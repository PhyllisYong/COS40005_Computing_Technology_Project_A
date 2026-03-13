<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantMeasurement extends Model
{
    protected $table      = 'plant_measurements';
    protected $primaryKey = 'measurement_id';

    protected $fillable = [
        'job_id',
        'component_name',
        'component_type',
        'perimeter',
        'area',
        'bbox_min_long_side',
        'bbox_min_short_side',
        'units',
        'conversion_factor',
        'aspect_ratio',
    ];

    public function extractJob(): BelongsTo
    {
        return $this->belongsTo(ExtractJob::class, 'job_id', 'job_id');
    }
}
