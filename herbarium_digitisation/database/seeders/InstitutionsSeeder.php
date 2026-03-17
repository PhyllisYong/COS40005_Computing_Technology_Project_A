<?php

namespace Database\Seeders;

use App\Models\Institutions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class InstitutionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Institutions::factory()->count(5)->create();
    }
}
