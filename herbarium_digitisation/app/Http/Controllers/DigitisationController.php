<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitDigitisationJobRequest;
use App\Jobs\DispatchImageQualityCheckJob;
use App\Models\ExtractJob;
use App\Services\LeafMachine2Service;
use App\Services\OcrPipelineService;
use App\Services\UploadStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DigitisationController extends Controller
{
    public function __construct(
        private readonly UploadStorageService $uploadStorage,
        private readonly LeafMachine2Service $lm2,
        private readonly OcrPipelineService $ocr,
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
            'ocr_status' => $job->ocr_status,
            'ocr_progress_step' => $job->ocr_progress_step,
            'ocr_error_message' => $job->ocr_error_message,
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

        $errors = [];

        try {
            $this->ocr->submitStoredImages(
                $job->external_job_id,
                $acceptedImages
            );

            $job->update([
                'ocr_status' => 'queued',
                'ocr_progress_step' => 'ocr_queued',
                'ocr_error_message' => null,
                'ocr_started_at' => $job->ocr_started_at ?? now(),
            ]);
        } catch (Throwable $e) {
            $errors[] = 'OCR submission failed: ' . $e->getMessage();

            $job->update([
                'ocr_status' => 'failed',
                'ocr_progress_step' => 'ocr_submission_failed',
                'ocr_error_message' => $e->getMessage(),
                'ocr_failed_at' => now(),
            ]);
        }

        try {
            $this->lm2->submitStoredImages(
                $job->external_job_id,
                $job->job_name ?? 'Digitisation Run',
                $acceptedImages,
                $job->config_overrides ?? []
            );
        } catch (Throwable $e) {
            $errors[] = 'LeafMachine submission failed: ' . $e->getMessage();
        }

        if ($errors !== []) {
            return response()->json([
                'message' => implode(' ', $errors),
            ], 502);
        }

        $job->update([
            'progress_step' => $rejectedNames !== []
                ? 'quality_check_partial_pass_dual_submitted'
                : 'quality_check_pass_dual_submitted',
        ]);

        return response()->json([
            'message' => 'Accepted images submitted to OCR and LeafMachine as one batch.',
            'submitted_images_count' => count($acceptedImages),
            'rejected_images' => $rejectedNames,
        ]);
    }

    /**
     * Return OCR-specific result payload for the digitalisation1 page.
     */
    public function ocrResults(string $externalJobId): JsonResponse
    {
        $job = ExtractJob::with(['images' => fn($query) => $query->orderBy('image_id')])
            ->where('external_job_id', $externalJobId)
            ->first();

        if (!$job) {
            return response()->json([
                'message' => 'Digitisation job not found.',
            ], 404);
        }

        $images = $job->images
            ->filter(fn($image) => $image->accepted_for_submission === true && $image->iqc_decision === 'accept')
            ->map(fn($image) => [
                'image_id' => $image->image_id,
                'original_filename' => $image->original_filename,
                'stored_path' => $image->stored_path,
                'preview_url' => route('digitisation.jobs.images.preview', [
                    'externalJobId' => $job->external_job_id,
                    'imageId' => $image->image_id,
                ]),
                'ocr_status' => $image->ocr_status ?? 'pending',
                'llm_verified' => $image->ocr_llm_verified ?? [],
                'editable_details' => $this->extractEditableDetails(is_array($image->ocr_llm_verified) ? $image->ocr_llm_verified : []),
            ])
            ->values();

        return response()->json([
            'job_id' => $job->external_job_id,
            'ocr_status' => $job->ocr_status ?? 'pending',
            'ocr_progress_step' => $job->ocr_progress_step,
            'ocr_error_message' => $job->ocr_error_message,
            'leafmachine_status' => $job->status ?? 'pending',
            'leafmachine_progress_step' => $job->progress_step,
            'leafmachine_started_at' => $job->started_at?->toIso8601String(),
            'leafmachine_completed_at' => $job->completed_at?->toIso8601String(),
            'leafmachine_failed_at' => $job->failed_at?->toIso8601String(),
            'images' => $images,
        ]);
    }

    public function imagePreview(string $externalJobId, int $imageId): BinaryFileResponse|JsonResponse
    {
        $job = ExtractJob::where('external_job_id', $externalJobId)->first();

        if (!$job) {
            return response()->json([
                'message' => 'Digitisation job not found.',
            ], 404);
        }

        $image = $job->images()->where('image_id', $imageId)->first();
        if (!$image) {
            return response()->json([
                'message' => 'Image not found for this job.',
            ], 404);
        }

        if (!is_string($image->stored_path) || $image->stored_path === '') {
            return response()->json([
                'message' => 'Image path is not available.',
            ], 422);
        }

        $absolutePath = $this->uploadStorage->absolutePath($image->stored_path);
        if (!file_exists($absolutePath)) {
            return response()->json([
                'message' => 'Image file is missing on disk.',
            ], 404);
        }

        return response()->file($absolutePath, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function saveImageDetails(string $externalJobId, int $imageId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'scientific' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Invalid details payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $job = ExtractJob::where('external_job_id', $externalJobId)->first();

        if (!$job) {
            return response()->json([
                'message' => 'Digitisation job not found.',
            ], 404);
        }

        $image = $job->images()->where('image_id', $imageId)->first();
        if (!$image) {
            return response()->json([
                'message' => 'Image not found for this job.',
            ], 404);
        }

        $editable = $this->normalizeEditableDetails($validator->validated());
        $current = is_array($image->ocr_llm_verified) ? $image->ocr_llm_verified : [];
        $current['edited_details'] = $editable;

        $image->update([
            'ocr_llm_verified' => $current,
        ]);

        return response()->json([
            'message' => 'Image details saved.',
            'image_id' => $image->image_id,
            'editable_details' => $editable,
            'llm_verified' => $current,
        ]);
    }

    private function normalizeEditableDetails(array $input): array
    {
        return [
            'name' => trim((string) ($input['name'] ?? '')),
            'scientific' => trim((string) ($input['scientific'] ?? '')),
            'location' => trim((string) ($input['location'] ?? '')),
            'date' => trim((string) ($input['date'] ?? '')),
        ];
    }

    private function extractEditableDetails(array $llmVerified): array
    {
        if (isset($llmVerified['edited_details']) && is_array($llmVerified['edited_details'])) {
            return $this->normalizeEditableDetails($llmVerified['edited_details']);
        }

        $fieldValidation = [];
        if (isset($llmVerified['field_validation']) && is_array($llmVerified['field_validation'])) {
            $fieldValidation = $llmVerified['field_validation'];
        }

        $scientific = $this->pickFieldValue($fieldValidation, 'species')
            ?: $this->pickAnyString($llmVerified, ['scientific_name', 'taxon', 'species']);

        $location = $this->pickFieldValue($fieldValidation, 'locality')
            ?: $this->pickFieldValue($fieldValidation, 'municipality')
            ?: $this->pickFieldValue($fieldValidation, 'region')
            ?: $this->pickFieldValue($fieldValidation, 'country')
            ?: $this->pickAnyString($llmVerified, ['location', 'locality', 'country', 'state']);

        return [
            'name' => $this->pickAnyString($llmVerified, ['specimen_name', 'collector_name', 'name']),
            'scientific' => $scientific,
            'location' => $location,
            'date' => $this->pickAnyString($llmVerified, ['date_collected', 'event_date', 'date']),
        ];
    }

    private function pickFieldValue(array $fieldValidation, string $field): string
    {
        $value = $fieldValidation[$field] ?? null;
        if (!is_array($value)) {
            return '';
        }

        $suggestion = trim((string) ($value['suggestion'] ?? ''));
        if ($suggestion !== '') {
            return $suggestion;
        }

        return trim((string) ($value['original'] ?? ''));
    }

    private function pickAnyString(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (is_string($value) || is_numeric($value) || is_bool($value)) {
                $output = trim((string) $value);
                if ($output !== '') {
                    return $output;
                }
            }
        }

        return '';
    }
}
