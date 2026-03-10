<?php

namespace App\Services;

use App\Models\ExtractJob;
use App\Models\PlantMeasurement;
use Illuminate\Support\Carbon;
use RuntimeException;

class ResultProcessingService
{
    public function __construct(private readonly LeafMachine2Service $lm2)
    {
    }

    /**
     * Preview parsed CSV rows without persisting them.
     *
     * @return array<int, array{record_index: int, data: array<string, string>}>
     */
    public function preview(ExtractJob $job): array
    {
        $csv = $this->downloadCsvContents($job);
        return $this->parseCsv($csv);
    }

    /**
     * Parse CSV rows and upsert them into plant_measurements.
     * Existing measurements for the job are replaced to keep the operation idempotent.
     *
     * @return int number of rows written
     */
    public function saveToDatabase(ExtractJob $job, array $records): int
    {
        if (empty($records)) {
            return 0;
        }

        $now  = Carbon::now()->toDateTimeString();
        $rows = array_map(function ($r) use ($job, $now) {
            $mapped = $this->mapRowToMeasurement($r['data']);
            return array_merge($mapped, [
                'job_id'     => $job->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, $records);

        // Delete + re-insert to keep idempotent without needing a unique index
        PlantMeasurement::where('job_id', $job->getKey())->delete();
        foreach (array_chunk($rows, 500) as $chunk) {
            PlantMeasurement::insert($chunk);
        }

        $job->results_imported_at = now();
        $job->saveQuietly();

        return count($records);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function downloadCsvContents(ExtractJob $job): string
    {
        $filename = $this->selectPrimaryCsvFilename($job->result_files ?? []);
        $response = $this->lm2->downloadResultFile($job->external_job_id, $filename);
        return $response->body();
    }

    /**
     * Determine the primary CSV filename from the job's result_files list.
     *
     * Priority (based on LeafMachine2 output naming convention
     * "Measurements__{run_name}__{batch_N}.csv"):
     *   1. First name matching /^Measurements__.*\.csv$/i
     *   2. First name ending in _measurements.csv
     *   3. First .csv name, alphabetically sorted
     *   4. None found → RuntimeException
     */
    private function selectPrimaryCsvFilename(array $resultFiles): string
    {
        $csvFiles = array_values(array_filter($resultFiles, fn($f) => str_ends_with(strtolower($f), '.csv')));

        if (empty($csvFiles)) {
            throw new RuntimeException("No CSV result file found in result_files.");
        }

        // Priority 1 — canonical LeafMachine2 measurement output
        foreach ($csvFiles as $f) {
            if (preg_match('/^Measurements__.*\.csv$/i', $f)) {
                return $f;
            }
        }

        // Priority 2 — alternate naming convention
        foreach ($csvFiles as $f) {
            if (str_ends_with(strtolower($f), '_measurements.csv')) {
                return $f;
            }
        }

        // Priority 3 — alphabetically first CSV as last resort
        sort($csvFiles);
        return $csvFiles[0];
    }

    /**
     * Parse a CSV string into an array of indexed row records.
     *
     * @return array<int, array{record_index: int, data: array<string, string>}>
     */
    private function parseCsv(string $csvContents): array
    {
        $lines = array_filter(explode("\n", str_replace("\r\n", "\n", trim($csvContents))));
        $lines = array_values($lines);

        if (empty($lines)) {
            return [];
        }

        $headers = str_getcsv(array_shift($lines));
        $records = [];

        foreach ($lines as $index => $line) {
            $values = str_getcsv($line);

            // Pad or trim to match header count
            $values = array_slice(
                array_pad($values, count($headers), null),
                0,
                count($headers)
            );

            $records[] = [
                'record_index' => $index,
                'data'         => array_combine($headers, $values),
            ];
        }

        return $records;
    }

    /**
     * Map a parsed CSV row (associative, original headers) to the
     * plant_measurements column set. Keys are normalised to lowercase_snake_case.
     */
    private function mapRowToMeasurement(array $row): array
    {
        // Normalise all keys to lowercase snake_case for flexible header matching
        $n = [];
        foreach ($row as $k => $v) {
            $n[strtolower(preg_replace('/[\s\-]+/', '_', trim($k)))] = $v;
        }

        $num = fn($key) => isset($n[$key]) && is_numeric($n[$key]) ? (float) $n[$key] : null;

        return [
            'component_name'      => $n['component_name']      ?? $n['component']  ?? null,
            'component_type'      => $n['component_type']      ?? $n['type']       ?? null,
            'perimeter'           => $num('perimeter'),
            'area'                => $num('area'),
            'bbox_min_long_side'  => $num('bbox_min_long_side')  ?? $num('bbox_long_side'),
            'bbox_min_short_side' => $num('bbox_min_short_side') ?? $num('bbox_short_side'),
            'units'               => $num('units'),
            'conversion_factor'   => $num('conversion_factor'),
            'aspect_ratio'        => isset($n['aspect_ratio']) ? (string) $n['aspect_ratio'] : null,
        ];
    }
}
