<?php

namespace App\Events;

use App\Models\ExtractJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DigitisationJobStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly ExtractJob $job)
    {
    }

    /**
     * Broadcast on the authenticated user's private channel so no other user
     * can receive updates for jobs they do not own.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("digitisation.user.{$this->job->user_id}"),
        ];
    }

    /**
     * Payload sent to the client on each broadcast.
     * Never includes config_overrides or callback_payload — no internal data exposed.
     */
    public function broadcastWith(): array
    {
        return [
            'job_id'        => $this->job->external_job_id,
            'status'        => $this->job->status,
            'progress_step' => $this->job->progress_step,
            'error_message' => $this->job->error_message,
            'result_files'  => $this->job->result_files,
            'completed_at'  => $this->job->completed_at?->toIso8601String(),
            'failed_at'     => $this->job->failed_at?->toIso8601String(),
        ];
    }
}
