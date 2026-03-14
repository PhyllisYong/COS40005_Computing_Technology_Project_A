<?php

namespace Database\Seeders;

use App\Models\Inferences;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InferencesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Inferences::factory() ->count(5)->create();
    }
}
