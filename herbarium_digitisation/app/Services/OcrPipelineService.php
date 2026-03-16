<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OcrPipelineService
{
    private string $baseUrl;
    private string $apiKey;
    private string $submitPath;

    public function __construct(
        private readonly UploadStorageService $uploadStorage,
    ) {
        $this->baseUrl = rtrim((string) config('services.ocr_pipeline.url'), '/');
        $this->apiKey = (string) config('services.ocr_pipeline.api_key');
        $this->submitPath = (string) config('services.ocr_pipeline.submit_path', '/api/v1/jobs/upload');
    }

    /**
     * Submit accepted images to the OCR microservice as a single batch request.
     *
     * @param  array<int, array{stored_path:string, original_filename:string}>  $images
     */
    public function submitStoredImages(string $externalJobId, string $runName, array $images): array
    {
        $request = Http::timeout(60)->asMultipart();

        if ($this->apiKey !== '') {
            $request = $request->withHeader('X-API-Key', $this->apiKey);
        }

        $attachedCount = 0;

        foreach ($images as $image) {
            $absolutePath = $this->uploadStorage->absolutePath($image['stored_path']);

            if (!file_exists($absolutePath)) {
                throw new RuntimeException("OCR submission file not found: {$absolutePath}");
            }

            $request = $request->attach(
                'files',
                fopen($absolutePath, 'rb'),
                $image['original_filename']
            );

            $attachedCount++;
        }

        if ($attachedCount === 0) {
            throw new RuntimeException('OCR submission aborted: no files were attached.');
        }

        $response = $request->post("{$this->baseUrl}{$this->submitPath}", [
            'job_id' => $externalJobId,
            'run_name' => $runName,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                "OCR pipeline submission failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
