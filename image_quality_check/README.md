# Image Quality Check Microservice

This microservice evaluates uploaded images before downstream processing.

## What it does

- Validates uploaded image files.
- Applies EXIF orientation normalization before quality metrics.
- Runs soft/hard resolution checks.
- Runs focus/blur detection using Laplacian variance.
- Runs BRISQUE IQA scoring.
- Returns per-image accept/reject decisions via callback.

## Pipeline

1. File sanity checks (decode + size cap).
2. EXIF orientation normalization (`ImageOps.exif_transpose`) before IQA metrics.
3. Soft and hard resolution checks.
4. Blur/focus detection (Laplacian variance).
5. BRISQUE score calculation.
6. Per-image decision payload returned via callback.

## API

- `POST /api/v1/jobs/upload`
  - Multipart form fields:
    - `job_id` (string)
    - `image_manifest` (JSON array of `{stored_path, original_filename}`)
    - `images` files
  - Header: `X-API-Key`
  - Returns immediate accepted response; processing runs in background task.

- Callback target (configured in env):
  - `POST {IQC_CALLBACK_URL}/{job_id}/status`
  - Bearer token: `IQC_CALLBACK_TOKEN`

## Required Env Vars

- `IQC_API_KEY`
- `IQC_CALLBACK_URL` (example: `http://laravel-app.test/api/internal/iqc/jobs`)
- `IQC_CALLBACK_TOKEN`

Optional thresholds:

- `IQC_SOFT_MIN_WIDTH` (default `1800`)
- `IQC_SOFT_MIN_HEIGHT` (default `1200`)
- `IQC_HARD_MIN_WIDTH` (default `1000`)
- `IQC_HARD_MIN_HEIGHT` (default `700`)
- `IQC_MAX_FILE_BYTES` (default `52428800`)
- `IQC_LAPLACIAN_THRESHOLD` (default `120`)
- `IQC_BRISQUE_THRESHOLD` (default `40`)

Optional callback reliability settings:

- `IQC_CALLBACK_TIMEOUT_SECONDS` (default `30`)
- `IQC_CALLBACK_MAX_RETRIES` (default `3`)
- `IQC_CALLBACK_RETRY_DELAY_SECONDS` (default `1.5`)

## Run locally (Windows PowerShell)

1. Move into this directory:

```powershell
cd image_quality_check
```

2. Create and activate venv:

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
```

3. Install dependencies:

```powershell
python -m pip install --upgrade pip
pip install -r requirements.txt
```

4. Set required environment values:

```powershell
$env:IQC_API_KEY = "replace-with-iqc-api-key"
$env:IQC_CALLBACK_URL = "http://127.0.0.1:8000/api/internal/iqc/jobs"
$env:IQC_CALLBACK_TOKEN = "replace-with-iqc-callback-token"
```

5. Start the server:

```powershell
uvicorn app.main:app --host 0.0.0.0 --port 9001 --reload
```

6. Health check:

```powershell
Invoke-RestMethod -Method Get -Uri "http://127.0.0.1:9001/health"
```

## Laravel integration checklist

In `herbarium_digitisation/.env`:

```env
IQC_SERVICE_URL=http://127.0.0.1:9001
IQC_API_KEY=replace-with-iqc-api-key
IQC_CALLBACK_TOKEN=replace-with-iqc-callback-token
```

Run Laravel queue worker (required because IQC dispatch is queued):

```powershell
cd herbarium_digitisation
php artisan queue:work --queue=iqc,default
```

Run DB migrations if not applied yet:

```powershell
php artisan migrate
```

## Quick manual upload test (optional)

```powershell
$headers = @{ "X-API-Key" = "replace-with-iqc-api-key" }
$form = @{
  job_id = "test-job-001"
  image_manifest = "[{\"stored_path\":\"uploads/test/test.jpg\",\"original_filename\":\"test.jpg\"}]"
  images = Get-Item ".\sample.jpg"
}
Invoke-RestMethod -Method Post -Uri "http://127.0.0.1:9001/api/v1/jobs/upload" -Headers $headers -Form $form
```

Expected behavior: the endpoint returns immediately with accepted status, then posts a callback with per-image decisions.
