<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UploadStorageService
{
    private string $uploadsRoot;

    public function __construct()
    {
        $configuredPath = (string) config('services.image_quality_check.uploads_dir', base_path('uploads'));
        $this->uploadsRoot = $this->normalizeAbsolutePath($configuredPath);
    }

    public function rootPath(): string
    {
        return $this->uploadsRoot;
    }

    /**
     * Persist an uploaded file in uploads/{jobId}/ and return the stored relative path.
     *
     * @return array{stored_path:string,absolute_path:string,stored_filename:string}
     */
    public function storeUploadedFile(UploadedFile $file, string $jobId): array
    {
        $jobDirectory = $this->jobDirectoryPath($jobId);
        File::ensureDirectoryExists($jobDirectory);

        $storedFilename = (string) Str::uuid() . '_' . $this->sanitizeOriginalFilename($file->getClientOriginalName());
        $file->move($jobDirectory, $storedFilename);

        $relativePath = $jobId . '/' . $storedFilename;

        return [
            'stored_path' => $relativePath,
            'absolute_path' => $this->absolutePath($relativePath),
            'stored_filename' => $storedFilename,
        ];
    }

    public function absolutePath(string $storedPath): string
    {
        $normalized = str_replace('\\', '/', trim($storedPath));
        $normalized = ltrim($normalized, '/');

        // Backward compatibility for earlier rows that stored the "uploads/" prefix.
        if (str_starts_with($normalized, 'uploads/')) {
            $normalized = substr($normalized, strlen('uploads/'));
        }

        return $this->normalizeAbsolutePath($this->uploadsRoot . '/' . $normalized);
    }

    public function exists(string $storedPath): bool
    {
        return File::exists($this->absolutePath($storedPath));
    }

    private function jobDirectoryPath(string $jobId): string
    {
        return $this->normalizeAbsolutePath($this->uploadsRoot . '/' . trim($jobId));
    }

    private function sanitizeOriginalFilename(string $filename): string
    {
        $basename = basename($filename);
        $sanitized = preg_replace('/[^A-Za-z0-9._ -]/', '_', $basename) ?? $basename;
        $sanitized = str_replace(' ', '_', $sanitized);

        return $sanitized !== '' ? $sanitized : 'upload.bin';
    }

    private function normalizeAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        if (!preg_match('/^[A-Za-z]:\//', $path) && !str_starts_with($path, '/')) {
            $path = base_path($path);
        }

        return rtrim(str_replace('\\', '/', $path), '/');
    }
}