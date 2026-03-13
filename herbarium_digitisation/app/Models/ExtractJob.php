<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExtractJob extends Model
{
    protected $table      = 'extract_jobs';
    protected $primaryKey = 'job_id';

    protected $fillable = [
        'job_name',
        'external_job_id',
        'status',
        'progress_step',
        'result_files',
        'output_path',
        'error_message',
        'config_overrides',
        'callback_payload',
        'user_id',
        'success',
        'accepted_at',
        'started_at',
        'completed_at',
        'failed_at',
        'results_imported_at',
    ];

    protected $casts = [
        'result_files'       => 'array',
        'config_overrides'   => 'array',
        'callback_payload'   => 'array',
        'accepted_at'        => 'datetime',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
        'failed_at'          => 'datetime',
        'results_imported_at'=> 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(PlantMeasurement::class, 'job_id', 'job_id');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'failed'], true);
    }

    public function canImportResults(): bool
    {
        return $this->status === 'completed' && !empty($this->result_files);
    }

    public function hasImportedResults(): bool
    {
        return $this->results_imported_at !== null;
    }
}
