<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ImageQualityCheckService
{
    private string $baseUrl;

    public function __construct(
        private readonly UploadStorageService $uploadStorage,
    ) {
        $this->baseUrl = rtrim((string) config('services.image_quality_check.url'), '/');
    }

    /**
     * Submit locally stored images to the IQC microservice for asynchronous evaluation.
     *
     * @param  array<int, array{stored_path:string, original_filename:string}>  $images
     */
    public function submitStoredImages(string $externalJobId, array $images): array
    {
        $request = Http::timeout(60)
            ->asMultipart();

        $manifest = [];

        foreach ($images as $image) {
            $absolutePath = $this->uploadStorage->absolutePath($image['stored_path']);

            if (!file_exists($absolutePath)) {
                throw new RuntimeException("Stored image not found: {$absolutePath}");
            }

            $manifest[] = [
                'stored_path' => $image['stored_path'],
                'original_filename' => $image['original_filename'],
            ];

            $request = $request->attach(
                'images',
                fopen($absolutePath, 'rb'),
                $image['original_filename']
            );
        }

        $response = $request->post("{$this->baseUrl}/api/v1/jobs/upload", [
            'job_id' => $externalJobId,
            'image_manifest' => json_encode($manifest),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException(
                "IQC submission failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }
}
