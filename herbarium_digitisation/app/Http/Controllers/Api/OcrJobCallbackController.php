<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtractJob;
use App\Services\OcrJobStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OcrJobCallbackController extends Controller
{
    public function __construct(
        private readonly OcrJobStateService $stateService,
    ) {
    }

    public function status(string $jobId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'progress_step' => ['sometimes', 'nullable', 'string', 'max:255'],
            'error_message' => ['sometimes', 'nullable', 'string'],
            'images' => ['sometimes', 'array'],
            'images.*.stored_path' => ['sometimes', 'nullable', 'string'],
            'images.*.original_filename' => ['sometimes', 'nullable', 'string'],
            'images.*.ocr_text' => ['sometimes', 'nullable', 'string'],
            'images.*.llm_verified' => ['sometimes', 'nullable', 'array'],
        ]);

        $job = ExtractJob::where('external_job_id', $jobId)->first();

        if (!$job) {
            return response()->json(['message' => 'Job not found, callback ignored.'], 200);
        }

        $this->stateService->applyCallback($job, $validated);

        return response()->json(['message' => 'OK'], 200);
    }
}
