<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\PlantSpecies;
use App\Models\User;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inferences>
 */
class InferencesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */

    public function definition(): array
    {
       
         return [
            'predicted_label' => PlantSpecies::inRandomOrder()->first()?->scientific_name,

            'confidence_score' => $this->faker->randomFloat(4, 0.50, 0.99),

            'user_id' => User::inRandomOrder()->first()?->user_id,
        ];
    }
}
