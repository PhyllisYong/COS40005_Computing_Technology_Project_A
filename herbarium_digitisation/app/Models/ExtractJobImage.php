<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractJobImage extends Model
{
    protected $table = 'extract_job_images';
    protected $primaryKey = 'image_id';

    protected $fillable = [
        'job_id',
        'original_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'width',
        'height',
        'exif_orientation',
        'normalized_rotation',
        'iqc_status',
        'iqc_decision',
        'iqc_reasons',
        'iqc_metrics',
        'iqc_payload',
        'iqc_checked_at',
        'accepted_for_submission',
        'ocr_status',
        'ocr_payload',
        'ocr_text',
        'ocr_llm_verified',
        'ocr_processed_at',
    ];

    protected $casts = [
        'iqc_reasons' => 'array',
        'iqc_metrics' => 'array',
        'iqc_payload' => 'array',
        'iqc_checked_at' => 'datetime',
        'accepted_for_submission' => 'boolean',
        'ocr_payload' => 'array',
        'ocr_llm_verified' => 'array',
        'ocr_processed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExtractJob::class, 'job_id', 'job_id');
    }
}
