<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PlantSpecies;
use App\Models\Institutions;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HerbariumSheets>
 */
class HerbariumSheetsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plant = PlantSpecies::inRandomOrder()->first();

        return [
            'plant_id' => $plant?->plant_id,
            'scientific_name' => $plant?->scientific_name,

            'institution_id' => Institutions::inRandomOrder()->first()?->institution_id,

            'family_name' => $this->faker->word(),

            'collector_name' => $this->faker->name(),

            'locality' => $this->faker->city(),
            'country' => $this->faker->country(),

            'habitat' => $this->faker->randomElement([
                'Tropical rainforest',
                'Lowland forest',
                'Riverbank',
                'Mountain forest',
                'Swamp forest'
            ]),

            'plant_description' => $this->faker->sentence(),

            'sheet_image_path' => 'herbarium/' . $this->faker->uuid() . '.jpg',
        ];
    }
}
