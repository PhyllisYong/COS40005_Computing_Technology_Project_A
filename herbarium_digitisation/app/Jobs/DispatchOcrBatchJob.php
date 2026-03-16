<?php

namespace App\Jobs;

use App\Models\ExtractJob;
use App\Services\OcrPipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchOcrBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<int, array{stored_path:string, original_filename:string}>  $acceptedImages
     */
    public function __construct(
        private readonly int $jobId,
        private readonly array $acceptedImages,
        private readonly string $runName,
    ) {
    }

    public function handle(OcrPipelineService $ocr): void
    {
        $job = ExtractJob::find($this->jobId);

        if (!$job) {
            return;
        }

        $ocr->submitStoredImages(
            $job->external_job_id,
            $this->runName,
            $this->acceptedImages,
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $job = ExtractJob::find($this->jobId);

        if (!$job) {
            return;
        }

        $message = $exception?->getMessage() ?? 'OCR dispatch failed.';

        $job->update([
            'error_message' => 'OCR dispatch failed: ' . $message,
        ]);
    }
}
