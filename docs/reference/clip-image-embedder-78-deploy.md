# CLIP / nomic-embed-vision image embedder on .78 (issue #1272 layer C)

Deploy runbook for the multimodal image-embedding worker that backs
`ahg-discovery` image search. Layers A (heratio indexer + `embedImage()`) and B
(gateway `/ai/v1/embed/image` route) are already shipped; this is the remaining
GPU/worker service (layer C).

## Contract (fixed by the gateway)

The gateway (`ai_proxy.py` `_worker_failover("clip", ...)`) forwards to the node
whose `gpu_nodes.capabilities.services` contains `"clip"`, at:

```
POST http://<node-ip>:5004/ai/v1/embed/image
Header: X-API-Key: <gateway UPSTREAM_AI_KEY>
Body:   {"model": "nomic-embed-vision-v1.5", "image": "<base64>"}
Reply:  {"embedding": [768 floats]}   (L2-normalised, shares space with nomic-embed-text)
```

`WORKER_PORT` is a single global in the gateway (5004) applied to every node, so
the service MUST listen on **5004**. The base URL already includes `/ai/v1`, so
the service path is **`/ai/v1/embed/image`**.

## Target node + constraints

- Node: **192.168.0.78** (ahgmachine2 — 12 cores, 125 GB RAM, RTX 3070 8 GB).
- `.78` is **in production** and runs Ollama (`llava:7b/13b`, `mistral:7b`) on
  :11434. **Do not remove or unload any models.**
- Therefore the embedder runs **CPU-only** (`CUDA_VISIBLE_DEVICES=""`): it never
  allocates VRAM and cannot evict the Ollama models. `.78` has plenty of
  RAM/CPU; nomic-embed-vision is small (~0.5 B params) and fine on CPU for a
  batch index.
- `:5004` is currently free on `.78` (only Ollama runs). Verify before start:
  `ss -ltnp | grep ':5004' || echo free`.

## Step 1 - operator runs this ON .78

`.78` sshd is publickey-only, so this is run by the operator (console / their
own key). Paste as one script (e.g. `sudo bash deploy-clip.sh`):

```bash
set -euo pipefail
SVC_USER=ahg
APP_DIR=/opt/ahg-clip-embed
PORT=5004

# 1) python venv + deps (CPU-only torch wheel - no CUDA pulled)
sudo mkdir -p "$APP_DIR"
sudo chown "$SVC_USER":"$SVC_USER" "$APP_DIR"
sudo -u "$SVC_USER" python3 -m venv "$APP_DIR/.venv"
sudo -u "$SVC_USER" "$APP_DIR/.venv/bin/pip" install -U pip wheel
sudo -u "$SVC_USER" "$APP_DIR/.venv/bin/pip" install \
    torch --index-url https://download.pytorch.org/whl/cpu
sudo -u "$SVC_USER" "$APP_DIR/.venv/bin/pip" install \
    fastapi "uvicorn[standard]" transformers pillow numpy einops

# 2) the embedder service
sudo -u "$SVC_USER" tee "$APP_DIR/embed_server.py" >/dev/null <<'PY'
import base64, io, os
import torch
import torch.nn.functional as F
from fastapi import FastAPI, Header, HTTPException
from PIL import Image
from transformers import AutoModel, AutoImageProcessor

MODEL_ID = os.getenv("EMBED_MODEL", "nomic-ai/nomic-embed-vision-v1.5")
WORKER_API_KEY = os.getenv("WORKER_API_KEY", "")   # set to gateway UPSTREAM_AI_KEY for defence-in-depth
torch.set_num_threads(int(os.getenv("OMP_NUM_THREADS", "4")))

app = FastAPI()
_processor = AutoImageProcessor.from_pretrained(MODEL_ID)
_model = AutoModel.from_pretrained(MODEL_ID, trust_remote_code=True).eval()

def _decode(b64: str) -> Image.Image:
    if "," in b64 and b64.strip().startswith("data:"):
        b64 = b64.split(",", 1)[1]
    return Image.open(io.BytesIO(base64.b64decode(b64))).convert("RGB")

@app.get("/ai/v1/health")
@app.get("/health")
def health():
    return {"status": "ok", "model": MODEL_ID, "dim": 768, "device": "cpu"}

@app.post("/ai/v1/embed/image")
def embed_image(payload: dict, x_api_key: str = Header(default="")):
    if WORKER_API_KEY and x_api_key != WORKER_API_KEY:
        raise HTTPException(status_code=401, detail="bad worker key")
    b64 = payload.get("image")
    if not b64:
        raise HTTPException(status_code=400, detail="missing 'image'")
    img = _decode(b64)
    with torch.no_grad():
        inp = _processor(img, return_tensors="pt")
        out = _model(**inp).last_hidden_state
        vec = F.normalize(out[:, 0], p=2, dim=1)[0]
    return {"embedding": vec.tolist()}
PY

# 3) systemd unit - CPU-only, restart-always, single worker
sudo tee /etc/systemd/system/ahg-clip-embed.service >/dev/null <<UNIT
[Unit]
Description=AHG CLIP/nomic-embed-vision image embedder (gateway 'clip' worker, #1272)
After=network-online.target
Wants=network-online.target

[Service]
User=$SVC_USER
WorkingDirectory=$APP_DIR
Environment=CUDA_VISIBLE_DEVICES=
Environment=OMP_NUM_THREADS=4
Environment=EMBED_MODEL=nomic-ai/nomic-embed-vision-v1.5
# Optional defence-in-depth: copy UPSTREAM_AI_KEY from the gateway .env on .112
# Environment=WORKER_API_KEY=<gateway UPSTREAM_AI_KEY>
ExecStart=$APP_DIR/.venv/bin/uvicorn embed_server:app --host 0.0.0.0 --port $PORT --workers 1
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
UNIT

sudo systemctl daemon-reload
sudo systemctl enable --now ahg-clip-embed.service
sleep 5
# 4) self-test (first call downloads the model from HF, ~once)
curl -sf http://127.0.0.1:5004/health && echo
python3 - <<'PYTEST'
import base64, io, json, urllib.request
from PIL import Image
buf = io.BytesIO(); Image.new("RGB", (64, 64), (128, 64, 32)).save(buf, "PNG")
body = json.dumps({"model": "nomic-embed-vision-v1.5",
                   "image": base64.b64encode(buf.getvalue()).decode()}).encode()
req = urllib.request.Request("http://127.0.0.1:5004/ai/v1/embed/image", body,
                             {"Content-Type": "application/json"})
v = json.load(urllib.request.urlopen(req))["embedding"]
print("OK dim =", len(v), "sample =", v[:3])
PYTEST
```

Expected: `health` returns `{"status":"ok",...}` and the self-test prints
`OK dim = 768`.

Notes:
- First model load needs HF network access from `.78` (~hundreds of MB, cached
  under the service user's `~/.cache/huggingface`). Subsequent starts are local.
- `CUDA_VISIBLE_DEVICES=` forces CPU; the GPU and its Ollama models are untouched.

## Step 2 - gateway-side (done from .112 once .78 reports OK)

1. Register the capability (Postgres `ahgai`, `gpu_nodes`):
   add `"clip"` to `.78`'s `capabilities.services`.
2. Verify through the gateway:
   `POST https://ai.theahg.co.za/ai/v1/embed/image` with an `ahg_live` key ->
   768-vector.
3. Rebuild the Qdrant index with real vectors:
   `php artisan ahg:qdrant-image-index --recreate`
   (the legacy `archive_images` is 512-dim CLIP; dims differ, so `--recreate`
   is required).
4. Verify image search returns hits end-to-end, then close #1272 and #1268.
