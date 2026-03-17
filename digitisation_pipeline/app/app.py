import os
import json
from uuid import uuid4
from typing import Any

import requests
from fastapi import FastAPI, UploadFile, File, Form, BackgroundTasks

try:
    from .ner_engine import NEREngine
    from .ocr_engine import OCREngine
    from .llm_check import LLMVerifier
except ImportError:
    # Allow running from inside the app directory (legacy local workflow).
    from ner_engine import NEREngine
    from ocr_engine import OCREngine
    from llm_check import LLMVerifier

class HerbariumOrchestrator:
    """
    Orchestrates the herbarium OCR -> NER -> LLM verification pipeline.
    """

    def __init__(self, llm_verifier: LLMVerifier, ner_model_path, taxonomy_path, ocr_debug=True):
        # Instantiate OCR engine once
        self.ocr_engine = OCREngine(debug=ocr_debug)

        # Instantiate NER engine once
        self.ner_engine = NEREngine(ner_model_path, taxonomy_path)

        # LLM verifier
        self.verifier = llm_verifier

        # Results folder
        self.results_root = "../results"
        os.makedirs(self.results_root, exist_ok=True)

    def process_images(self, image_paths):

        all_results = []

        for img_path in image_paths:

            print("\n==============================")
            print(f"Processing image: {img_path}")
            print("==============================")

            base_name = os.path.splitext(os.path.basename(img_path))[0]

            img_result_dir = os.path.join(self.results_root, base_name)
            os.makedirs(img_result_dir, exist_ok=True)

            # 1️⃣ OCR
            ocr_results = self.ocr_engine.run([img_path])
            ocr_text = ocr_results[0]["text"] if ocr_results else ""
            print(f"OCR text extracted ({len(ocr_text)} chars)")

            # 2️⃣ NER
            ner_result = self.ner_engine.run(ocr_text)
            print(f"NER output: {ner_result}")

            # 3️⃣ LLM verification
            print("Starting LLM verification...")
            verified_result = self.verifier.verify(ocr_text, ner_result)
            print(f"LLM verified output: {verified_result}")

            # Save JSON
            out_path = os.path.join(img_result_dir, f"{base_name}_result.json")

            with open(out_path, "w", encoding="utf-8") as f:
                json.dump({
                    "ocr_text": ocr_text,
                    "ner_output": ner_result,
                    "llm_verified": verified_result
                }, f, indent=2)

            all_results.append({
                "image": img_path,
                "ocr_text": ocr_text,
                "ner_output": ner_result,
                "llm_verified": verified_result
            })

        return all_results


# ==============================
# FASTAPI SERVER
# ==============================

app = FastAPI()

APP_DIR = os.path.dirname(os.path.abspath(__file__))
PIPELINE_ROOT = os.path.dirname(APP_DIR)
PROJECT_ROOT = os.path.dirname(PIPELINE_ROOT)

# Initialise pipeline ONCE
llm = LLMVerifier()

ner_model_path = os.path.join(PIPELINE_ROOT, "ner-models", "herbarium_ner_model-darwincore-gbif2")
taxonomy_path = os.path.join(PIPELINE_ROOT, "resources", "gbif_records_database.json")

orchestrator = HerbariumOrchestrator(
    llm,
    ner_model_path,
    taxonomy_path,
    ocr_debug=True
)

UPLOAD_DIR = os.path.join(PROJECT_ROOT, "herbarium_digitisation", "uploads")
os.makedirs(UPLOAD_DIR, exist_ok=True)

OCR_CALLBACK_BASE_URL = os.getenv("OCR_CALLBACK_URL", "http://127.0.0.1:8000/api/internal/ocr/jobs")


def _callback_payload(job_id: str, payload: dict[str, Any]) -> None:
    callback_url = f"{OCR_CALLBACK_BASE_URL.rstrip('/')}/{job_id}/status"

    try:
        requests.post(callback_url, json=payload, timeout=20)
    except Exception as exc:
        print(f"OCR callback failed for job {job_id}: {exc}")


def _save_upload(file: UploadFile, job_id: str) -> str:
    safe_name = f"{job_id}_{uuid4().hex}_{file.filename}"
    path = os.path.join(UPLOAD_DIR, safe_name)

    with open(path, "wb") as f:
        f.write(file.file.read())

    return path


def _run_batch(job_id: str, image_entries: list[dict[str, str]]) -> None:
    _callback_payload(job_id, {
        "status": "running",
        "progress_step": "ocr_running",
    })

    try:
        image_paths = [entry["absolute_path"] for entry in image_entries]
        raw_results = orchestrator.process_images(image_paths)

        images_payload = []
        for entry, result in zip(image_entries, raw_results):
            images_payload.append({
                "stored_path": entry["stored_path"],
                "original_filename": entry["original_filename"],
                "ocr_text": result.get("ocr_text", ""),
                "llm_verified": result.get("llm_verified", {}),
            })

        _callback_payload(job_id, {
            "status": "completed",
            "progress_step": "ocr_completed",
            "images": images_payload,
        })
    except Exception as exc:
        _callback_payload(job_id, {
            "status": "failed",
            "progress_step": "ocr_failed",
            "error_message": str(exc),
        })


@app.post("/api/v1/jobs/upload")
async def upload_job(
    background_tasks: BackgroundTasks,
    job_id: str = Form(...),
    image_manifest: str = Form("[]"),
    images: list[UploadFile] = File(...),
):
    try:
        manifest = json.loads(image_manifest)
        if not isinstance(manifest, list):
            manifest = []
    except json.JSONDecodeError:
        manifest = []

    image_entries: list[dict[str, str]] = []

    for idx, image in enumerate(images):
        absolute_path = _save_upload(image, job_id)
        manifest_row = manifest[idx] if idx < len(manifest) and isinstance(manifest[idx], dict) else {}

        image_entries.append({
            "absolute_path": absolute_path,
            "stored_path": str(manifest_row.get("stored_path", "")),
            "original_filename": str(manifest_row.get("original_filename", image.filename or os.path.basename(absolute_path))),
        })

    background_tasks.add_task(_run_batch, job_id, image_entries)

    return {
        "job_id": job_id,
        "status": "accepted",
        "queued_images": len(image_entries),
    }


@app.post("/process")
async def process_job(
    job_id: str = Form(...),
    file: UploadFile = File(...)
):

    # Save uploaded file
    file_path = os.path.join(UPLOAD_DIR, f"{job_id}_{file.filename}")

    with open(file_path, "wb") as f:
        f.write(await file.read())

    print(f"Received image: {file_path}")

    # Run pipeline
    results = orchestrator.process_images([file_path])

    print("\nAll results processed:")
    print(results)

    return {
        "job_id": job_id,
        "results": results
    }



def run_local_test(image_paths):
    """
    Run the pipeline locally without FastAPI.
    Useful for debugging the OCR + NER + LLM pipeline.
    """

    print("\n==============================")
    print("RUNNING LOCAL PIPELINE TEST")
    print("==============================")

    results = orchestrator.process_images(image_paths)

    print("\n===== FINAL RESULTS =====")

    for r in results:
        print("\nIMAGE:", r["image"])
        print("\nOCR TEXT:\n", r["ocr_text"])
        print("\nNER OUTPUT:\n", json.dumps(r["ner_output"], indent=2))
        print("\nLLM VERIFIED:\n", json.dumps(r["llm_verified"], indent=2))

    return results


if __name__ == "__main__":

    import sys

    if len(sys.argv) < 2:
        print("Usage:")
        print("python orchestrator.py image1.jpg image2.jpg")
        sys.exit(1)

    image_paths = sys.argv[1:]

    run_local_test(image_paths)