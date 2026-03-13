COS40005_Computing_Technology_Project_A

This is the repository for Group 12 of COS40005, Computing Technology Project A, also known as FYP-A. 

# Initial Setup

1. run 'composer install' to download all available dependencies

---

# Leaf Measurement Microservice
### 1. Clone the forked & modified LeafMachine2 repo: 
```
git clone https://github.com/DamianCWQ/LeafMachine2.git
```

### 2. Create and activate virtual environment
```
python -m venv .venv
.venv\Scripts\Activate.ps1
```

### 3. Upgrade pip and install uv (fast package installer)
```
python -m pip install --upgrade pip setuptools wheel
python -m pip install uv
```

### 4. Install main dependencies 
```
uv pip install -r requirements.txt
```
*Note: Use `pip install` if you prefer not to install uv*

### 5. Install specific required packages
```
uv pip install "git+https://github.com/waspinator/pycococreator.git@fba8f4098f3c7aaa05fe119dc93bbe4063afdab8#egg=pycococreatortools"
uv pip install "pycocotools>=2.0.5"
uv pip install "opencv-contrib-python-headless==4.7.0.72"
uv pip install "vit-pytorch==0.37.1"
```

### 6. Install PyTorch with CUDA 12.1 support
```
uv pip install torch==2.3.1 torchvision==0.18.1 torchaudio==2.3.1 --index-url https://download.pytorch.org/whl/cu121
```

### 7. Run the microservice
```
uvicorn api.main:app --reload --port 9000
```

---

# Image Quality Check (IQC) Microservice

The IQC service evaluates uploaded images before downstream processing.

### 1. Go to the IQC service directory
```
cd herbarium_digitisation\services\image_quality_check
```

### 2. Create and activate a virtual environment
```
python -m venv .venv
.venv\Scripts\Activate.ps1
```

### 3. Install dependencies
```
python -m pip install --upgrade pip
pip install -r requirements.txt
```

### 4. Run the IQC service
```
uvicorn app.main:app --host 0.0.0.0 --port 9001 --reload
```

### 5. Laravel side requirements
Run a queue worker for IQC dispatch:
```
cd herbarium_digitisation
php artisan queue:work --queue=iqc,default
```