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
            $table->string('ocr_status', 20)->nullable()->after('iqc_status');
            $table->string('ocr_progress_step')->nullable()->after('progress_step');
            $table->text('ocr_error_message')->nullable()->after('error_message');
            $table->timestamp('ocr_started_at')->nullable()->after('iqc_failed_at');
            $table->timestamp('ocr_completed_at')->nullable()->after('ocr_started_at');
            $table->timestamp('ocr_failed_at')->nullable()->after('ocr_completed_at');
        });

        Schema::table('extract_job_images', function (Blueprint $table) {
            $table->string('ocr_status', 20)->nullable()->after('accepted_for_submission');
            $table->json('ocr_payload')->nullable()->after('ocr_status');
            $table->longText('ocr_text')->nullable()->after('ocr_payload');
            $table->json('ocr_llm_verified')->nullable()->after('ocr_text');
            $table->timestamp('ocr_processed_at')->nullable()->after('ocr_llm_verified');

            $table->index(['job_id', 'ocr_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('extract_job_images', function (Blueprint $table) {
            $table->dropIndex(['job_id', 'ocr_status']);
            $table->dropColumn([
                'ocr_status',
                'ocr_payload',
                'ocr_text',
                'ocr_llm_verified',
                'ocr_processed_at',
            ]);
        });

        Schema::table('extract_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'ocr_status',
                'ocr_progress_step',
                'ocr_error_message',
                'ocr_started_at',
                'ocr_completed_at',
                'ocr_failed_at',
            ]);
        });
    }
};
