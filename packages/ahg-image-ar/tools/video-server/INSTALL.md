# Heratio image-to-video server - install

Runs on the AI host (Heratio convention: `192.168.0.78`). Listens on `:5052`.

## Hardware reality check

| GPU | Models that work | Notes |
|---|---|---|
| RTX 3070 8 GB (current) | SVD, SVD-XT (with `VIDEO_LOW_VRAM=1`) | ~3–8 min/clip, no text prompt |
| RTX 3090 / 4090 / A4500 24 GB | + CogVideoX-2B/5B-I2V, WAN 2.1 I2V | text prompts work, ~30–90 s/clip |

When the bigger card lands, edit `server.py` to add a `_load_cogvideox()` /
`_load_wan()` branch alongside `_load_svd()`, set `VIDEO_MODEL=cogvideox-2b`,
restart the service. Heratio side needs no changes - it just sends the new
model name in the `model=` form field.

## Install

```bash
# 0. NVIDIA driver + CUDA 12.x already in place
nvidia-smi

# 1. Copy this directory to /opt
sudo mkdir -p /opt/heratio-video-server /var/lib/video-server/{models,hf}
sudo chown -R ahg:ahg /opt/heratio-video-server /var/lib/video-server
scp -r ./* ahg@192.168.0.78:/opt/heratio-video-server/
ssh ahg@192.168.0.78
cd /opt/heratio-video-server

# 2. Python venv (use the system Python 3.10 or 3.11 - torch wheels are matrixed against these)
python3 -m venv .venv
. .venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt

# 3. (Optional but recommended) pre-warm the SVD weights so the first
#    HTTP request isn't a 5 GB cold-download.
python -c "from diffusers import StableVideoDiffusionPipeline; \
  StableVideoDiffusionPipeline.from_pretrained('stabilityai/stable-video-diffusion-img2vid', cache_dir='/var/lib/video-server/models')"

# 4. Smoke test (manual run; Ctrl-C when done)
VIDEO_LOW_VRAM=1 .venv/bin/uvicorn server:app --host 0.0.0.0 --port 5052
# In another terminal:
curl -s http://localhost:5052/health | jq
curl -s -X POST -F image=@/path/to/test.jpg -F num_frames=14 -F fps=7 \
     http://localhost:5052/animate -o /tmp/out.mp4
ls -lh /tmp/out.mp4   # expect ~200–800 KB

# 5. Install as a systemd service
sudo cp heratio-video-server.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now heratio-video-server
sudo journalctl -u heratio-video-server -f
```

## Firewall

```bash
sudo ufw allow from 192.168.0.0/24 to any port 5052 proto tcp
```

## Tuning on 8 GB

Symptoms → knobs:

| Symptom | Try |
|---|---|
| `OutOfMemoryError` mid-generation | reduce `num_frames` to 8–10, drop `width`/`height` to `512x288` |
| Generation takes > 10 min | drop `num_frames`, drop resolution, or accept it (offload trade-off) |
| Motion is too jittery | lower `motion_bucket_id` (try 80) |
| Subjects barely move | raise `motion_bucket_id` (try 180) - may add artifacts |
| First request is very slow | model isn't pre-warmed; step 3 above |

## Heratio-side configuration

In Heratio: **Admin → Image Animation Settings**

- `Server URL`: `http://192.168.0.78:5052`
- `Model`: `svd` (until 24 GB card → switch to `cogvideox-2b` or `wan-2.1`)
- `Frames`: 14
- `FPS`: 7
- `Motion bucket`: 127
- `Request timeout`: 900 (15 min - generous headroom for the 8 GB CPU-offload path)
