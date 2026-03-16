<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institutions extends Model
{
    /** @use HasFactory<\Database\Factories\InstitutionsFactory> */
    use HasFactory;

    protected $primaryKey = 'institution_id';
    protected $fillable = [
        'institution_name',
        'address',
    ];
}
