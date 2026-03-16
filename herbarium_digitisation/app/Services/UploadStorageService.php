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
        $configuredPath = trim((string) config('services.image_quality_check.uploads_dir', ''));
        if ($configuredPath === '') {
            $configuredPath = base_path('uploads');
        }

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
    public function storeUploadedFile(UploadedFile $file, string $jobId, ?string $runName = null): array
    {
        $directorySegment = $this->jobDirectorySegment($jobId, $runName);
        $jobDirectory = $this->jobDirectoryPath($directorySegment);
        File::ensureDirectoryExists($jobDirectory);

        $storedFilename = (string) Str::uuid() . '_' . $this->sanitizeOriginalFilename($file->getClientOriginalName());
        $file->move($jobDirectory, $storedFilename);

        $relativePath = $directorySegment . '/' . $storedFilename;

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

    private function jobDirectoryPath(string $directorySegment): string
    {
        return $this->normalizeAbsolutePath($this->uploadsRoot . '/' . trim($directorySegment));
    }

    private function jobDirectorySegment(string $jobId, ?string $runName): string
    {
        $baseName = Str::slug((string) $runName, '_');
        $baseName = $baseName !== '' ? Str::limit($baseName, 48, '') : 'run';

        return $baseName . '_' . trim($jobId);
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