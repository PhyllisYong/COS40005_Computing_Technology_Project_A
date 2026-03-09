# main.py #

from fastapi import FastAPI, UploadFile, File
from PIL import Image
import io
import numpy as np

from .model_loader import load_everything
from .inference import topk_species_predictions

app = FastAPI()

# Load everything
model, preprocess, class_text_features, unique_labels, meta_map = load_everything()

@app.post("/predict")
async def predict(file: UploadFile = File(...)):
    contents = await file.read()
    image = Image.open(io.BytesIO(contents)).convert("RGB")

    # Save temp image
    image_path = "temp.jpg"
    image.save(image_path)

    results = topk_species_predictions(
        image_path,
        model,
        preprocess,
        class_text_features,
        unique_labels,
        meta_map
    )

    return results

