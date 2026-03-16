<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDigitisationJobRequest;
use App\Http\Requests\SubmitOCRJobRequest;
use App\Models\ExtractJob;
use App\Services\LeafMachine2Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DigitisationController extends Controller
{
    public function __construct(private readonly LeafMachine2Service $lm2)
    {
    }

    /**
     * Show the digitisation dashboard with the current user's job history.
     */
    public function index(): Response
    {
        $jobs = ExtractJob::orderByDesc('created_at')
            ->paginate(15)
            ->through(fn($job) => [
                'id'                  => $job->getKey(),
                'job_id'              => $job->external_job_id,
                'run_name'            => $job->job_name,
                'status'              => $job->status,
                'progress_step'       => $job->progress_step,
                'error_message'       => $job->error_message,
                'result_files'        => $job->result_files,
                'created_at'          => $job->created_at->toIso8601String(),
                'completed_at'        => $job->completed_at?->toIso8601String(),
                'failed_at'           => $job->failed_at?->toIso8601String(),
                'has_imported_results' => $job->hasImportedResults(),
            ]);

        return Inertia::render('digitalisation', [
            'jobs'    => $jobs,
            'isAdmin' => false,
            'userId'  => 0,
        ]);
    }

    /**
     * Receive a validated job submission, forward it to the microservice,
     * and persist the initial job record.
     */
    public function store(SubmitDigitisationJobRequest $request): RedirectResponse
    {
        $jobId = (string) Str::uuid();

        // Create the local record immediately so we have a row to update
        $job = ExtractJob::create([
            'user_id'          => null,
            'external_job_id'  => $jobId,
            'job_name'         => $request->input('run_name'),
            'status'           => 'pending',
            'config_overrides' => $request->sanitizedConfigOverrides() ?: null,
        ]);

        try {
            $this->lm2->submitJob(
                $jobId,
                $request->input('run_name'),
                $request->file('files'),
                $request->sanitizedConfigOverrides()
            );

            $job->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'success'       => 'FAILED',
                'error_message' => $e->getMessage(),
                'failed_at'     => now(),
            ]);

            return redirect()->route('digitalisation')
                ->with('error', 'Job submission failed: ' . $e->getMessage());
        }

        return redirect()->route('digitalisation')
            ->with('success', "Job '{$job->job_name}' submitted successfully.");
    }
    
    public function submitOCR(SubmitOCRJobRequest $request)
    {
        $request->validate([
            'images.*' => 'required|image|max:10240', // multiple images
            'run_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $job_id = now()->format('Ymd_His') . '_' . Str::random(4);
        $run_name = $request->input('run_name', 'Default Run');

        $uploaded_paths = [];
        foreach ($request->file('images') as $file) {
            $path = $file->store("digitisation/{$user->id}/{$job_id}", 'public');
            $uploaded_paths[] = $path;
        }

        // Create job record
        $job = DigitisationJob::create([
            'user_id' => $user->id,
            'job_id' => $job_id,
            'run_name' => $run_name,
            'status' => 'pending',
            'output_path' => json_encode($uploaded_paths),
        ]);

        $python_api_url = config('services.ocr_pipeline.url'); // e.g., http://localhost:5000/process

        $results = [];
        foreach ($uploaded_paths as $path) {
            $response = Http::attach(
                'file', fopen(storage_path("app/public/{$path}"), 'r'), basename($path)
            )->post($python_api_url, [
                'job_id' => $job_id,
            ]);

            if ($response->successful()) {
                $res = $response->json()['results'][0]; // single image
                DigitisationResult::create([
                    'digitisation_job_id' => $job->id,
                    'record_index' => 0,
                    'data' => $res,
                ]);
                $results[] = $res;
            } else {
                $job->update([
                    'status' => 'failed',
                    'error_message' => $response->body(),
                ]);
                return response()->json(['message' => 'OCR failed', 'error' => $response->body()], 500);
            }
        }

        $job->update([
            'status' => 'completed',
            'results_imported_at' => now(),
        ]);

        return response()->json([
            'job_id' => $job_id,
            'results' => $results,
        ]);
    }
}
