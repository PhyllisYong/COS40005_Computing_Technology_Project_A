<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDigitisationJobRequest;
use App\Jobs\DispatchImageQualityCheckJob;
use App\Models\ExtractJob;
use App\Services\UploadStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DigitisationController extends Controller
{
    public function __construct(
        private readonly UploadStorageService $uploadStorage,
    ) {
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
                'iqc_status'          => $job->iqc_status,
                'progress_step'       => $job->progress_step,
                'error_message'       => $job->error_message,
                'result_files'        => $job->result_files,
                'accepted_images_count' => $job->accepted_images_count,
                'rejected_images_count' => $job->rejected_images_count,
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

        // Create the local record immediately so we can track IQC and downstream states.
        $job = ExtractJob::create([
            'user_id'          => null,
            'external_job_id'  => $jobId,
            'job_name'         => $request->input('run_name'),
            'status'           => 'pending',
            'iqc_status'       => 'pending',
            'progress_step'    => 'upload_received',
            'config_overrides' => $request->sanitizedConfigOverrides() ?: null,
        ]);

        try {
            foreach ($request->file('files') as $file) {
                $originalFilename = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                $stored = $this->uploadStorage->storeUploadedFile($file, $jobId);
                $storedPath = $stored['stored_path'];

                [$width, $height] = @getimagesize($stored['absolute_path']) ?: [null, null];

                $job->images()->create([
                    'original_filename'  => $originalFilename,
                    'stored_path'        => $storedPath,
                    'mime_type'          => $mimeType,
                    'file_size'          => $fileSize,
                    'width'              => $width,
                    'height'             => $height,
                    'iqc_status'         => 'pending',
                ]);
            }

            DispatchImageQualityCheckJob::dispatch($job->getKey())
                ->onQueue('iqc');

            $job->update([
                'iqc_status'    => 'queued',
                'iqc_started_at'=> now(),
                'progress_step' => 'quality_check_queued',
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status'        => 'failed',
                'iqc_status'    => 'failed',
                'success'       => 'FAILED',
                'error_message' => $e->getMessage(),
                'iqc_failed_at' => now(),
                'failed_at'     => now(),
            ]);

            return redirect()->route('digitalisation')
                ->with('error', 'Upload processing failed: ' . $e->getMessage());
        }

        return redirect()->route('digitalisation')
            ->with('success', "Upload received for '{$job->job_name}'. Quality check is in progress.");
    }
}
