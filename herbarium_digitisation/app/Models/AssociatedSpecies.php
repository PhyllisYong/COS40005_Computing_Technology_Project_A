<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssociatedSpecies extends Model
{
    /** @use HasFactory<\Database\Factories\AssociatedSpeciesFactory> */
    use HasFactory;

    protected $table ='associated_species';

    protected $fillable =['sheet_id','plant_id'];
}
