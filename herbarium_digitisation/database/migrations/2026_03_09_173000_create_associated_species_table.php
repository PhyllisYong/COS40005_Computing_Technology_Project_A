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
        Schema::create('associated_species', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_id')
                  ->references("sheet_id")
                  ->on("herbarium_sheets")
                  ->cascadeOnDelete();
            $table->foreignId('plant_id')
                  ->references('plant_id')
                  ->on('plant_species')
                  ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('associated_species');
    }
};
