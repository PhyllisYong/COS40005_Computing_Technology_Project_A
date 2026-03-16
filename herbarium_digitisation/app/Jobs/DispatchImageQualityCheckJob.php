<?php

namespace App\Jobs;

use App\Models\ExtractJob;
use App\Services\ImageQualityCheckService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchImageQualityCheckJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly int $jobId,
    ) {
    }

    public function handle(ImageQualityCheckService $iqc): void
    {
        $this->console("Starting dispatch for internal job_id={$this->jobId}");

        $job = ExtractJob::with('images')->find($this->jobId);

        if (!$job) {
            $this->console("Job not found. Skipping dispatch.");
            return;
        }

        $images = $job->images
            ->map(fn($image) => [
                'stored_path' => $image->stored_path,
                'original_filename' => $image->original_filename,
            ])
            ->all();

        if ($images === []) {
            $this->console("No images found for external_job_id={$job->external_job_id}. Marking failed.");
            $job->update([
                'iqc_status' => 'failed',
                'status' => 'failed',
                'success' => 'FAILED',
                'error_message' => 'No images found for quality check dispatch.',
                'iqc_failed_at' => now(),
                'failed_at' => now(),
            ]);
            return;
        }

        $job->update([
            'iqc_status' => 'dispatching',
            'progress_step' => 'quality_check_dispatching',
            'iqc_started_at' => $job->iqc_started_at ?? now(),
        ]);

        $this->console("Dispatching {$job->images->count()} image(s) to IQC for external_job_id={$job->external_job_id}");

        $response = $iqc->submitStoredImages($job->external_job_id, $images);

        $status = $response['status'] ?? 'unknown';
        $queued = $response['queued_images'] ?? count($images);
        $this->console("IQC accepted request: status={$status}, queued_images={$queued}, external_job_id={$job->external_job_id}");

        $job->update([
            'iqc_status' => 'running',
            'progress_step' => 'quality_check_running',
        ]);

        $this->console("Job marked as quality_check_running for external_job_id={$job->external_job_id}");
    }

    public function failed(?\Throwable $exception): void
    {
        $job = ExtractJob::find($this->jobId);

        if (!$job) {
            return;
        }

        $message = $exception?->getMessage() ?? 'IQC dispatch failed after retries.';

        $this->console("Dispatch failed for internal job_id={$this->jobId}: {$message}");

        $job->update([
            'iqc_status' => 'failed',
            'status' => 'failed',
            'success' => 'FAILED',
            'error_message' => 'IQC dispatch failed: ' . $message,
            'iqc_failed_at' => now(),
            'failed_at' => now(),
        ]);
    }

    private function console(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, '[IQC Queue] ' . $message . PHP_EOL);
        }
    }
}
