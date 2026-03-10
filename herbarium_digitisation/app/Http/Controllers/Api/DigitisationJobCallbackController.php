<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExtractJob;
use App\Services\DigitisationJobStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DigitisationJobCallbackController extends Controller
{
    public function __construct(private readonly DigitisationJobStateService $stateService)
    {
    }

    /**
     * Receive a status callback from the LeafMachine2 microservice.
     *
     * This endpoint is protected by VerifyCallbackToken middleware — no
     * session or Fortify authentication is needed or performed here.
     *
     * Always returns 200 OK so the microservice does not retry on logical
     * errors (unknown status, already-terminal job).  Only genuine server
     * errors produce non-2xx responses.
     */
    public function status(string $jobId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'        => ['required', 'string'],
            'progress_step' => ['sometimes', 'nullable', 'string', 'max:255'],
            'result_files'  => ['sometimes', 'nullable', 'array'],
            'result_files.*'=> ['string'],
            'output_path'   => ['sometimes', 'nullable', 'string'],
            'error_message' => ['sometimes', 'nullable', 'string'],
        ]);

        $job = ExtractJob::where('external_job_id', $jobId)->first();

        if (!$job) {
            // Return 200 — not 404 — so the microservice does not keep retrying
            Log::warning('DigitisationJobCallbackController: received callback for unknown job', [
                'job_id' => $jobId,
            ]);
            return response()->json(['message' => 'Job not found, callback ignored.'], 200);
        }

        // Normalise the raw microservice status — never trust it directly
        $internalStatus = $this->stateService->normalizeStatus($validated['status']);

        if ($internalStatus === null) {
            // Store the raw payload for debugging, return 200 to stop retries
            $job->update(['callback_payload' => $request->all()]);
            return response()->json(['message' => 'Unrecognised status, callback stored for review.'], 200);
        }

        $this->stateService->transition($job, $internalStatus, [
            'progress_step'  => $validated['progress_step'] ?? null,
            'result_files'   => $validated['result_files']  ?? null,
            'output_path'    => $validated['output_path']   ?? null,
            'error_message'  => $validated['error_message'] ?? null,
            'callback_payload' => $request->all(),
        ]);

        return response()->json(['message' => 'OK'], 200);
    }
}
