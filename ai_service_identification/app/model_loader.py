# model.loader.py #

import torch
import open_clip
import pandas as pd
from .inference import get_metadata_map

MODEL_PATH = "./best_bioclip_last_trans.pt"
MODEL_NAME = "hf-hub:imageomics/bioclip-2"
SPECIES_CSV = "./species_label_plantclef2021.csv"
CLASS_METADATA_CSV = "./species_classification.csv"
TEXT_FEATURES_PATH = "./class_text_features.pt"
if torch.cuda.is_available():
    device = torch.device("cuda")
    print(f" GPU Detected: {torch.cuda.get_device_name(0)}")
else:
    device = torch.device("cpu")
    print(" GPU not detected or not compatible. Falling back to CPU.")

def load_everything():
    species_df = pd.read_csv(SPECIES_CSV)
    unique_labels = sorted(species_df["train label"].unique())

    model, _, preprocess = open_clip.create_model_and_transforms(MODEL_NAME)
    model.load_state_dict(torch.load(MODEL_PATH, map_location=device))
    model = model.to(device)
    model.eval()

    data = torch.load(TEXT_FEATURES_PATH, map_location=device, weights_only=False)
    class_text_features = data["text_features"].to(device)

    meta_map = get_metadata_map(SPECIES_CSV, CLASS_METADATA_CSV)

    return model, preprocess, class_text_features, unique_labels, meta_map