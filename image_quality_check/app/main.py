from __future__ import annotations

import asyncio
import io
import json
import logging
import os
import time
from dataclasses import dataclass
from typing import Any

import cv2
import httpx
import numpy as np
import inspect
from fastapi import BackgroundTasks, FastAPI, File, Form, HTTPException, UploadFile
from PIL import Image, ImageOps
from dotenv import load_dotenv

load_dotenv()

logging.basicConfig(
    level=logging.INFO,
    format="[IQC Service] %(asctime)s %(levelname)s %(message)s",
)
logger = logging.getLogger("iqc-service")

# Compatibility shim for image-quality using deprecated skimage rescale(multichannel=...).
try:
    import scipy

    if not hasattr(scipy, "ndarray"):
        scipy.ndarray = np.ndarray

    from skimage import transform as sk_transform
    from skimage import color as sk_color

    if "multichannel" not in inspect.signature(sk_transform.rescale).parameters:
        _original_rescale = sk_transform.rescale

        def _rescale_compat(*args: Any, **kwargs: Any) -> Any:
            if "multichannel" in kwargs and "channel_axis" not in kwargs:
                multichannel = bool(kwargs.pop("multichannel"))
                kwargs["channel_axis"] = -1 if multichannel else None
            return _original_rescale(*args, **kwargs)

        sk_transform.rescale = _rescale_compat

    _original_rgb2gray = sk_color.rgb2gray

    def _rgb2gray_compat(image: Any, *args: Any, **kwargs: Any) -> Any:
        array = np.asarray(image)
        if array.ndim == 2:
            return array
        return _original_rgb2gray(image, *args, **kwargs)

    sk_color.rgb2gray = _rgb2gray_compat
except Exception:
    pass

try:
    from imquality import brisque
except Exception:  # pragma: no cover - fallback path when BRISQUE package unavailable
    brisque = None

app = FastAPI(title="Image Quality Check Service", version="0.1.0")

EXIF_ORIENTATION_TAG = 274
ROTATION_BY_EXIF = {1: 0, 3: 180, 6: 270, 8: 90}

CALLBACK_URL = os.getenv("IQC_CALLBACK_URL", "")
CALLBACK_TIMEOUT_SECONDS = float(os.getenv("IQC_CALLBACK_TIMEOUT_SECONDS", "30"))
CALLBACK_MAX_RETRIES = int(os.getenv("IQC_CALLBACK_MAX_RETRIES", "3"))
CALLBACK_RETRY_DELAY_SECONDS = float(os.getenv("IQC_CALLBACK_RETRY_DELAY_SECONDS", "1.5"))

SOFT_MIN_WIDTH = int(os.getenv("IQC_SOFT_MIN_WIDTH", "1800"))
SOFT_MIN_HEIGHT = int(os.getenv("IQC_SOFT_MIN_HEIGHT", "1200"))
HARD_MIN_WIDTH = int(os.getenv("IQC_HARD_MIN_WIDTH", "1000"))
HARD_MIN_HEIGHT = int(os.getenv("IQC_HARD_MIN_HEIGHT", "700"))
MAX_FILE_BYTES = int(os.getenv("IQC_MAX_FILE_BYTES", str(50 * 1024 * 1024)))
BLUR_THRESHOLD = float(os.getenv("IQC_LAPLACIAN_THRESHOLD", "120"))
BRISQUE_THRESHOLD = float(os.getenv("IQC_BRISQUE_THRESHOLD", "40"))
BRISQUE_MAX_DIM = int(os.getenv("IQC_BRISQUE_MAX_DIM", "1024"))
BRISQUE_SKIP_ON_REJECT = os.getenv("IQC_BRISQUE_SKIP_ON_REJECT", "false").strip().lower() in {"1", "true", "yes", "on"}


@dataclass
class InMemoryImage:
    filename: str
    stored_path: str
    content: bytes


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/api/v1/jobs/upload")
async def upload_job(
    background_tasks: BackgroundTasks,
    job_id: str = Form(...),
    image_manifest: str | None = Form(default=None),
    images: list[UploadFile] = File(...),
) -> dict[str, Any]:
    manifest = _parse_manifest(image_manifest)

    buffered: list[InMemoryImage] = []
    for index, upload in enumerate(images):
        content = await upload.read()
        if len(content) > MAX_FILE_BYTES:
            raise HTTPException(status_code=422, detail=f"File '{upload.filename}' exceeds maximum size")

        mapped = manifest[index] if index < len(manifest) else {}
        stored_path = str(mapped.get("stored_path") or upload.filename or f"image-{index}")
        buffered.append(
            InMemoryImage(
                filename=str(upload.filename or f"image-{index}"),
                stored_path=stored_path,
                content=content,
            )
        )

    background_tasks.add_task(process_and_callback, job_id, buffered)

    logger.info(
        "Accepted IQC job_id=%s with %s image(s). Processing queued.",
        job_id,
        len(buffered),
    )

    return {
        "job_id": job_id,
        "status": "accepted",
        "queued_images": len(buffered),
    }


def _parse_manifest(raw_manifest: str | None) -> list[dict[str, Any]]:
    if not raw_manifest:
        return []

    try:
        data = json.loads(raw_manifest)
    except json.JSONDecodeError:
        return []

    if not isinstance(data, list):
        return []

    return [item for item in data if isinstance(item, dict)]


def _prepare_brisque_image(image: Image.Image) -> Image.Image:
    if BRISQUE_MAX_DIM <= 0:
        return image

    width, height = image.size
    longest_side = max(width, height)

    if longest_side <= BRISQUE_MAX_DIM:
        return image

    scale = BRISQUE_MAX_DIM / float(longest_side)
    resized = (
        max(1, int(round(width * scale))),
        max(1, int(round(height * scale))),
    )
    return image.resize(resized, Image.Resampling.LANCZOS)


async def process_and_callback(job_id: str, images: list[InMemoryImage]) -> None:
    logger.info("Processing IQC job_id=%s", job_id)

    decisions: list[dict[str, Any]] = []

    for image in images:
        decision = evaluate_image(image)
        decisions.append(decision)

        logger.info(
            "Image evaluated job_id=%s file=%s decision=%s",
            job_id,
            decision.get("filename"),
            decision.get("decision"),
        )

    accepted = sum(1 for d in decisions if d["decision"] == "accept")
    rejected = len(decisions) - accepted

    payload = {
        "status": "completed",
        "summary": {
            "accepted": accepted,
            "rejected": rejected,
            "total": len(decisions),
        },
        "images": decisions,
    }

    logger.info(
        "IQC summary job_id=%s accepted=%s rejected=%s total=%s",
        job_id,
        accepted,
        rejected,
        len(decisions),
    )

    await post_callback(job_id, payload)


def evaluate_image(image: InMemoryImage) -> dict[str, Any]:
    reasons: list[dict[str, str]] = []
    metrics: dict[str, Any] = {}
    decision = "accept"

    try:
        pil_image = Image.open(io.BytesIO(image.content))
    except Exception:
        return {
            "stored_path": image.stored_path,
            "filename": image.filename,
            "status": "completed",
            "decision": "reject",
            "reasons": [{"code": "decode_failed", "message": "Image cannot be decoded."}],
            "metrics": {},
            "exif_orientation": None,
            "normalized_rotation": 0,
        }

    exif_orientation = None
    exif = pil_image.getexif()
    if exif is not None:
        exif_orientation = exif.get(EXIF_ORIENTATION_TAG)

    normalized = ImageOps.exif_transpose(pil_image)
    normalized_rotation = ROTATION_BY_EXIF.get(int(exif_orientation), 0) if exif_orientation else 0

    width, height = normalized.size
    metrics["width"] = width
    metrics["height"] = height

    if width < HARD_MIN_WIDTH or height < HARD_MIN_HEIGHT:
        decision = "reject"
        reasons.append({
            "code": "resolution_hard_fail",
            "message": f"Image resolution too low ({width}x{height}).",
        })
    elif width < SOFT_MIN_WIDTH or height < SOFT_MIN_HEIGHT:
        reasons.append({
            "code": "resolution_soft_warning",
            "message": f"Image is below soft resolution target ({SOFT_MIN_WIDTH}x{SOFT_MIN_HEIGHT}).",
        })

    rgb_image = normalized.convert("RGB")
    gray = cv2.cvtColor(np.array(rgb_image), cv2.COLOR_RGB2GRAY)
    laplacian_var = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    metrics["laplacian_variance"] = laplacian_var

    if laplacian_var < BLUR_THRESHOLD:
        decision = "reject"
        reasons.append({
            "code": "blur_detected",
            "message": f"Focus score too low ({laplacian_var:.2f}).",
        })

    brisque_score = None
    if brisque is not None:
        if BRISQUE_SKIP_ON_REJECT and decision == "reject":
            metrics["brisque_skipped"] = True
        else:
            brisque_input = _prepare_brisque_image(rgb_image)
            metrics["brisque_input_width"] = brisque_input.size[0]
            metrics["brisque_input_height"] = brisque_input.size[1]
            try:
                start = time.perf_counter()
                brisque_score = float(brisque.score(brisque_input))
                metrics["brisque"] = brisque_score
                metrics["brisque_ms"] = round((time.perf_counter() - start) * 1000, 2)
            except Exception:
                reasons.append({
                    "code": "brisque_failed",
                    "message": "BRISQUE score could not be computed.",
                })
    else:
        reasons.append({
            "code": "brisque_unavailable",
            "message": "BRISQUE package unavailable in this deployment.",
        })

    if brisque_score is not None and brisque_score > BRISQUE_THRESHOLD:
        decision = "reject"
        reasons.append({
            "code": "brisque_threshold_fail",
            "message": f"BRISQUE score too high ({brisque_score:.2f}).",
        })

    return {
        "stored_path": image.stored_path,
        "filename": image.filename,
        "status": "completed",
        "decision": decision,
        "reasons": reasons,
        "metrics": metrics,
        "exif_orientation": exif_orientation,
        "normalized_rotation": normalized_rotation,
    }


async def post_callback(job_id: str, payload: dict[str, Any]) -> None:
    if not CALLBACK_URL:
        logger.warning("Callback skipped for job_id=%s because callback env is missing", job_id)
        return

    url = f"{CALLBACK_URL.rstrip('/')}/{job_id}/status"
    headers = {
        "Content-Type": "application/json",
    }

    attempts = max(1, CALLBACK_MAX_RETRIES)

    for attempt in range(1, attempts + 1):
        try:
            async with httpx.AsyncClient(timeout=CALLBACK_TIMEOUT_SECONDS) as client:
                response = await client.post(url, headers=headers, json=payload)
        except httpx.TimeoutException as exc:
            logger.warning(
                "Callback timeout for job_id=%s attempt=%s/%s timeout=%ss error=%s",
                job_id,
                attempt,
                attempts,
                CALLBACK_TIMEOUT_SECONDS,
                str(exc),
            )
        except httpx.RequestError as exc:
            logger.warning(
                "Callback request error for job_id=%s attempt=%s/%s error=%s",
                job_id,
                attempt,
                attempts,
                str(exc),
            )
        else:
            if response.is_success:
                logger.info("Callback delivered for job_id=%s with status_code=%s", job_id, response.status_code)
                return

            logger.error(
                "Callback failed for job_id=%s attempt=%s/%s status_code=%s body=%s",
                job_id,
                attempt,
                attempts,
                response.status_code,
                response.text,
            )

        if attempt < attempts:
            await asyncio.sleep(max(0.0, CALLBACK_RETRY_DELAY_SECONDS))

    logger.error(
        "Callback permanently failed for job_id=%s after %s attempt(s)",
        job_id,
        attempts,
    )
