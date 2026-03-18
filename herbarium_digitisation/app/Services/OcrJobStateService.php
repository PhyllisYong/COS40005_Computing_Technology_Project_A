<?php

namespace App\Services;

use App\Models\ExtractJob;

class OcrJobStateService
{
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
     * @param  array{status:string,images?:array<int,array<string,mixed>>,progress_step?:string,error_message?:string}  $payload
     */
    public function applyCallback(ExtractJob $job, array $payload): void
    {
        $internalStatus = $this->normalizeStatus($payload['status']);

        if ($internalStatus === null) {
            return;
        }

        if ($internalStatus === 'running') {
            $job->update([
                'ocr_status' => 'running',
                'ocr_progress_step' => $payload['progress_step'] ?? 'ocr_running',
                'ocr_started_at' => $job->ocr_started_at ?? now(),
            ]);
            return;
        }

        if ($internalStatus === 'failed') {
            $job->update([
                'ocr_status' => 'failed',
                'ocr_progress_step' => $payload['progress_step'] ?? 'ocr_failed',
                'ocr_error_message' => $payload['error_message'] ?? 'OCR pipeline failed.',
                'ocr_failed_at' => now(),
            ]);
            return;
        }

        foreach (($payload['images'] ?? []) as $imageResult) {
            $storedPath = $imageResult['stored_path'] ?? null;
            $filename = $imageResult['original_filename'] ?? null;

            $query = $job->images();

            if (is_string($storedPath) && $storedPath !== '') {
                $query->where('stored_path', $storedPath);
            } elseif (is_string($filename) && $filename !== '') {
                $query->where('original_filename', $filename);
            } else {
                continue;
            }

            $query->update([
                'ocr_status' => 'completed',
                'ocr_payload' => $imageResult,
                'ocr_text' => is_string($imageResult['ocr_text'] ?? null) ? $imageResult['ocr_text'] : null,
                'ocr_llm_verified' => is_array($imageResult['llm_verified'] ?? null) ? $imageResult['llm_verified'] : null,
                'ocr_processed_at' => now(),
            ]);
        }

        $job->update([
            'ocr_status' => 'completed',
            'ocr_progress_step' => $payload['progress_step'] ?? 'ocr_completed',
            'ocr_error_message' => null,
            'ocr_completed_at' => now(),
        ]);
    }
}
