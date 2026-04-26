"""
ahg-image-ar — image-to-video FastAPI server.

Designed to run on the Heratio AI host (192.168.0.78). Hosts one or more
diffusion image-to-video pipelines with a uniform HTTP interface. Heratio
POSTs the master image (+ optional text prompt) and gets an MP4 back.

Default model: Stable Video Diffusion (SVD). On an 8 GB GPU we enable
sequential CPU offload — much slower per call but it fits.

When a 24 GB+ GPU is available, swap MODEL_DEFAULT to a prompt-aware
model (cogvideox-2b / wan-2.1) and the Heratio side gains real text
control without code changes.

Endpoints:
  GET  /health           — readiness + currently-loaded model + VRAM
  POST /animate          — multipart: image, prompt, num_frames, fps,
                           motion_bucket_id, seed, model
                           returns: video/mp4 binary

Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
AGPL-3.0-or-later.
"""

from __future__ import annotations

import io
import os
import time
import logging
import tempfile
import subprocess
from contextlib import asynccontextmanager
from typing import Optional

import torch
from fastapi import FastAPI, File, Form, UploadFile, HTTPException
from fastapi.responses import Response, JSONResponse
from PIL import Image

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger("video-server")

MODEL_DEFAULT = os.environ.get("VIDEO_MODEL", "svd")          # svd | svd-xt | cogvideox-2b | wan-2.1
MODEL_CACHE = os.environ.get("VIDEO_MODEL_CACHE", "/var/lib/video-server/models")
LOW_VRAM = os.environ.get("VIDEO_LOW_VRAM", "1") == "1"        # CPU-offload for 8 GB cards

PIPELINES: dict[str, object] = {}     # lazy-loaded model cache


def _load_svd(variant: str = "svd"):
    """Stable Video Diffusion image-to-video. No text prompt."""
    from diffusers import StableVideoDiffusionPipeline

    repo = {
        "svd":    "stabilityai/stable-video-diffusion-img2vid",       # 14 frames, 576x1024, 7 fps
        "svd-xt": "stabilityai/stable-video-diffusion-img2vid-xt",    # 25 frames, 576x1024, 7 fps
    }[variant]

    log.info(f"Loading {repo} (low_vram={LOW_VRAM})…")
    pipe = StableVideoDiffusionPipeline.from_pretrained(
        repo,
        torch_dtype=torch.float16,
        variant="fp16",
        cache_dir=MODEL_CACHE,
    )
    if LOW_VRAM:
        # Sequential CPU offload — slowest but fits in ~6 GB.
        pipe.enable_model_cpu_offload()
        try:
            pipe.unet.enable_forward_chunking()
        except Exception:
            pass
    else:
        pipe = pipe.to("cuda")
    return pipe


def _load_pipeline(model: str):
    if model in PIPELINES:
        return PIPELINES[model]
    if model in ("svd", "svd-xt"):
        pipe = _load_svd(model)
    else:
        raise HTTPException(400, f"Model '{model}' not implemented yet on this server. "
                                  "Currently supported: svd, svd-xt. "
                                  "Add cogvideox-2b/wan-2.1 once your bigger GPU is in.")
    PIPELINES[model] = pipe
    return pipe


def _frames_to_mp4(frames: list[Image.Image], fps: int) -> bytes:
    """Encode PIL frames → MP4 (h264, yuv420p, faststart) via ffmpeg."""
    with tempfile.TemporaryDirectory() as td:
        for i, fr in enumerate(frames):
            fr.save(os.path.join(td, f"f_{i:04d}.png"))
        out = os.path.join(td, "out.mp4")
        cmd = [
            "ffmpeg", "-y", "-loglevel", "error",
            "-framerate", str(fps),
            "-i", os.path.join(td, "f_%04d.png"),
            "-c:v", "libx264", "-pix_fmt", "yuv420p", "-crf", "20",
            "-movflags", "+faststart",
            out,
        ]
        subprocess.run(cmd, check=True)
        with open(out, "rb") as f:
            return f.read()


@asynccontextmanager
async def lifespan(app: FastAPI):
    log.info(f"Pre-loading default model: {MODEL_DEFAULT}")
    try:
        _load_pipeline(MODEL_DEFAULT)
    except Exception as e:
        log.exception(f"Pre-load failed (server still up): {e}")
    yield
    PIPELINES.clear()
    if torch.cuda.is_available():
        torch.cuda.empty_cache()


app = FastAPI(title="Heratio Image-to-Video Server", version="1.0", lifespan=lifespan)


@app.get("/health")
def health():
    cuda = torch.cuda.is_available()
    info = {
        "status": "ok",
        "default_model": MODEL_DEFAULT,
        "loaded_models": list(PIPELINES.keys()),
        "cuda": cuda,
        "low_vram_mode": LOW_VRAM,
    }
    if cuda:
        info["device"] = torch.cuda.get_device_name(0)
        info["vram_total_gb"] = round(torch.cuda.get_device_properties(0).total_memory / 1e9, 2)
        info["vram_free_gb"] = round(torch.cuda.mem_get_info()[0] / 1e9, 2)
    return info


@app.post("/animate")
async def animate(
    image: UploadFile = File(...),
    model: str = Form(default=MODEL_DEFAULT),
    num_frames: int = Form(default=14),
    fps: int = Form(default=7),
    motion_bucket_id: int = Form(default=127),
    noise_aug_strength: float = Form(default=0.02),
    seed: int = Form(default=0),                         # 0 = random
    prompt: Optional[str] = Form(default=None),          # ignored by SVD
    width: int = Form(default=576),
    height: int = Form(default=320),
):
    t0 = time.time()
    img_bytes = await image.read()
    pil = Image.open(io.BytesIO(img_bytes)).convert("RGB")
    pil = pil.resize((width, height), Image.LANCZOS)

    # Conservative bounds for 8 GB.
    num_frames = max(8, min(num_frames, 25))
    fps = max(4, min(fps, 24))
    motion_bucket_id = max(1, min(motion_bucket_id, 255))

    pipe = _load_pipeline(model)

    generator = None
    if seed and seed > 0:
        generator = torch.Generator(device="cuda" if torch.cuda.is_available() else "cpu").manual_seed(int(seed))

    log.info(f"Generating: model={model} frames={num_frames} fps={fps} "
             f"motion={motion_bucket_id} size={width}x{height} prompt={'(none)' if not prompt else prompt[:60]}")

    try:
        if model in ("svd", "svd-xt"):
            result = pipe(
                pil,
                num_frames=num_frames,
                decode_chunk_size=2,            # smaller chunks = less peak VRAM
                motion_bucket_id=motion_bucket_id,
                noise_aug_strength=noise_aug_strength,
                generator=generator,
            )
            frames = result.frames[0]
        else:
            raise HTTPException(400, f"Unsupported model '{model}'.")
    except torch.cuda.OutOfMemoryError as e:
        torch.cuda.empty_cache()
        raise HTTPException(507, f"Out of GPU memory — try fewer frames / smaller size: {e}")

    mp4 = _frames_to_mp4(frames, fps)
    elapsed = time.time() - t0
    log.info(f"Done in {elapsed:.1f}s — {len(mp4)} bytes")

    return Response(
        content=mp4,
        media_type="video/mp4",
        headers={
            "X-Generation-Secs": f"{elapsed:.2f}",
            "X-Model": model,
            "X-Frames": str(num_frames),
            "X-Fps": str(fps),
            "X-Motion-Bucket": str(motion_bucket_id),
            "X-Seed": str(seed),
        },
    )


@app.exception_handler(Exception)
async def all_errors(request, exc):
    log.exception("unhandled")
    return JSONResponse(status_code=500, content={"error": str(exc), "type": exc.__class__.__name__})
