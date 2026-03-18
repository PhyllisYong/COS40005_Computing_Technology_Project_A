# 1. HOW TO RUN
## Initial Setup
1. run `composer install` to download all available dependencies for the backend
2. run `npm install` for all frontend dependencies such as React and other libraries
3. run `cp .env.example .env` to get a copy of the Laravel environment file
3.1. check and edit configuration for database connection as well as ensure that XAMPP is running the local MySQL server
4. generate an encryption key by running 'php artisan key:generate'

## AI Identification Microservice

### 1. Go to ai_service_identification/ directory  
### 2. Create and activate virtual environment  
```
python -m venv venv
venv\Scripts\activate
```

### 3. Install main dependencies   
```
pip install -r requirements.txt
```

### 4. Run the microservice 
```
python -m uvicorn app.main:app --reload --port 8001
```

---

## Leaf Measurement Microservice
### 1. Clone the forked & modified LeafMachine2 repo: 
```
git clone https://github.com/DamianCWQ/LeafMachine2.git
```

### 2. In the LeafMachine2 repo, Create and activate virtual environment
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
cd image_quality_check
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

---

# OCR Pipeline Microservice

The OCR service runs OCR -> NER -> LLM verification and sends async callbacks to Laravel.

### 1. Go to the OCR pipeline directory
```
cd digitisation_pipeline
```

### 2. Create and activate a virtual environment
```
python -m venv .ocr-venv
.ocr-venv\Scripts\Activate.ps1
```

### 3. Install dependencies
```
python -m pip install --upgrade pip
pip install -r requirements.txt
```

### 4. Go to the app directory
```
cd app
```

### 5. Run the OCR service
```
python -m uvicorn app:app --reload --host 0.0.0.0 --port 8002
```

## Database Setup

1. `php artisan migrate` creates the database schema
2. `php artisan migrate:fresh` drops whatever schema that has been created and reruns all migrations

- ! please note that all migration files are ran in the order that you see in the file directory, from earliest timestamp to latest. this means that 
you can change the order of migrations by simply changing the timestamp of the migration file !
- ! tables with foreign keys need to have those foreign keys declared FIRST in ANOTHER migration before being used in that current one!
- ! you can make new migration file with `php artisan make:migration create<table_name>`. for example, `php artisan make:migration create_inferences_table`.

# Running the Application

1. run `php artisan serve` for the backend
2. run `npm run dev` for the frontend
