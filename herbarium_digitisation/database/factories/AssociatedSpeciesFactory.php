<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\HerbariumSheets;
use App\Models\PlantSpecies;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AssociatedSpecies>
 */
class AssociatedSpeciesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sheet_id' => HerbariumSheets::inRandomOrder()->first()?->sheet_id,
            'plant_id' => PlantSpecies::inRandomOrder()->first()?->plant_id,
        ];
    }
}
