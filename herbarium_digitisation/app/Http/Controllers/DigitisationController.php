<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDigitisationJobRequest;
use App\Jobs\DispatchImageQualityCheckJob;
use App\Jobs\DispatchLeafMachineBatchJob;
use App\Jobs\DispatchOcrBatchJob;
use App\Models\ExtractJob;
use App\Services\UploadStorageService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
    public function store(SubmitDigitisationJobRequest $request): RedirectResponse|JsonResponse
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

                $stored = $this->uploadStorage->storeUploadedFile($file, $jobId, (string) $request->input('run_name'));
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
                ->onQueue('default');

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

            $message = 'Upload processing failed: ' . $e->getMessage();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 422);
            }

            return redirect()->route('digitalisation')
                ->with('error', $message);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Upload received for '{$job->job_name}'. Quality check is in progress.",
                'job_id' => $job->external_job_id,
            ]);
        }

        return redirect()->route('digitalisation')
            ->with('success', "Upload received for '{$job->job_name}'. Quality check is in progress.");
    }

    /**
     * Return a compact IQC status payload for polling from the upload page.
     */
    public function status(string $externalJobId, Request $request): JsonResponse
    {
        $job = ExtractJob::with(['images' => fn($query) => $query->orderBy('image_id')])
            ->where('external_job_id', $externalJobId)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Digitisation job not found.',
            ], 404);
        }

        return response()->json([
            'job_id' => $job->external_job_id,
            'status' => $job->status,
            'iqc_status' => $job->iqc_status,
            'progress_step' => $job->progress_step,
            'accepted_images_count' => $job->accepted_images_count ?? 0,
            'rejected_images_count' => $job->rejected_images_count ?? 0,
            'error_message' => $job->error_message,
            'images' => $job->images->map(fn($image) => [
                'image_id' => $image->image_id,
                'original_filename' => $image->original_filename,
                'stored_path' => $image->stored_path,
                'iqc_status' => $image->iqc_status,
                'iqc_decision' => $image->iqc_decision,
                'accepted_for_submission' => (bool) $image->accepted_for_submission,
                'iqc_reasons' => $image->iqc_reasons ?? [],
                'iqc_metrics' => $image->iqc_metrics,
            ])->values(),
        ]);
    }

    /**
     * Submit only accepted IQC images to downstream services as one batch.
     */
    public function submitAcceptedBatch(string $externalJobId, Request $request): JsonResponse
    {
        $job = ExtractJob::with('images')
            ->where('external_job_id', $externalJobId)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Digitisation job not found.',
            ], 404);
        }

        if ($job->iqc_status !== 'completed') {
            return response()->json([
                'message' => 'Quality check is not completed yet.',
            ], 422);
        }

        if (str_contains((string) $job->progress_step, 'submitted')) {
            return response()->json([
                'message' => 'This batch has already been submitted.',
            ], 200);
        }

        $acceptedImages = $job->images
            ->filter(fn($image) => $image->accepted_for_submission === true && $image->iqc_decision === 'accept')
            ->map(fn($image) => [
                'stored_path' => $image->stored_path,
                'original_filename' => $image->original_filename,
            ])
            ->filter(fn(array $image) => $this->uploadStorage->exists($image['stored_path']))
            ->values()
            ->all();

        if ($acceptedImages === []) {
            return response()->json([
                'message' => 'No accepted images are available for submission.',
            ], 422);
        }

        $rejectedNames = $job->images
            ->filter(fn($image) => $image->accepted_for_submission === false)
            ->pluck('original_filename')
            ->filter(fn($name) => is_string($name) && $name !== '')
            ->values()
            ->all();

        Bus::chain([
            new DispatchOcrBatchJob(
                $job->getKey(),
                $acceptedImages,
                (string) ($job->job_name ?? 'Digitisation Run')
            ),
            new DispatchLeafMachineBatchJob(
                $job->getKey(),
                $acceptedImages,
                (string) ($job->job_name ?? 'Digitisation Run'),
                $job->config_overrides ?? []
            ),
        ])->onQueue('default')->dispatch();

        $job->update([
            'progress_step' => $rejectedNames !== []
                ? 'quality_check_partial_pass_submitted'
                : 'quality_check_pass_submitted',
        ]);

        return response()->json([
            'message' => 'Accepted images queued for submission to OCR and LeafMachine.',
            'submitted_images_count' => count($acceptedImages),
            'rejected_images' => $rejectedNames,
        ]);
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
