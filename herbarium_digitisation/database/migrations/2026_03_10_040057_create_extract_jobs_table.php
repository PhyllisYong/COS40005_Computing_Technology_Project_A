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
            $table->id('job_id');
            $table->string('job_name', 255)->nullable();

            // UUID assigned by the microservice to identify this job
            $table->string('external_job_id', 128)->nullable()->unique();

            // Lifecycle status: pending | accepted | running | completed | failed
            $table->string('status', 20)->default('pending');
            $table->string('progress_step')->nullable();

            // Nullable: set to SUCCESS/FAILED only on terminal transition
            $table->enum('success', ['SUCCESS', 'FAILED'])->nullable();

            $table->json('result_files')->nullable();
            $table->string('output_path')->nullable();
            $table->text('error_message')->nullable();
            $table->json('config_overrides')->nullable();
            $table->json('callback_payload')->nullable();

            // Owner (nullable until auth is re-enabled)
            $table->foreignId('user_id')->nullable()->constrained('users', 'user_id')->nullOnDelete();

            // Lifecycle timestamps
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('results_imported_at')->nullable();

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
