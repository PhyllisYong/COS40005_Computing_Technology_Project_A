<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PlantSpecies;
class PlantSpeciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        
        $file = base_path("database/data/species_label_plantclef2021.csv");

        $open = fopen($file, "r");

        // Skip the header row 
        fgetcsv($open);

        while (($data = fgetcsv($open, 1000, ",")) !== FALSE) {
            PlantSpecies::create([
                'scientific_name' => $data[2], 
                'sample_image_path' => $data[1] , # point to the class_id
            ]);
        }

        fclose($open);
    
    }
}
