<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantSpecies extends Model
{
    /** @use HasFactory<\Database\Factories\PlantSpeciesFactory> */
    use HasFactory;

    protected $table ='plant_species';
    protected $primaryKey = 'plant_id';
    protected $fillable= ['scientific_name','sample_image_path'];
}
