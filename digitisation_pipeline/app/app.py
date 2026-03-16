# orchestrator.py

import os
import json
from fastapi import FastAPI, UploadFile, File, Form
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

# Initialise pipeline ONCE
llm = LLMVerifier()

ner_model_path = "../ner-models/herbarium_ner_model-darwincore-gbif2"
taxonomy_path = "../resources/gbif_records_database.json"

orchestrator = HerbariumOrchestrator(
    llm,
    ner_model_path,
    taxonomy_path,
    ocr_debug=True
)

UPLOAD_DIR = "../herbarium_digitisation/uploads"
os.makedirs(UPLOAD_DIR, exist_ok=True)

@app.post("/process")
async def process_job(
    job_id: str = Form(...),
    file: UploadFile = File(...)
):

    # Save uploaded file
    file_path = os.path.join(UPLOAD_DIR, file.filename)

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