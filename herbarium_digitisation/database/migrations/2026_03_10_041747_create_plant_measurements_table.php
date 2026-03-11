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
        Schema::create('plant_measurements', function (Blueprint $table) {
            $table->id("measurement_id");
            $table->string("component_name", length:255)->nullable();
            $table->string("component_type", length:255)->nullable();
            $table->double("perimeter")->nullable();
            $table->double("area")->nullable();
            $table->double("bbox_min_long_side")->nullable();
            $table->double("bbox_min_short_side")->nullable();
            $table->double("units")->nullable();
            $table->double("conversion_factor")->nullable();
            $table->string("aspect_ratio", length: 20)->nullable();
            $table->foreignId("job_id")
                  ->references("job_id")
                  ->on("extract_jobs")
                  ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plant_measurements');
    }
};
