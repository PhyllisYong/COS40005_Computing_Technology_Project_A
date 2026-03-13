<?php

namespace App\Http\Controllers;

use App\Models\ExtractJob;
use App\Services\LeafMachine2Service;
use App\Services\ResultProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitisationResultController extends Controller
{
    public function __construct(
        private readonly ResultProcessingService $processing,
        private readonly LeafMachine2Service $lm2,
    ) {
    }

    /**
     * Preview endpoint — parse and return CSV rows as JSON without saving.
     * The consumer (frontend) uses this to display a result table to the user.
     */
    public function show(ExtractJob $job): JsonResponse
    {
        abort_unless($job->canImportResults(), 422, 'Job has not completed successfully.');

        $records = $this->processing->preview($job);

        return response()->json($records);
    }

    /**
     * Import endpoint — parse CSV rows and save them into plant_measurements.
     * Idempotent: calling this multiple times replaces the existing records.
     */
    public function store(ExtractJob $job): JsonResponse
    {
        abort_unless($job->canImportResults(), 422, 'Job has not completed successfully.');

        $records         = $this->processing->preview($job);
        $alreadyImported = $job->hasImportedResults();
        $saved           = $this->processing->saveToDatabase($job, $records);

        return response()->json([
            'saved'            => $saved,
            'already_imported' => $alreadyImported,
        ]);
    }

    /**
     * Download proxy — stream a result file from the microservice to the browser.
     * Filename is validated against the job's known result_files before proxying.
     */
    public function download(ExtractJob $job, string $filename): StreamedResponse
    {
        // Validate the requested filename against the job's known result files
        $allowed = $job->result_files ?? [];
        abort_unless(in_array($filename, $allowed, true), 404, 'Result file not found.');

        $response = $this->lm2->downloadResultFile($job->external_job_id, $filename);

        return response()->streamDownload(
            fn() => print($response->body()),
            $filename,
            ['Content-Type' => $response->header('Content-Type') ?: 'application/octet-stream']
        );
    }
}
