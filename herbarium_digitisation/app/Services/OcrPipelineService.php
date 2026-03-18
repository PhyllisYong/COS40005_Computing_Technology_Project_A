<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OcrPipelineService
{
    private string $baseUrl;

    public function __construct(
        private readonly UploadStorageService $uploadStorage,
    ) {
        $this->baseUrl = rtrim((string) config('services.ocr_pipeline.url'), '/');
    }

    /**
     * Submit accepted, already-stored images for OCR processing.
     *
     * @param  array<int, array{stored_path:string, original_filename:string}>  $images
     */
    public function submitStoredImages(string $jobId, array $images): array
    {
        $request = Http::timeout(60)
            ->asMultipart();

        $manifest = [];
        $attachedCount = 0;

        foreach ($images as $image) {
            $absolutePath = $this->uploadStorage->absolutePath($image['stored_path']);

            if (!file_exists($absolutePath)) {
                throw new RuntimeException("OCR submission file not found: {$absolutePath}");
            }

            $request = $request->attach(
                'images',
                fopen($absolutePath, 'rb'),
                $image['original_filename']
            );

            $manifest[] = [
                'stored_path' => $image['stored_path'],
                'original_filename' => $image['original_filename'],
            ];

            $attachedCount++;
        }

        if ($attachedCount === 0) {
            throw new RuntimeException('OCR submission aborted: no files were attached.');
        }

        $payload = [
            'job_id' => $jobId,
            'image_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
        ];

        $response = $request->post("{$this->baseUrl}/api/v1/jobs/upload", $payload);

        if (!$response->successful()) {
            throw new RuntimeException(
                "OCR job submission failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
