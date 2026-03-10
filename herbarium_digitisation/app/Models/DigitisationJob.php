<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigitisationJob extends Model
{
    protected $fillable = [
        'user_id',
        'job_id',
        'run_name',
        'status',
        'progress_step',
        'result_files',
        'output_path',
        'error_message',
        'config_overrides',
        'callback_payload',
        'accepted_at',
        'started_at',
        'completed_at',
        'failed_at',
        'results_imported_at',
    ];

    protected $casts = [
        'result_files'      => 'array',
        'config_overrides'  => 'array',
        'callback_payload'  => 'array',
        'accepted_at'       => 'datetime',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
        'failed_at'         => 'datetime',
        'results_imported_at' => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(DigitisationResult::class);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    /** True when the job has reached a terminal state (no further transitions allowed). */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    /** True when results are available for preview or import. */
    public function canImportResults(): bool
    {
        return $this->status === 'completed';
    }

    /** True when CSV rows have been written to digitisation_results. */
    public function hasImportedResults(): bool
    {
        return $this->results_imported_at !== null;
    }
}
