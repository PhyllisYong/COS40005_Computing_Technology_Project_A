# infrence.py #

import torch
import open_clip
import pandas as pd
from PIL import Image
import math
import torch.nn.functional as F
import numpy as np

def get_metadata_map(SPECIES_CSV,CLASS_METADATA_CSV):
    label_df = pd.read_csv(SPECIES_CSV)
    class_df = pd.read_csv(CLASS_METADATA_CSV)
    meta = pd.merge(label_df, class_df, left_on="class id", right_on="ClassIndex")

    def get_freq_bin(count):
        if count <= 100: return "3. Less-seen (<=100)"
        if count <= 1000: return "2. Moderately-seen (<=1000)"
        return "1. Most-seen (>1000)"

    meta['Frequency_Bin'] = meta['Total_Images'].apply(get_freq_bin)
    return meta.set_index('train label').to_dict('index')

# ---------------- CLASS TEXT EMBEDDINGS ---------------- #
@torch.no_grad()
def build_class_text_embeddings(model, class_names, device):
    model.eval()
    print("Building class text embeddings...")
    prompts = [f"a photo of a {name}" for name in class_names]
    text_tokens = open_clip.tokenize(prompts).to(device)
    text_features = model.encode_text(text_tokens)
    text_features = text_features / text_features.norm(dim=-1, keepdim=True)
    return text_features

##updated inference code
import torch.nn.functional as F

def topk_species_predictions(image_path, model, preprocess, class_text_features, unique_labels, meta_map, k=5, device="cpu"):
    model.eval()

    # 1. Load and preprocess
    raw_img = Image.open(image_path).convert("RGB")
    processed = preprocess(raw_img)
    
    # Handle both OpenCLIP (Tensor) and HF (Dict) outputs
    if isinstance(processed, dict):
        input_tensor = processed['pixel_values'].to(device)
    else:
        input_tensor = processed.unsqueeze(0).to(device)

    # 2. Forward pass
    with torch.no_grad():
        # Encode image
        image_features = model.encode_image(input_tensor)
        
        # IMPORTANT: Normalize image features
        image_features /= image_features.norm(dim=-1, keepdim=True)
        
       
        logit_scale = model.logit_scale.exp()
        logits = (image_features @ class_text_features.T) * logit_scale
        
        # Convert to probabilities
        probs = logits.softmax(dim=-1)

    # 3. Extract Top-k
    topk_probs, topk_idxs = probs.topk(k, dim=-1)
    topk_probs = topk_probs[0].cpu().tolist()
    topk_idxs = topk_idxs[0].cpu().tolist()

    results = []
    for idx, conf in zip(topk_idxs, topk_probs):
        # label_name is likely your numeric 'train label' or index
        label_key = unique_labels[idx]
        
        # Fetch metadata using the key
        species_info = meta_map.get(label_key, {})
        
        results.append({
            "species": species_info.get('species', "Unknown Species"),
            "confidence": round(conf, 4),
            "Frequency_Bin": species_info.get('Frequency_Bin', 'Unknown'),
            "category": species_info.get('Category', 'Unknown')
        })

    return results


