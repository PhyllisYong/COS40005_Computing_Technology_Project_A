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
        'iqc_status',
        'ocr_status',
        'ocr_progress_step',
        'ocr_error_message',
        'progress_step',
        'result_files',
        'output_path',
        'error_message',
        'iqc_summary',
        'config_overrides',
        'callback_payload',
        'user_id',
        'success',
        'accepted_images_count',
        'rejected_images_count',
        'iqc_started_at',
        'iqc_completed_at',
        'iqc_failed_at',
        'ocr_started_at',
        'ocr_completed_at',
        'ocr_failed_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'failed_at',
        'results_imported_at',
    ];

    protected $casts = [
        'result_files'       => 'array',
        'iqc_summary'        => 'array',
        'config_overrides'   => 'array',
        'callback_payload'   => 'array',
        'accepted_images_count' => 'integer',
        'rejected_images_count' => 'integer',
        'iqc_started_at'     => 'datetime',
        'iqc_completed_at'   => 'datetime',
        'iqc_failed_at'      => 'datetime',
        'ocr_started_at'     => 'datetime',
        'ocr_completed_at'   => 'datetime',
        'ocr_failed_at'      => 'datetime',
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

    public function images(): HasMany
    {
        return $this->hasMany(ExtractJobImage::class, 'job_id', 'job_id');
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
