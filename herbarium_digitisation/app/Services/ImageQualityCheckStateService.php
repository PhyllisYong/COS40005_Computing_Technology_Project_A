<?php

namespace App\Services;

use App\Models\ExtractJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ImageQualityCheckStateService
{
    public function __construct(
        private readonly LeafMachine2Service $lm2,
        private readonly UploadStorageService $uploadStorage,
    ) {
    }

    public function normalizeStatus(string $rawStatus): ?string
    {
        return match (strtolower(trim($rawStatus))) {
            'pending', 'queued', 'running' => 'running',
            'completed', 'complete', 'done' => 'completed',
            'failed', 'error' => 'failed',
            default => null,
        };
    }

    /**
     * Persist per-image IQC outcomes and progress the main job lifecycle.
     *
     * @param  array{status:string,images?:array<int,array<string,mixed>>,summary?:array<string,mixed>,message?:string}  $payload
     */
    public function applyCallback(ExtractJob $job, array $payload): void
    {
        $internalStatus = $this->normalizeStatus($payload['status']);

        if ($job->iqc_status === 'completed' && $internalStatus === 'completed') {
            // Duplicate completion callback: keep state unchanged to avoid duplicate LM2 submissions.
            return;
        }

        if ($internalStatus === null) {
            $job->update([
                'iqc_summary' => ['raw_payload' => $payload, 'note' => 'Unrecognized IQC status'],
            ]);
            return;
        }

        if ($internalStatus === 'running') {
            $job->update([
                'iqc_status' => 'running',
                'progress_step' => 'quality_check_running',
                'iqc_started_at' => $job->iqc_started_at ?? now(),
                'iqc_summary' => $payload['summary'] ?? $job->iqc_summary,
            ]);
            return;
        }

        if ($internalStatus === 'failed') {
            $job->update([
                'iqc_status' => 'failed',
                'status' => 'failed',
                'success' => 'FAILED',
                'error_message' => $payload['message'] ?? 'Image quality check failed.',
                'iqc_failed_at' => now(),
                'failed_at' => now(),
                'iqc_summary' => $payload['summary'] ?? null,
                'progress_step' => 'quality_check_failed',
            ]);
            return;
        }

        $images = collect($payload['images'] ?? []);

        $this->applyImageDecisions($job, $images);

        $acceptedImages = $job->images()
            ->where('accepted_for_submission', true)
            ->get(['stored_path', 'original_filename'])
            ->map(fn($image) => [
                'stored_path' => $image->stored_path,
                'original_filename' => $image->original_filename,
            ])
            ->filter(function (array $image) use ($job) {
                $exists = $this->uploadStorage->exists($image['stored_path']);

                if (!$exists) {
                    Log::warning('ImageQualityCheckStateService: accepted image missing from storage', [
                        'job_id' => $job->external_job_id,
                        'stored_path' => $image['stored_path'],
                    ]);
                }

                return $exists;
            })
            ->values()
            ->all();

        $rejectedCount = $job->images()->where('accepted_for_submission', false)->count();
        $acceptedCount = count($acceptedImages);

        $job->update([
            'iqc_status' => 'completed',
            'iqc_completed_at' => now(),
            'accepted_images_count' => $acceptedCount,
            'rejected_images_count' => $rejectedCount,
            'iqc_summary' => $payload['summary'] ?? null,
            'progress_step' => 'quality_check_completed',
        ]);

        if ($acceptedCount === 0) {
            Log::warning('ImageQualityCheckStateService: no accepted images available for LeafMachine submission', [
                'job_id' => $job->external_job_id,
            ]);

            $job->update([
                'status' => 'failed',
                'success' => 'FAILED',
                'error_message' => 'All uploaded images were rejected by quality checks. Please reupload clearer images.',
                'failed_at' => now(),
            ]);
            return;
        }

        Log::info('ImageQualityCheckStateService: submitting accepted images to LeafMachine2', [
            'job_id' => $job->external_job_id,
            'accepted_images' => $acceptedCount,
            'rejected_images' => $rejectedCount,
        ]);

        $this->lm2->submitStoredImages(
            $job->external_job_id,
            $job->job_name ?? 'Digitisation Run',
            $acceptedImages,
            $job->config_overrides ?? []
        );

        $job->update([
            'status' => 'accepted',
            'accepted_at' => now(),
            'progress_step' => $rejectedCount > 0
                ? 'quality_check_partial_pass_submitted'
                : 'quality_check_pass_submitted',
        ]);

        Log::info('ImageQualityCheckStateService: LeafMachine2 submission succeeded', [
            'job_id' => $job->external_job_id,
        ]);
    }

    /**
     * @param  Collection<int, array<string,mixed>>  $images
     */
    private function applyImageDecisions(ExtractJob $job, Collection $images): void
    {
        foreach ($images as $decision) {
            $storedPath = $decision['stored_path'] ?? null;

            if (!is_string($storedPath) || $storedPath === '') {
                continue;
            }

            $status = strtolower((string) ($decision['status'] ?? 'completed'));
            $verdict = strtolower((string) ($decision['decision'] ?? 'reject'));

            $rotation = $decision['normalized_rotation'] ?? null;
            if (!is_int($rotation)) {
                $rotation = 0;
            }

            $exifOrientation = $decision['exif_orientation'] ?? null;
            if (!is_int($exifOrientation)) {
                $exifOrientation = null;
            }

            $updated = $job->images()
                ->where('stored_path', $storedPath)
                ->update([
                    'iqc_status' => $status,
                    'iqc_decision' => $verdict,
                    'iqc_reasons' => $decision['reasons'] ?? null,
                    'iqc_metrics' => $decision['metrics'] ?? null,
                    'iqc_payload' => $decision,
                    'iqc_checked_at' => now(),
                    'accepted_for_submission' => $verdict === 'accept',
                    'exif_orientation' => $exifOrientation,
                    'normalized_rotation' => $rotation,
                ]);

            if ($updated === 0) {
                Log::warning('ImageQualityCheckStateService: callback image not found for path', [
                    'job_id' => $job->external_job_id,
                    'stored_path' => $storedPath,
                ]);
            }
        }
    }
}
