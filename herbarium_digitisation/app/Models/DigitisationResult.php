<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DigitisationResult extends Model
{
    protected $fillable = [
        'digitisation_job_id',
        'record_index',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(DigitisationJob::class, 'digitisation_job_id');
    }
}
