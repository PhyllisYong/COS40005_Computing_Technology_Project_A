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
        Schema::create('inferences', function (Blueprint $table) {
            $table->id("inference_id");
            $table->string("predicted_label", length:255)->nullable();
            $table->string("actual_label", length:255)->nullable();
            $table->double("confidence_score")->nullable();
            $table->foreignId('user_id')
                  ->references("user_id")
                  ->on("users")
                  ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inferences');
    }
};
