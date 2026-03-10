<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('herbarium_sheets', function (Blueprint $table) {
            $table->id('sheet_id');
            $table->string('family_name', length:255)->nullable();
            $table->string("scientific_name", length:255)->nullable();
            $table->string("collector_name", length:255)->nullable();
            
            $table->string("locality", length:255)->nullable();
            $table->string("country", length:255)->nullable();
            $table->string("habitat", length:255)->nullable();
            $table->string("plant_description", length:255)->nullable();
            $table->string("sheet_image_path", length:255)->nullable();

            $table->foreignId('plant_id')
                  ->references('plant_id')
                  ->on('plant_species')
                  ->cascadeOnDelete();

            $table->foreignId('institution_id')
                  ->references('institution_id')
                  ->on('institutions')
                  ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('herbarium_sheets');
    }
};
