<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtractJob;
use App\Services\ImageQualityCheckStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageQualityCheckCallbackController extends Controller
{
    public function __construct(
        private readonly ImageQualityCheckStateService $stateService,
    ) {
    }

    public function status(string $jobId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'message' => ['sometimes', 'nullable', 'string'],
            'summary' => ['sometimes', 'nullable', 'array'],
            'images' => ['sometimes', 'nullable', 'array'],
            'images.*.stored_path' => ['required_with:images', 'string'],
            'images.*.filename' => ['sometimes', 'nullable', 'string'],
            'images.*.status' => ['sometimes', 'nullable', 'string'],
            'images.*.decision' => ['required_with:images', 'string', 'in:accept,reject'],
            'images.*.reasons' => ['sometimes', 'nullable', 'array'],
            'images.*.metrics' => ['sometimes', 'nullable', 'array'],
            'images.*.exif_orientation' => ['sometimes', 'nullable', 'integer'],
            'images.*.normalized_rotation' => ['sometimes', 'nullable', 'integer'],
        ]);

        $job = ExtractJob::where('external_job_id', $jobId)->first();

        if (!$job) {
            Log::warning('ImageQualityCheckCallbackController: callback for unknown job', [
                'job_id' => $jobId,
            ]);

            return response()->json(['message' => 'Job not found, callback ignored.'], 200);
        }

        try {
            $this->stateService->applyCallback($job, $validated);
        } catch (\Throwable $e) {
            Log::error('ImageQualityCheckCallbackController: callback handling failed', [
                'job_id' => $job->external_job_id,
                'error' => $e->getMessage(),
            ]);

            $job->update([
                'iqc_status' => 'failed',
                'status' => 'failed',
                'success' => 'FAILED',
                'error_message' => 'IQC callback processing failed: ' . $e->getMessage(),
                'iqc_failed_at' => now(),
                'failed_at' => now(),
            ]);
        }

        return response()->json(['message' => 'OK'], 200);
    }
}
