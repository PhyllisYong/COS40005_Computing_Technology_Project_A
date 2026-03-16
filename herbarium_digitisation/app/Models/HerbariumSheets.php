<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HerbariumSheets extends Model
{
    /** @use HasFactory<\Database\Factories\HerbariumSheetsFactory> */
    use HasFactory;

    protected $table="herbarium_sheets";
    
    protected $primaryKey = 'sheet_id';
    protected $fillable =['family_name','scientific_name','collector_name','locality','country','habitat','plant_description','sheet_image_path','plant_id','institution_id'];
}
