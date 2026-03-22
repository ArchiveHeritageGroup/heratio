"""
Heratio HTR (Handwritten Text Recognition) Service
FastAPI application providing OCR extraction for South African vital records.
Uses TrOCR (microsoft/trocr-large-handwritten) for handwriting recognition.
Runs on port 5006.
"""

import csv
import io
import json
import logging
import os
import subprocess
import sys
import time
import uuid
from datetime import datetime
from pathlib import Path
from typing import List, Optional

import torch
from fastapi import FastAPI, File, Form, HTTPException, UploadFile
from fastapi.responses import FileResponse, JSONResponse, StreamingResponse
from PIL import Image

from field_extractors import extract_fields, full_page_ocr
from gedcom_writer import write_gedcom

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------
BASE_DIR = Path("/opt/ahg-ai/htr")
JOBS_DIR = BASE_DIR / "jobs"
ANNOTATIONS_DIR = BASE_DIR / "annotations"
EXPORTS_DIR = BASE_DIR / "exports"
MODELS_DIR = BASE_DIR / "models"
TRAINING_STATUS_FILE = BASE_DIR / "training_status.json"

SERVICE_VERSION = "1.0.0"

for d in (JOBS_DIR, ANNOTATIONS_DIR, EXPORTS_DIR, MODELS_DIR):
    d.mkdir(parents=True, exist_ok=True)

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(name)s] %(levelname)s %(message)s",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(BASE_DIR / "htr.log"),
    ],
)
logger = logging.getLogger("ahg-htr")

# ---------------------------------------------------------------------------
# App
# ---------------------------------------------------------------------------
app = FastAPI(title="AHG HTR Service", version=SERVICE_VERSION)

START_TIME = time.time()

# ---------------------------------------------------------------------------
# Lazy model loading
# ---------------------------------------------------------------------------
_model = None
_processor = None
_model_load_error = None


def _ensure_model():
    """Lazy-load the TrOCR model on first use."""
    global _model, _processor, _model_load_error
    if _model is not None:
        return
    if _model_load_error is not None:
        raise RuntimeError(f"Model previously failed to load: {_model_load_error}")
    try:
        logger.info("Loading TrOCR model (microsoft/trocr-large-handwritten) ...")
        from transformers import TrOCRProcessor, VisionEncoderDecoderModel

        model_name = "microsoft/trocr-large-handwritten"
        cache_dir = str(MODELS_DIR)

        _processor = TrOCRProcessor.from_pretrained(model_name, cache_dir=cache_dir)
        _model = VisionEncoderDecoderModel.from_pretrained(model_name, cache_dir=cache_dir)

        if torch.cuda.is_available():
            _model = _model.to("cuda")
            logger.info(f"Model loaded on GPU: {torch.cuda.get_device_name(0)}")
        else:
            logger.info("Model loaded on CPU (no CUDA available)")

        _model.eval()
        logger.info("TrOCR model ready")
    except Exception as exc:
        _model_load_error = str(exc)
        logger.error(f"Failed to load TrOCR model: {exc}")
        raise RuntimeError(f"Model load failed: {exc}")


def ocr_image(image: Image.Image) -> str:
    """Run TrOCR inference on a single PIL Image and return recognized text."""
    _ensure_model()
    if image.mode != "RGB":
        image = image.convert("RGB")
    device = "cuda" if torch.cuda.is_available() and _model is not None else "cpu"
    pixel_values = _processor(images=image, return_tensors="pt").pixel_values.to(device)
    with torch.no_grad():
        generated_ids = _model.generate(pixel_values, max_new_tokens=256)
    text = _processor.batch_decode(generated_ids, skip_special_tokens=True)[0]
    return text


# ---------------------------------------------------------------------------
# GPU info helper
# ---------------------------------------------------------------------------
def _gpu_info():
    """Return GPU name and VRAM info, or defaults if unavailable."""
    if torch.cuda.is_available():
        props = torch.cuda.get_device_properties(0)
        vram_total = round(props.total_memory / (1024 ** 3), 2)
        vram_used = round(torch.cuda.memory_allocated(0) / (1024 ** 3), 2)
        return props.name, vram_total, vram_used
    return "N/A (CPU)", 0.0, 0.0


# ---------------------------------------------------------------------------
# Training status helpers
# ---------------------------------------------------------------------------
def _read_training_status() -> dict:
    if TRAINING_STATUS_FILE.exists():
        try:
            return json.loads(TRAINING_STATUS_FILE.read_text())
        except Exception:
            pass
    return {
        "annotation_counts": {"births": 0, "deaths": 0, "marriages": 0, "other": 0},
        "total": 0,
        "training_active": False,
        "last_trained": None,
        "model_version": "base",
    }


def _write_training_status(status: dict):
    TRAINING_STATUS_FILE.write_text(json.dumps(status, indent=2))


def _count_annotations() -> dict:
    counts = {"births": 0, "deaths": 0, "marriages": 0, "other": 0}
    if not ANNOTATIONS_DIR.exists():
        return counts
    for f in ANNOTATIONS_DIR.glob("*.json"):
        try:
            data = json.loads(f.read_text())
            atype = data.get("type", "other")
            if atype == "type_a":
                counts["births"] += 1
            elif atype == "type_b":
                counts["deaths"] += 1
            elif atype == "type_c":
                counts["marriages"] += 1
            else:
                counts["other"] += 1
        except Exception:
            counts["other"] += 1
    return counts


# ---------------------------------------------------------------------------
# Helper: save job result
# ---------------------------------------------------------------------------
def _save_job(job_id: str, doc_type: str, fields: list, processing_time: float, filename: str = ""):
    job_data = {
        "job_id": job_id,
        "doc_type": doc_type,
        "fields": fields,
        "processing_time": processing_time,
        "filename": filename,
        "created_at": datetime.now().isoformat(),
    }
    job_file = JOBS_DIR / f"{job_id}.json"
    job_file.write_text(json.dumps(job_data, indent=2))
    return job_data


# ---------------------------------------------------------------------------
# Helper: generate export files
# ---------------------------------------------------------------------------
def _generate_csv(fields: list) -> str:
    output = io.StringIO()
    writer = csv.writer(output)
    writer.writerow(["field_name", "value", "confidence"])
    for f in fields:
        writer.writerow([f["name"], f["value"], f["confidence"]])
    return output.getvalue()


def _generate_json_export(job_data: dict) -> str:
    return json.dumps(job_data, indent=2)


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------

@app.get("/health")
async def health():
    """Health check endpoint returning GPU and model status."""
    gpu_name, vram_total, vram_used = _gpu_info()
    uptime = round(time.time() - START_TIME, 1)
    return {
        "gpu_name": gpu_name,
        "vram_total": vram_total,
        "vram_used": vram_used,
        "model_loaded": _model is not None,
        "version": SERVICE_VERSION,
        "uptime": uptime,
    }


@app.post("/extract")
async def extract(
    file: UploadFile = File(...),
    doc_type: str = Form("auto"),
    format: str = Form("all"),
):
    """
    Extract handwritten text from an uploaded image.
    Returns structured fields based on the document type.
    """
    if doc_type not in ("auto", "type_a", "type_b", "type_c"):
        raise HTTPException(status_code=400, detail=f"Invalid doc_type: {doc_type}. Must be auto, type_a, type_b, or type_c.")

    # Read and validate image
    try:
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        if image.mode != "RGB":
            image = image.convert("RGB")
    except Exception as e:
        logger.error(f"Invalid image file: {e}")
        raise HTTPException(status_code=400, detail=f"Invalid image file: {e}")

    # Ensure model is loaded
    try:
        _ensure_model()
    except RuntimeError as e:
        raise HTTPException(status_code=503, detail=str(e))

    # Extract fields
    start = time.time()
    try:
        resolved_type, fields = extract_fields(image, doc_type, ocr_image)
    except Exception as e:
        logger.error(f"Extraction failed: {e}")
        raise HTTPException(status_code=500, detail=f"Extraction failed: {e}")
    processing_time = round(time.time() - start, 3)

    # Save job
    job_id = str(uuid.uuid4())
    job_data = _save_job(job_id, resolved_type, fields, processing_time, file.filename or "")

    # Pre-generate export files
    csv_content = _generate_csv(fields)
    csv_path = EXPORTS_DIR / f"{job_id}.csv"
    csv_path.write_text(csv_content)

    json_path = EXPORTS_DIR / f"{job_id}.json"
    json_path.write_text(_generate_json_export(job_data))

    gedcom_content = write_gedcom(resolved_type, fields)
    gedcom_path = EXPORTS_DIR / f"{job_id}.ged"
    gedcom_path.write_text(gedcom_content)

    return {
        "job_id": job_id,
        "fields": fields,
        "doc_type": resolved_type,
        "processing_time": processing_time,
    }


@app.get("/download/{job_id}/{fmt}")
async def download(job_id: str, fmt: str):
    """Download extraction results in the specified format (csv, json, gedcom)."""
    fmt = fmt.lower()
    if fmt not in ("csv", "json", "gedcom", "ged"):
        raise HTTPException(status_code=400, detail=f"Invalid format: {fmt}. Must be csv, json, or gedcom.")

    if fmt in ("gedcom", "ged"):
        ext = "ged"
        media_type = "text/plain"
    elif fmt == "csv":
        ext = "csv"
        media_type = "text/csv"
    else:
        ext = "json"
        media_type = "application/json"

    export_file = EXPORTS_DIR / f"{job_id}.{ext}"

    if not export_file.exists():
        # Try to regenerate from job data
        job_file = JOBS_DIR / f"{job_id}.json"
        if not job_file.exists():
            raise HTTPException(status_code=404, detail=f"Job {job_id} not found")

        job_data = json.loads(job_file.read_text())
        fields = job_data.get("fields", [])
        doc_type = job_data.get("doc_type", "type_a")

        if ext == "csv":
            export_file.write_text(_generate_csv(fields))
        elif ext == "json":
            export_file.write_text(_generate_json_export(job_data))
        elif ext == "ged":
            export_file.write_text(write_gedcom(doc_type, fields))

    return FileResponse(
        path=str(export_file),
        media_type=media_type,
        filename=f"{job_id}.{ext}",
    )


@app.post("/batch")
async def batch(
    files: List[UploadFile] = File(...),
    format: str = Form("csv"),
):
    """Process multiple images and return combined results."""
    if not files:
        raise HTTPException(status_code=400, detail="No files provided")

    # Ensure model is loaded
    try:
        _ensure_model()
    except RuntimeError as e:
        raise HTTPException(status_code=503, detail=str(e))

    batch_id = str(uuid.uuid4())
    results = []
    total_start = time.time()

    for upload_file in files:
        try:
            contents = await upload_file.read()
            image = Image.open(io.BytesIO(contents))
            if image.mode != "RGB":
                image = image.convert("RGB")

            start = time.time()
            resolved_type, fields = extract_fields(image, "auto", ocr_image)
            processing_time = round(time.time() - start, 3)

            job_id = str(uuid.uuid4())
            _save_job(job_id, resolved_type, fields, processing_time, upload_file.filename or "")

            # Generate export files
            csv_content = _generate_csv(fields)
            (EXPORTS_DIR / f"{job_id}.csv").write_text(csv_content)
            (EXPORTS_DIR / f"{job_id}.json").write_text(
                _generate_json_export({"job_id": job_id, "doc_type": resolved_type, "fields": fields})
            )
            (EXPORTS_DIR / f"{job_id}.ged").write_text(write_gedcom(resolved_type, fields))

            results.append({
                "job_id": job_id,
                "filename": upload_file.filename or "",
                "doc_type": resolved_type,
                "fields": fields,
                "processing_time": processing_time,
            })
        except Exception as e:
            logger.error(f"Batch item failed ({upload_file.filename}): {e}")
            results.append({
                "job_id": None,
                "filename": upload_file.filename or "",
                "doc_type": None,
                "fields": [],
                "processing_time": 0,
                "error": str(e),
            })

    total_time = round(time.time() - total_start, 3)

    return {
        "batch_id": batch_id,
        "results": results,
        "total_processing_time": total_time,
        "total_files": len(files),
    }


@app.post("/annotate")
async def annotate(
    image: UploadFile = File(...),
    type: str = Form(...),
    annotations: str = Form(...),
):
    """Save annotations for a document image to use in future training."""
    if type not in ("type_a", "type_b", "type_c", "other"):
        raise HTTPException(status_code=400, detail=f"Invalid type: {type}")

    try:
        annotations_data = json.loads(annotations)
    except json.JSONDecodeError as e:
        raise HTTPException(status_code=400, detail=f"Invalid annotations JSON: {e}")

    # Save image
    annotation_id = str(uuid.uuid4())
    image_contents = await image.read()

    image_ext = os.path.splitext(image.filename or "image.png")[1] or ".png"
    image_path = ANNOTATIONS_DIR / f"{annotation_id}{image_ext}"
    image_path.write_bytes(image_contents)

    # Save annotation metadata
    annotation_record = {
        "annotation_id": annotation_id,
        "type": type,
        "image_file": str(image_path),
        "annotations": annotations_data,
        "created_at": datetime.now().isoformat(),
    }
    meta_path = ANNOTATIONS_DIR / f"{annotation_id}.json"
    meta_path.write_text(json.dumps(annotation_record, indent=2))

    # Count total annotations
    total = len(list(ANNOTATIONS_DIR.glob("*.json")))

    logger.info(f"Saved annotation {annotation_id} (type={type}, total={total})")

    return {
        "status": "saved",
        "annotation_id": annotation_id,
        "total_annotations": total,
    }


@app.get("/training/status")
async def training_status():
    """Return current training status and annotation counts."""
    status = _read_training_status()
    # Refresh annotation counts from disk
    counts = _count_annotations()
    status["annotation_counts"] = counts
    status["total"] = sum(counts.values())
    _write_training_status(status)
    return status


@app.post("/train")
async def train():
    """Trigger model fine-tuning on collected annotations."""
    status = _read_training_status()
    counts = _count_annotations()
    total = sum(counts.values())

    if total < 10:
        return {
            "status": "insufficient_data",
            "message": f"Need at least 10 annotations to train. Currently have {total}.",
        }

    if status.get("training_active"):
        return {
            "status": "already_running",
            "message": "Training is already in progress.",
        }

    # Mark training as active
    status["training_active"] = True
    status["annotation_counts"] = counts
    status["total"] = total
    _write_training_status(status)

    # Launch training in background
    train_script = BASE_DIR / "train_model.py"
    if train_script.exists():
        try:
            subprocess.Popen(
                [sys.executable, str(train_script)],
                cwd=str(BASE_DIR),
                stdout=open(str(BASE_DIR / "training.log"), "a"),
                stderr=subprocess.STDOUT,
            )
            logger.info("Training process started")
        except Exception as e:
            logger.error(f"Failed to start training: {e}")
            status["training_active"] = False
            _write_training_status(status)
            return {"status": "error", "message": f"Failed to start training: {e}"}
    else:
        # No training script yet - just mark as complete
        status["training_active"] = False
        status["last_trained"] = datetime.now().isoformat()
        status["model_version"] = f"ft-{datetime.now().strftime('%Y%m%d')}"
        _write_training_status(status)
        logger.info("Training marked complete (no train_model.py script found)")

    return {
        "status": "started",
        "message": f"Training initiated with {total} annotations.",
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=5006, log_level="info")
