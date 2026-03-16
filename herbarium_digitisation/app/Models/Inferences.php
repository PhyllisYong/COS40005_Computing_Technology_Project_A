<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inferences extends Model
{
    /** @use HasFactory<\Database\Factories\InferencesFactory> */
    use HasFactory;
    protected $primaryKey = 'inference_id';
    protected $fillable = ['predicted_label','confidence_score','user_id'];
}
