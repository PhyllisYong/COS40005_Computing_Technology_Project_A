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
        Schema::create('extract_jobs', function (Blueprint $table) {
            $table->id("job_id");
            $table->string("job_name", length:255)->nullable();
            $table->enum("success", ["SUCCESS", "FAILED"]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extract_jobs');
    }
};
