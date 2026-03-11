# infrence.py #

import torch
import open_clip
import pandas as pd
from PIL import Image
import math
import torch.nn.functional as F
import numpy as np
import cv2
import base64

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

def topk_species_predictions(image_path, model, preprocess, class_text_features, unique_labels, meta_map, k=5, device="cpu"):
    model.eval()

    # 1. Load and preprocess
    # raw_img = Image.open(image_path).convert("RGB")
    raw_img = image_path
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


def generate_attention_heatmap(image_path, model, preprocess, device="cpu"):
    """
    Generates an attention heatmap for a given image and model.
    Returns a Base64 string ready for HTML <img src="data:image/png;base64,...">.
    """
    model.eval()
    attn_weights_all = []

    # Hook to capture attention weights
    def capture_attention(module, input):
        x = input[0]
        B, N, C = x.shape
        qkv = F.linear(x, module.in_proj_weight, module.in_proj_bias)
        qkv = qkv.reshape(B, N, 3, module.num_heads, C // module.num_heads)
        qkv = qkv.permute(2, 0, 3, 1, 4)
        q, k = qkv[0], qkv[1]
        attn = (q @ k.transpose(-2, -1)) * ((C // module.num_heads) ** -0.5)
        attn = attn.softmax(dim=-1)
        attn_weights_all.append(attn.detach())

    # Register hooks
    handles = [
        block.attn.register_forward_pre_hook(capture_attention)
        for block in model.visual.transformer.resblocks
    ]

    try:
        # Load and preprocess image
        raw_img = image_path
        processed = preprocess(raw_img)
        input_tensor = (
            processed['pixel_values'].unsqueeze(0).to(device)
            if isinstance(processed, dict)
            else processed.unsqueeze(0).to(device)
        )

        with torch.no_grad():
            _ = model.encode_image(input_tensor)

        # Attention rollout
        num_tokens = attn_weights_all[0].size(-1)
        result = torch.eye(num_tokens).to(device)
        for attn in attn_weights_all:
            attn_avg = attn.mean(dim=1)[0]
            attn_res = attn_avg + torch.eye(num_tokens).to(device)
            result = (attn_res / attn_res.sum(dim=-1, keepdim=True)) @ result

        mask = result[0, 1:]
        grid_size = int(math.sqrt(mask.shape[0]))
        mask = mask.reshape(grid_size, grid_size).cpu().numpy()
        mask = (mask - mask.min()) / (mask.max() - mask.min() + 1e-8)

        # Resize mask and create overlay
        img_np = np.array(raw_img)
        mask_resized = cv2.resize(mask, (img_np.shape[1], img_np.shape[0]))
        heatmap = cv2.applyColorMap(np.uint8(255 * mask_resized), cv2.COLORMAP_JET)
        heatmap = cv2.cvtColor(heatmap, cv2.COLOR_BGR2RGB)
        overlay = cv2.addWeighted(img_np, 0.6, heatmap, 0.4, 0)

        # Encode as Base64
        success, buffer = cv2.imencode(".png", overlay)
        if not success:
            raise ValueError("Failed to encode heatmap image")
        heatmap_base64 = base64.b64encode(buffer).decode("utf-8")
        print("Received image:", image_path)
        print("Overlay shape:", overlay.shape)
        # Return full data URL
        return f"data:image/png;base64,{heatmap_base64}"

    finally:
        for h in handles:
            h.remove()