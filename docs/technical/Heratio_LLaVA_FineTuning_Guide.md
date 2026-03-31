# Heratio — LLaVA Conservation Fine-Tuning Technical Guide

**For:** ML Engineers, Model Trainers, DevOps
**Date:** 16 March 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Current Infrastructure

### Hardware

| Server | Role | GPU | VRAM | CPU | RAM | Disk Free |
|--------|------|-----|------|-----|-----|-----------|
| 115 | Training + inference | NVIDIA RTX 3080 | 10GB | AMD Ryzen | 32GB | 58GB |
| 112 | Web/app (no GPU) | — | — | Intel Xeon | 64GB | ~30GB |
| 92 | Future inference (pending) | NVIDIA RTX 3060 | 12GB | — | — | — |

### Software Stack (Server 115)

```
OS:           Ubuntu 22.04 LTS
CUDA:         13.0
Driver:       580.126.09
Ollama:       0.17.7
Python venv:  /opt/ahg-ai/.venv/ (transformers 5.x, PyTorch, spaCy, CTranslate2)
```

### Current Models on 115

| Model | Size | Purpose | VRAM (loaded) |
|-------|------|---------|---------------|
| llava:7b | 4.7GB disk | Vision — condition assessment + image description | 5.4GB |
| mistral-nemo:12b | 7.1GB disk | Text generation | 8GB |
| gemma2:9b | 5.4GB disk | Text generation | 6GB |
| mistral:7b | 4.4GB disk | Text generation | 5GB |

Ollama auto-unloads after 5min idle — only one model in VRAM at a time.

### Current AI Services on 115

| Service | Port | Framework | Purpose |
|---------|------|-----------|---------|
| ahg-ai.service | 5004 | Flask | NER, Summarizer, Translator, Spellchecker |
| ahg-ai-gateway.service | 8000 | FastAPI/uvicorn | Gateway router, admin UI |
| Ollama | 11434 | Native | LLM inference (LLaVA, Mistral, etc.) |
| Qdrant | 6333 | Native | Vector DB (used by KM service) |
| KM service | 5050 | Flask | AI Q&A platform (km.theahg.co.za) |

---

## 2. The Model — LLaVA

### What It Is

LLaVA (Large Language and Vision Assistant) = LLaMA text model + CLIP vision encoder. It "sees" an image and generates text about it.

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│  Image      │────>│ CLIP ViT-L   │────>│ LLaMA 7B    │────> Text output
│  (JPEG/PNG) │     │ (vision      │     │ (language    │      (description,
│             │     │  encoder)    │     │  model)      │       damage list)
└─────────────┘     └──────────────┘     └─────────────┘
                         │                      │
                    Visual tokens          Text generation
                    (image features)       conditioned on
                                           image + prompt
![wireframe](./images/wireframes/wireframe_06773439.png)
```

### License & Provenance

| Component | License | Commercial Use |
|-----------|---------|---------------|
| LLaVA | Apache 2.0 | Yes — fully free |
| LLaMA (base) | Meta LLaMA 2 Community License | Yes — free under 700M MAU |
| CLIP (vision) | MIT (OpenAI) | Yes — fully free |
| Ollama (runtime) | MIT | Yes — fully free |
| LoRA fine-tuning (PEFT) | Apache 2.0 | Yes — fully free |

**No recurring costs. No API fees. No data leaves your servers.**

### Model Architecture

```
Base model:     llava-hf/llava-1.5-7b-hf (HuggingFace)
Vision encoder: openai/clip-vit-large-patch14-336
Language model: meta-llama/Llama-2-7b-chat-hf
Image input:    336x336 pixels (CLIP preprocessing)
Parameters:     7B total (6.7B language + 0.3B vision adapter)
Quantization:   Q4_0 (4-bit, via Ollama — 4.7GB on disk)
Full precision: ~14GB (FP16) — needed for training
```

---

## 3. Training Data Format

### Required Format for LoRA Fine-Tuning

LLaVA fine-tuning expects JSON-lines with image path + conversation pairs:

```jsonl
{"id": "condition_001", "image": "images/condition_001.jpg", "conversations": [{"from": "human", "value": "<image>\nYou are a professional conservator. Analyze this photograph and provide a structured condition assessment.\n\nRespond in this format:\nRATING: [excellent/good/fair/poor/critical]\nSEVERITY: [minor/moderate/severe/critical]\nDAMAGE: [comma-separated damage types]\nDESCRIPTION: [2-3 sentences]\nRECOMMENDATIONS: [1-2 sentences]"}, {"from": "gpt", "value": "RATING: poor\nSEVERITY: severe\nDAMAGE: tear, water_damage, foxing\nDESCRIPTION: The document shows significant water damage across the lower third with tide lines and cockling. A large tear approximately 8cm runs along the left margin. Brown foxing spots are visible throughout the upper portion.\nRECOMMENDATIONS: Requires immediate flattening and drying. Repair tear with Japanese tissue and wheat starch paste. Deacidify to prevent further foxing."}]}
```

### Training Data Sources in AtoM DB

**Source 1: Annotated condition photos (primary)**

```sql
-- Export condition photos with damage annotations
SELECT
    cp.id,
    cp.filename,
    cp.file_path,
    cp.photo_type,
    cp.caption,
    cp.annotations,               -- JSON: damage markers with categories
    cc.overall_condition,          -- excellent/good/fair/poor/critical
    cc.condition_description,
    cc.recommended_treatment,
    cc.treatment_priority,
    cc.material_type
FROM spectrum_condition_photo cp
JOIN spectrum_condition_check cc ON cp.condition_check_id = cc.id
WHERE cp.annotations IS NOT NULL
  AND JSON_LENGTH(cp.annotations) > 0;
```

**Source 2: Damage records**

```sql
-- Get damage details per condition check
SELECT
    cd.condition_report_id,
    cd.damage_type,               -- tear, stain, foxing, etc.
    cd.severity,                  -- minor/moderate/severe/critical
    cd.location,                  -- overall, recto, verso, edge_*, spine, cover_*
    cd.treatment_required,
    cd.treatment_notes
FROM condition_damage cd
JOIN spectrum_condition_check cc ON cd.condition_report_id = cc.id;
```

**Source 3: Condition vocabulary (for label standardization)**

```sql
SELECT vocabulary_type, term, description, color, icon
FROM condition_vocabulary
WHERE is_active = 1
ORDER BY vocabulary_type, sort_order;
```

### Annotation JSON Format (from spectrum_condition_photo.annotations)

```json
[
  {
    "id": "ann_1",
    "category": "tear",
    "color": "#dc3545",
    "x": 150, "y": 200, "width": 80, "height": 30,
    "description": "Vertical tear along left margin, approx 8cm"
  },
  {
    "id": "ann_2",
    "category": "water_damage",
    "color": "#0d6efd",
    "x": 0, "y": 400, "width": 600, "height": 200,
    "description": "Water staining across lower third with tide lines"
  }
]
```

### Damage Type Vocabulary (15 types)

```
tear, stain, foxing, fading, water_damage, mold, pest_damage,
abrasion, brittleness, loss, crack, corrosion, discolouration,
deformation, dust
```

### Photo Storage Paths

```
/usr/share/nginx/archive/uploads/condition_photos/     (condition photos)
/usr/share/nginx/archive/uploads/r/                    (digital object masters)
/mnt/nas/heratio/archive/                              (NAS — symlinked from uploads/r)
```

---

## 4. LoRA Fine-Tuning Procedure

### Prerequisites

```bash
# On server 115
cd /opt/ahg-ai
source .venv/bin/activate

# Install training dependencies (if not present)
pip install peft bitsandbytes accelerate datasets Pillow
pip install llava  # or clone from GitHub
```

### Step 1: Export Training Data

Create a script that exports condition photos + labels from AtoM DB into the LLaVA training format.

**Output structure:**

```
/opt/ahg-ai/training/condition/
├── images/
│   ├── condition_001.jpg
│   ├── condition_002.jpg
│   └── ...
├── train.jsonl              (80% of data)
├── val.jsonl                (20% of data)
└── metadata.json            (dataset stats)
```

**Each JSONL line converts DB records into:**

```
Photo file  → images/condition_{id}.jpg
Annotations → structured "from: gpt" response
Prompt      → standard conservation assessment prompt (same as ConditionAIService)
```

### Step 2: Training Configuration

```python
# training_config.py
training_args = {
    "model_name": "llava-hf/llava-1.5-7b-hf",
    "output_dir": "/opt/ahg-ai/models/condition_llava_lora",

    # LoRA parameters
    "lora_r": 16,                    # Rank (8-64, higher = more capacity)
    "lora_alpha": 32,                # Scaling factor (usually 2x rank)
    "lora_dropout": 0.05,
    "lora_target_modules": [         # Which layers to fine-tune
        "q_proj", "v_proj",          # Attention layers
        "mm_projector",              # Vision-language connector (critical)
    ],

    # Training hyperparameters
    "num_train_epochs": 3,           # 3-5 epochs for 500 images
    "per_device_train_batch_size": 1, # RTX 3080 10GB — batch size 1 with gradient accumulation
    "gradient_accumulation_steps": 8, # Effective batch size = 8
    "learning_rate": 2e-5,
    "warmup_ratio": 0.03,
    "weight_decay": 0.0,
    "bf16": True,                    # Use bfloat16 (Ampere GPU)
    "tf32": True,

    # Memory optimization
    "gradient_checkpointing": True,  # Required for 10GB VRAM
    "optim": "paged_adamw_8bit",     # 8-bit optimizer saves VRAM

    # Data
    "train_data": "/opt/ahg-ai/training/condition/train.jsonl",
    "val_data": "/opt/ahg-ai/training/condition/val.jsonl",
    "image_folder": "/opt/ahg-ai/training/condition/images",

    # Saving
    "save_strategy": "epoch",
    "save_total_limit": 3,
}
```

### Step 3: Run Training

```bash
cd /opt/ahg-ai
source .venv/bin/activate

# Verify GPU
python -c "import torch; print(torch.cuda.get_device_name(0))"

# Run training (~4-8 hours for 500 images, 3 epochs)
python train_condition_llava.py

# Output:
# /opt/ahg-ai/models/condition_llava_lora/
# ├── adapter_config.json        (~1KB)
# ├── adapter_model.safetensors  (~50-100MB)
# └── training_args.json
```

### Step 4: Create Ollama Modelfile

```dockerfile
# /opt/ahg-ai/models/condition_llava_lora/Modelfile
FROM llava:7b
ADAPTER /opt/ahg-ai/models/condition_llava_lora/adapter_model.safetensors

PARAMETER temperature 0.3
PARAMETER top_p 0.9
PARAMETER num_predict 500

SYSTEM """You are a professional conservator specializing in cultural heritage preservation.
When analyzing condition photographs, you identify specific damage types using Spectrum 5.1
terminology: tear, stain, foxing, fading, water_damage, mold, pest_damage, abrasion,
brittleness, loss, crack, corrosion, discolouration, deformation, dust.
You assess severity as minor, moderate, severe, or critical.
You provide actionable conservation recommendations."""
```

### Step 5: Register with Ollama

```bash
cd /opt/ahg-ai/models/condition_llava_lora
ollama create condition-llava -f Modelfile

# Verify
ollama list | grep condition
# condition-llava:latest    abc123    5.0 GB    just now

# Test
ollama run condition-llava "Describe the condition of this document" --images /path/to/test.jpg
```

### Step 6: Point AtoM to Fine-Tuned Model

```sql
-- On server 112
UPDATE ahg_settings
SET setting_value = 'condition-llava'
WHERE setting_key = 'voice_local_llm_model';

-- Or keep llava:7b for general use and add condition-specific override
-- in ConditionAIService constructor
```

Or modify `ConditionAIService.php` to use a dedicated model:

```php
// In ConditionAIService::__construct()
$this->model = $this->getSetting('condition_ai_model', 'condition-llava');
// Falls back to 'condition-llava', independent of voice_local_llm_model
```

---

## 5. VRAM Budget

| Phase | VRAM Usage | Notes |
|-------|-----------|-------|
| Inference (Q4_0) | 5.4GB | Current — llava:7b quantized |
| Training (FP16 + LoRA) | ~9.5GB | Tight on 10GB — gradient checkpointing required |
| Training (QLoRA 4-bit) | ~6GB | Safer option — uses bitsandbytes quantized base |

**Recommendation:** Use QLoRA (4-bit quantized base + LoRA adapters) for training on the RTX 3080. This keeps VRAM under 7GB during training.

```python
# QLoRA configuration
from transformers import BitsAndBytesConfig

bnb_config = BitsAndBytesConfig(
    load_in_4bit=True,
    bnb_4bit_quant_type="nf4",
    bnb_4bit_compute_dtype=torch.bfloat16,
    bnb_4bit_use_double_quant=True,
)
```

---

## 6. Evaluation

### Metrics to Track

| Metric | How to Measure | Target |
|--------|---------------|--------|
| Rating accuracy | Compare AI rating vs expert rating on held-out set | >80% exact match |
| Damage detection recall | % of expert-annotated damages that AI also found | >85% |
| Damage detection precision | % of AI-detected damages that are real | >75% |
| False positive rate | AI reports damage where expert sees none | <15% |
| Severity agreement | AI severity matches expert within 1 level | >70% |

### Evaluation Script

```python
# eval_condition.py
import json

def evaluate(predictions_file, ground_truth_file):
    with open(predictions_file) as f:
        preds = [json.loads(l) for l in f]
    with open(ground_truth_file) as f:
        truth = [json.loads(l) for l in f]

    rating_correct = 0
    damage_recall_sum = 0
    damage_precision_sum = 0

    for pred, gt in zip(preds, truth):
        # Rating accuracy
        if pred['overall_rating'] == gt['overall_rating']:
            rating_correct += 1

        # Damage recall/precision
        pred_damages = set(d['type'] for d in pred.get('damage_types', []))
        gt_damages = set(d['type'] for d in gt.get('damage_types', []))

        if gt_damages:
            damage_recall_sum += len(pred_damages & gt_damages) / len(gt_damages)
        if pred_damages:
            damage_precision_sum += len(pred_damages & gt_damages) / len(pred_damages)

    n = len(preds)
    print(f"Rating accuracy:       {rating_correct/n*100:.1f}%")
    print(f"Damage recall (avg):   {damage_recall_sum/n*100:.1f}%")
    print(f"Damage precision (avg):{damage_precision_sum/n*100:.1f}%")
```

---

## 7. Deployment Checklist

```
[ ] Export training data from AtoM DB (min 200 photos with annotations)
[ ] Resize/normalize images to 336x336 (CLIP input size)
[ ] Split 80/20 train/val
[ ] Install training deps on 115 (peft, bitsandbytes, accelerate)
[ ] Run QLoRA training (~4-8 hours)
[ ] Evaluate on held-out set
[ ] Create Ollama Modelfile with LoRA adapter
[ ] Register: ollama create condition-llava -f Modelfile
[ ] Test: ollama run condition-llava with sample images
[ ] Update AtoM setting: condition_ai_model = condition-llava
[ ] Clear AtoM cache
[ ] Verify via UI: AI Scan button on condition photos
[ ] Monitor: check /var/log/atom/condition.log for errors
```

---

## 8. File Locations

| What | Path (115) |
|------|-----------|
| Ollama models | `~/.ollama/models/` |
| Python venv | `/opt/ahg-ai/.venv/` |
| Training data output | `/opt/ahg-ai/training/condition/` |
| LoRA adapter weights | `/opt/ahg-ai/models/condition_llava_lora/` |
| Modelfile | `/opt/ahg-ai/models/condition_llava_lora/Modelfile` |
| AI service | `/opt/ahg-ai/api/ai_service.py` |
| Logs | `/opt/ahg-ai/logs/` |

| What | Path (112) |
|------|-----------|
| ConditionAIService | `/usr/share/nginx/archive/atom-ahg-plugins/ahgConditionPlugin/lib/Service/ConditionAIService.php` |
| Condition photos | `/usr/share/nginx/archive/uploads/condition_photos/` |
| AtoM config | `ahg_settings` table: `voice_local_llm_url`, `voice_local_llm_model` |

---

## 9. Contacts & Access

| Server | SSH |
|--------|-----|
| 115 | `ssh johanpiet@192.168.0.115` |
| 112 | Direct (web/app server) |
| Ollama API (115) | `http://192.168.0.115:11434` |
| AI service (115) | `http://192.168.0.115:5004/ai/v1/` |

---

*Heratio Framework v2.8.2 — The Archive and Heritage Group (Pty) Ltd*
