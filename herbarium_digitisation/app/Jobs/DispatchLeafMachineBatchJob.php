<?php

namespace App\Jobs;

use App\Models\ExtractJob;
use App\Services\LeafMachine2Service;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchLeafMachineBatchJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * @param  array<int, array{stored_path:string, original_filename:string}>  $acceptedImages
     * @param  array<string, mixed>  $configOverrides
     */
    public function __construct(
        private readonly int $jobId,
        private readonly array $acceptedImages,
        private readonly string $runName,
        private readonly array $configOverrides = [],
    ) {
    }

    public function handle(LeafMachine2Service $lm2): void
    {
        $job = ExtractJob::find($this->jobId);

        if (!$job) {
            return;
        }

        $lm2->submitStoredImages(
            $job->external_job_id,
            $this->runName,
            $this->acceptedImages,
            $this->configOverrides
        );
    }

    public function failed(?\Throwable $exception): void
    {
        $job = ExtractJob::find($this->jobId);

        if (!$job) {
            return;
        }

        $message = $exception?->getMessage() ?? 'LeafMachine dispatch failed.';

        $job->update([
            'error_message' => 'LeafMachine dispatch failed: ' . $message,
        ]);
    }
}
