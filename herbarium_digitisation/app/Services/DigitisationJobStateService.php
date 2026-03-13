<?php

namespace App\Services;

use App\Events\DigitisationJobStatusUpdated;
use App\Models\ExtractJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DigitisationJobStateService
{
    /**
     * Maps every status string the microservice may send to its internal
     * Laravel equivalent.  Any string absent from this map is unrecognised
     * and must be rejected by normalizeStatus().
     *
     * 'cancel_requested' has no internal equivalent — it is treated as a
     * failure because the job will not produce results.
     */
    private const EXTERNAL_STATUS_MAP = [
        'pending'          => 'pending',
        'accepted'         => 'accepted',
        'running'          => 'running',
        'completed'        => 'completed',
        'failed'           => 'failed',
        'cancel_requested' => 'failed',
    ];

    /**
     * Allowed state transitions. Only these forward-moves are permitted.
     * Terminal states (completed, failed) have no outgoing transitions.
     */
    private const ALLOWED_TRANSITIONS = [
        'pending'  => ['accepted', 'failed'],
        'accepted' => ['running',  'failed'],
        'running'  => ['completed','failed'],
    ];

    /**
     * Translate a raw microservice status string to an internal status value.
     *
     * Returns null when the value is not in the known map — callers must log
     * a warning and skip the state transition (still return 200 to microservice).
     */
    public function normalizeStatus(string $externalStatus): ?string
    {
        $normalized = self::EXTERNAL_STATUS_MAP[$externalStatus] ?? null;

        if ($normalized === null) {
            Log::warning('DigitisationJobStateService: unrecognised external status received', [
                'external_status' => $externalStatus,
            ]);
        }

        return $normalized;
    }

    /**
     * Apply a validated status transition to a job, set lifecycle timestamps,
     * and fire a broadcast event when the effective state changes.
     *
     * Returns true when the transition was applied, false when it was skipped
     * (idempotent / terminal guard).
     */
    public function transition(ExtractJob $job, string $internalStatus, array $meta = []): bool
    {
        // Guard: never leave a terminal state
        if ($job->isTerminal()) {
            Log::debug('DigitisationJobStateService: job already terminal, transition skipped', [
                'job_id'          => $job->external_job_id,
                'current_status'  => $job->status,
                'requested_status' => $internalStatus,
            ]);
            return false;
        }

        // Guard: only allow defined forward transitions
        $allowed = self::ALLOWED_TRANSITIONS[$job->status] ?? [];
        if (!in_array($internalStatus, $allowed, true) && $job->status !== $internalStatus) {
            Log::warning('DigitisationJobStateService: illegal status transition', [
                'job_id'   => $job->external_job_id,
                'from'     => $job->status,
                'to'       => $internalStatus,
            ]);
            return false;
        }

        $statusChanged   = $job->status !== $internalStatus;
        $progressChanged = isset($meta['progress_step']) && $meta['progress_step'] !== $job->progress_step;

        DB::transaction(function () use ($job, $internalStatus, $meta) {
            $job->status = $internalStatus;

            if (isset($meta['progress_step'])) {
                $job->progress_step = $meta['progress_step'];
            }
            if (isset($meta['error_message'])) {
                $job->error_message = $meta['error_message'];
            }
            if (isset($meta['result_files'])) {
                $job->result_files = $meta['result_files'];
            }
            if (isset($meta['output_path'])) {
                $job->output_path = $meta['output_path'];
            }
            if (isset($meta['callback_payload'])) {
                $job->callback_payload = $meta['callback_payload'];
            }

            // Sync the legacy success enum with the final status
            if ($internalStatus === 'completed') {
                $job->success = 'SUCCESS';
            } elseif ($internalStatus === 'failed') {
                $job->success = 'FAILED';
            }

            // Apply lifecycle timestamps — never overwrite once set
            $this->applyTimestamps($job, $internalStatus);

            $job->save();
        });

        // Broadcast only when something meaningful changed
        if ($statusChanged || $progressChanged) {
            event(new DigitisationJobStatusUpdated($job));
        }

        return true;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function applyTimestamps(ExtractJob $job, string $status): void
    {
        $now = now();

        match ($status) {
            'accepted'  => $job->accepted_at  ??= $now,
            'running'   => $job->started_at   ??= $now,
            'completed' => $job->completed_at ??= $now,
            'failed'    => $job->failed_at    ??= $now,
            default     => null,
        };
    }
}
