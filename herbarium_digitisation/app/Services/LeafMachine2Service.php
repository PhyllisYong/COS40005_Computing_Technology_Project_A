<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LeafMachine2Service
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(
        private readonly UploadStorageService $uploadStorage,
    ) {
        $this->baseUrl = rtrim(config('services.leafmachine2.url'), '/');
        $this->apiKey  = config('services.leafmachine2.api_key');
    }

    /**
     * Submit a digitisation job to the microservice via multipart upload.
     *
     * @param  string  $jobId          UUID that will identify this job externally
     * @param  string  $runName        Human-readable batch name
     * @param  array   $files          Uploaded file instances (Illuminate\Http\UploadedFile[])
     * @param  array   $configOverrides Optional LeafMachine2 config overrides (admin only)
     * @return array                   Decoded JSON response body from the microservice
     *
     * @throws RuntimeException when the microservice returns a non-2xx status
     */
    public function submitJob(
        string $jobId,
        string $runName,
        array $files,
        array $configOverrides = []
    ): array {
        $request = Http::timeout(30)->asMultipart();

        if ($this->apiKey !== '') {
            $request = $request->withHeader('X-API-Key', $this->apiKey);
        }

        // Attach each uploaded file as a separate 'files' part
        foreach ($files as $file) {
            $request = $request->attach(
                'files',
                fopen($file->getRealPath(), 'rb'),
                $file->getClientOriginalName()
            );
        }

        $payload = ['job_id' => $jobId, 'run_name' => $runName];

        if (!empty($configOverrides)) {
            $payload['config_overrides'] = json_encode($configOverrides);
        }

        $response = $request->post("{$this->baseUrl}/api/v1/jobs/upload", $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                "LeafMachine2 job submission failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Submit images that are already stored on disk.
     *
     * @param  array<int, array{stored_path:string, original_filename:string}> $images
     */
    public function submitStoredImages(
        string $jobId,
        string $runName,
        array $images,
        array $configOverrides = []
    ): array {
        $request = Http::timeout(30)->asMultipart();

        if ($this->apiKey !== '') {
            $request = $request->withHeader('X-API-Key', $this->apiKey);
        }

        $attachedCount = 0;

        foreach ($images as $image) {
            $absolutePath = $this->uploadStorage->absolutePath($image['stored_path']);

            if (!file_exists($absolutePath)) {
                throw new RuntimeException("LeafMachine2 submission file not found: {$absolutePath}");
            }

            $request = $request->attach(
                'files',
                fopen($absolutePath, 'rb'),
                $image['original_filename']
            );

            $attachedCount++;
        }

        if ($attachedCount === 0) {
            throw new RuntimeException('LeafMachine2 submission aborted: no files were attached.');
        }

        $payload = ['job_id' => $jobId, 'run_name' => $runName];

        if (!empty($configOverrides)) {
            $payload['config_overrides'] = json_encode($configOverrides);
        }

        $response = $request->post("{$this->baseUrl}/api/v1/jobs/upload", $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                "LeafMachine2 stored-image submission failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json();
    }

    /**
     * Download a result file from the microservice and return the raw response
     * so the caller can stream it directly to the browser.
     *
     * @param  string  $jobId     The external job identifier
     * @param  string  $filename  Filename as reported in result_files (e.g. "Measurements__batch_0.csv")
     * @return Response           Raw Illuminate HTTP response for streaming
     *
     * @throws RuntimeException when the file cannot be retrieved
     */
    public function downloadResultFile(string $jobId, string $filename): Response
    {
        $encodedFilename = rawurlencode($filename);

        $request = Http::timeout(120);

        if ($this->apiKey !== '') {
            $request = $request->withHeader('X-API-Key', $this->apiKey);
        }

        $response = $request->get("{$this->baseUrl}/api/v1/jobs/{$jobId}/results/{$encodedFilename}");

        if (!$response->successful()) {
            throw new RuntimeException(
                "Failed to download result file '{$filename}' for job '{$jobId}' [{$response->status()}]"
            );
        }

        return $response;
    }
}
