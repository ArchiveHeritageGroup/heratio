# Heratio - Docker test stack

A self-contained way to bring up Heratio for testing, on any host with
Docker installed. Three services come up via Compose:

| service | image | role |
|---|---|---|
| `heratio` | built from `docker/Dockerfile` | PHP 8.3 + nginx + Heratio app, supervised |
| `mysql`   | `mysql:8.0` | persistent MySQL 8, utf8mb4, native auth |
| `elasticsearch` | `elasticsearch:8.13.4` | single-node, security off |

Cantaloupe (IIIF) and Ollama (AI) are intentionally **not** included - those
are remote-only on production, and not needed to test the app itself.

## Quick start

```bash
cd /usr/share/nginx/heratio
cp docker/.env.docker.example docker/.env.docker
# edit docker/.env.docker - set ADMIN_PASSWORD at minimum

docker compose -f docker/docker-compose.yml --env-file docker/.env.docker up -d --build
docker compose -f docker/docker-compose.yml logs -f heratio
```

First boot runs `docker/init.sh` which:
1. Generates `.env` from the compose env-vars and runs `artisan key:generate`
2. Loads `database/core/*.sql` (~995 tables)
3. Runs `heratio:install-bootstrap --pass=1` then `--pass=2`
4. Loads `database/seeds/*.sql`
5. Creates an admin user
6. Drops + recreates the `heratio_*` Elasticsearch indices

A marker at `storage/.heratio-installed` makes subsequent boots skip the
schema/seed work - they only re-run pass-2 (idempotent) and verify ES is up.

Once healthy, point your browser at `http://<host>:8088/` and log in with the
admin credentials from `.env.docker`.

## Reset

```bash
docker compose -f docker/docker-compose.yml down -v
```

`-v` drops the volumes - MySQL, ES, uploads, backups all wiped. Next `up`
starts from scratch.

## Inspect a running container

```bash
# shell in the app container
docker exec -it heratio-app bash

# tail the install log
docker exec -it heratio-app tail -f /var/www/heratio/storage/logs/laravel.log

# run a one-off artisan command
docker exec -it heratio-app php artisan tinker
```

## Running on a libvirt VM

```bash
# from the libvirt host (e.g. .112)
sudo bin/heratio-vm.sh   # provisions an Ubuntu 24.04 VM, installs Docker,
                         # clones this repo, runs the stack
```

See `bin/heratio-vm.sh` for the cloud-init / virt-install plumbing.

## What's NOT inside the container

These stay external on purpose:

- **Cantaloupe** (IIIF deep-zoom for TIFF/JP2). Heratio's `ahg-iiif-viewer.js`
  auto-detects TIFF/JP2 and routes to a Cantaloupe instance - point it via
  the `iiif_base_url` setting in the Dropdown Manager.
- **Ollama / vLLM** (AI services - HTR, NER, condition scan). Per project
  policy, AI is remote-only. Set `voice_local_llm_url` to your GPU host.
- **Cron / queue worker.** Add a `queue-worker` service to compose if you
  need background jobs. The supervisor config has a stub.
- **TLS termination.** The container speaks plain HTTP on port 80. Front
  it with the host's nginx (or Traefik) for HTTPS.

## Caveats

- Build is large (~1.5 GB image) because Heratio pulls ~94 packages via
  composer. Trade-off: a fully self-contained image with no dependency on
  the host's PHP / node setup.
- ES 8 requires `vm.max_map_count >= 262144` on the host kernel. If ES
  refuses to start, run: `sudo sysctl -w vm.max_map_count=262144` (and add
  to `/etc/sysctl.conf` for persistence).
- First boot takes a while - ~3 min for the schema + seed pass.

## AI / model policy (load-bearing - read before changing the build)

Heratio is an **AI client, never a host**. The published image at
`ghcr.io/archiveheritagegroup/heratio` is fully public and contains
**zero model weights, zero AI runtimes, zero GPU dependencies**. AI
features (HTR, NER, condition scan, semantic search, voice transcription)
work by HTTP-calling a separate Ollama / vLLM / TGI host you run
yourself. Configure the endpoints in Heratio's Settings dashboard
(`voice_local_llm_url`, etc.) after first login.

This contract is enforced in three places:

1. `docker/.dockerignore` rejects `*.safetensors`, `*.gguf`, `*.ggml`,
   `*.bin`, `*.pt`, `*.pth`, `*.onnx`, `*.h5`, `*.ckpt`, `*.tflite`,
   plus `**/models/`, `**/checkpoints/`,
   `**/.cache/{huggingface,torch,whisper,ollama}/`, and known runtime
   trees (`**/{ollama,llama.cpp,whisper.cpp,transformers_cache}/`).
2. The `Dockerfile` runs a `find` over the build context inside the
   image and **fails the build** if any of those globs match. There is
   no flag to override - if the assertion trips, fix `.dockerignore`.
3. Every published image carries OCI labels
   `io.heratio.ai-bundled=false` and
   `io.heratio.ai-model-policy=remote-only` so the policy is visible
   from a registry scan without pulling.

Do not relax any of these. If a future feature ever needs a local model,
publish that as a *separate* image / sidecar service - never bake it
into Heratio's main image.

## Publishing the image to ghcr.io (operator notes)

A `v*` git tag (i.e. anything `bin/release` produces) triggers
`.github/workflows/docker-publish.yml`, which builds a multi-arch
(amd64 + arm64) image and pushes three tags to GitHub Container
Registry: `vX.Y.Z`, `X.Y`, and `latest`. No extra secret is needed -
the workflow uses `GITHUB_TOKEN` to authenticate against ghcr.io.

### One-time setup: flip the package to public

GHCR creates the package on the first push and **inherits the org's
default visibility, which is Private**. Until you flip it, anonymous
clients will hit `denied: requires authentication` when running
`install-heratio.sh`. You only do this **once per package, ever**:

1. After the first tag push completes successfully, open
   <https://github.com/orgs/ArchiveHeritageGroup/packages>.
2. Click the `heratio` package (only appears after the first push).
3. Right side -> **Package settings**.
4. Scroll to **Danger Zone** -> **Change visibility** -> **Public**.
5. Confirm by typing the package name.

From that moment on the image is anonymously pullable forever, and
every subsequent tag push updates the same package without changing
its visibility.

### Verifying the image is public

```bash
# from any machine NOT logged in to ghcr.io
docker logout ghcr.io
docker pull ghcr.io/archiveheritagegroup/heratio:latest
# should succeed with no auth prompts

# or via the registry HTTP API (no Authorization header)
curl -fI https://ghcr.io/v2/archiveheritagegroup/heratio/manifests/latest
# 200 OK = public; 401 = still private
```

### Verifying the image is AI-free

The OCI labels can be inspected without pulling:

```bash
docker buildx imagetools inspect ghcr.io/archiveheritagegroup/heratio:latest \
    --format '{{json .Manifest}}' | jq '.annotations,.config.Labels'
# should include:
#   "io.heratio.ai-bundled": "false"
#   "io.heratio.ai-model-policy": "remote-only"
```

If those labels are missing or different, the build pipeline has been
altered and the image should not be considered safe to ship as the
canonical Heratio image.
