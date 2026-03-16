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
        Schema::table('extract_jobs', function (Blueprint $table) {
            $table->string('iqc_status', 20)->nullable()->after('status');
            $table->unsignedInteger('accepted_images_count')->default(0)->after('success');
            $table->unsignedInteger('rejected_images_count')->default(0)->after('accepted_images_count');
            $table->json('iqc_summary')->nullable()->after('callback_payload');
            $table->timestamp('iqc_started_at')->nullable()->after('failed_at');
            $table->timestamp('iqc_completed_at')->nullable()->after('iqc_started_at');
            $table->timestamp('iqc_failed_at')->nullable()->after('iqc_completed_at');
        });

        Schema::create('extract_job_images', function (Blueprint $table) {
            $table->id('image_id');
            $table->foreignId('job_id')
                ->references('job_id')
                ->on('extract_jobs')
                ->cascadeOnDelete();

            $table->string('original_filename', 255);
            $table->string('stored_path', 512);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            $table->unsignedSmallInteger('exif_orientation')->nullable();
            $table->smallInteger('normalized_rotation')->default(0);

            $table->string('iqc_status', 20)->default('pending');
            $table->string('iqc_decision', 20)->nullable();
            $table->json('iqc_reasons')->nullable();
            $table->json('iqc_metrics')->nullable();
            $table->json('iqc_payload')->nullable();
            $table->timestamp('iqc_checked_at')->nullable();
            $table->boolean('accepted_for_submission')->nullable();

            $table->timestamps();

            $table->index(['job_id', 'iqc_status']);
            $table->index(['job_id', 'accepted_for_submission']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extract_job_images');

        Schema::table('extract_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'iqc_status',
                'accepted_images_count',
                'rejected_images_count',
                'iqc_summary',
                'iqc_started_at',
                'iqc_completed_at',
                'iqc_failed_at',
            ]);
        });
    }
};
