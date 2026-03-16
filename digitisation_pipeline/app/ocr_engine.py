from ultralytics import YOLO
import os
import numpy as np
from PIL import Image, ImageDraw
import pytesseract

# i think i 

TEXT_MODEL_PATH = "../weights/best_text_detection.pt"
LINE_MODEL_PATH = "../weights/best_line_detection.pt"

CONF_THRESH = 0.65
PAD = 1
PAD_X = 6

TESS_CONFIG = "--oem 1 --psm 7"

RESULTS_DIR = "../results_debug"


class OCREngine:

    def __init__(self, debug=True):

        print("Loading OCR models...")

        self.text_model = YOLO(TEXT_MODEL_PATH)
        self.line_model = YOLO(LINE_MODEL_PATH)

        self.debug = debug

        if debug:
            os.makedirs(RESULTS_DIR, exist_ok=True)

    def run(self, images):

        results = []

        for img_path in images:

            print("\n====================================")
            print(f"Processing image: {img_path}")
            print("====================================")

            base_name = os.path.splitext(os.path.basename(img_path))[0]

            if self.debug:
                img_result_dir = os.path.join(RESULTS_DIR, base_name)
                crops_dir = os.path.join(img_result_dir, "crops")

                os.makedirs(img_result_dir, exist_ok=True)
                os.makedirs(crops_dir, exist_ok=True)

            image = Image.open(img_path).convert("RGB")
            image_np = np.array(image)

            # ==============================
            # REGION DETECTION
            # ==============================

            region_results = self.text_model(image_np)

            if region_results[0].boxes is None:
                print("No regions detected.")
                continue

            region_boxes = region_results[0].boxes.xyxy.cpu().numpy()
            region_confs = region_results[0].boxes.conf.cpu().numpy()

            if self.debug:

                stageA_image = image.copy()
                draw_stageA = ImageDraw.Draw(stageA_image)

                for box, conf in zip(region_boxes, region_confs):

                    if conf < CONF_THRESH:
                        continue

                    x1, y1, x2, y2 = map(int, box)

                    draw_stageA.rectangle(
                        [x1, y1, x2, y2],
                        outline="red",
                        width=3
                    )

                stageA_path = os.path.join(img_result_dir, "stageA_regions.jpg")
                stageA_image.save(stageA_path)

            full_document_text = []

            # ==============================
            # REGION LOOP
            # ==============================

            for region_idx, (region_box, region_conf) in enumerate(zip(region_boxes, region_confs)):

                if region_conf < CONF_THRESH:
                    continue

                x1, y1, x2, y2 = map(int, region_box)

                region_crop = image_np[y1:y2, x1:x2]

                print(f"\n----- REGION {region_idx} -----")

                line_results = self.line_model(region_crop)

                if line_results[0].boxes is None:
                    print("No lines detected.")
                    continue

                line_boxes = line_results[0].boxes.xyxy.cpu().numpy()
                line_confs = line_results[0].boxes.conf.cpu().numpy()

                valid_indices = [
                    i for i, c in enumerate(line_confs)
                    if c >= CONF_THRESH
                ]

                line_boxes = line_boxes[valid_indices]

                if len(line_boxes) == 0:
                    continue

                sorted_indices = sorted(
                    range(len(line_boxes)),
                    key=lambda i: line_boxes[i][1]
                )

                line_boxes = line_boxes[sorted_indices]

                region_text_lines = []

                # ==============================
                # OCR EACH LINE
                # ==============================

                for line_idx, line_box in enumerate(line_boxes):

                    lx1, ly1, lx2, ly2 = map(int, line_box)

                    lx1_pad = max(0, lx1 - PAD_X)
                    ly1_pad = max(0, ly1 - PAD)
                    lx2_pad = min(region_crop.shape[1], lx2 + PAD_X)
                    ly2_pad = min(region_crop.shape[0], ly2 + PAD)

                    line_crop = region_crop[
                        ly1_pad:ly2_pad,
                        lx1_pad:lx2_pad
                    ]

                    line_crop_pil = Image.fromarray(line_crop)

                    if self.debug:

                        crop_filename = f"region_{region_idx}_line_{line_idx}.jpg"
                        crop_path = os.path.join(crops_dir, crop_filename)

                        line_crop_pil.save(crop_path)

                    text = pytesseract.image_to_string(
                        line_crop_pil,
                        lang="eng",
                        config=TESS_CONFIG
                    ).strip()

                    print(f"Line {line_idx}: {text}")

                    if text:
                        region_text_lines.append(text)

                region_text = "\n".join(region_text_lines)

                full_document_text.append(region_text)

            final_text = "\n\n".join(full_document_text)

            results.append({
                "image": img_path,
                "text": final_text
            })

        return results