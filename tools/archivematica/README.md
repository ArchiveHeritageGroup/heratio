# Archivematica -> Heratio uploader

`upload_heratio.py` is an **Archivematica MCPClient client script** that pushes a
DIP's files into Heratio. It replaces the stock `upload_qubit.py` (whose legacy
SWORD deposit Heratio's Laravel front controller rejects with `405`).

It lives here for version control; it **runs on the Archivematica server**, not in
Heratio. Install it in the MCPClient client-scripts directory and wire it to the
"Upload DIP" microservice in the same place `upload_qubit.py` is referenced.

## What it does

- One `POST /api/v2/descriptions/{slug}/upload` per file in the DIP (bytes-in
  first: it keeps going on individual file failures rather than aborting the job).
- **Item-level hierarchy:** each file gets its own child description created under
  the DIP's **parent slug** via `POST /api/v2/descriptions`, then the file is
  uploaded to that child. The parent slug is the Archivematica **"Access system
  ID"** set on the transfer - so a DIP lands as children under the chosen Heratio
  parent. (heratio#1435)

## Configuration

- **API key / base URL / parent slug** come from the AM job arguments (same
  plumbing as `upload_qubit.py`); nothing is hardcoded here.
- **Optional SSH derivative regeneration** (older Heratio only) reads from the
  environment, so no host is baked into this public repo:

  | Env var | Purpose |
  |---------|---------|
  | `HERATIO_SSH_HOST` | Heratio host to SSH into (leave unset to disable) |
  | `HERATIO_SSH_USER` | SSH user (needs a scoped passwordless `sudo -u www-data` rule) |
  | `HERATIO_SSH_KEY`  | Path to the SSH private key |
  | `HERATIO_SSH_TIMEOUT` | seconds (default 60) |

## Heratio version note

As of **Heratio v1.154.448** the server side is idempotent and self-sufficient:

- `POST /api/v2/descriptions` **dedups by record** (title/identifier within the
  parent), not just by slug - a re-run returns the existing child.
- `POST /api/v2/descriptions/{slug}/upload` **skips** if the description already
  has a master, and routes through the canonical uploader (`/r/` storage, Master
  `usage_id 140`, and **reference + thumbnail derivatives generated inline**).

So against v1.154.448+ the script's client-side dedup (`resolve_child_slug()`) is
belt-and-braces, and the **SSH regenerate step is unnecessary** - leave
`HERATIO_SSH_HOST` unset and `regenerate_derivatives()` no-ops. Both remain for
compatibility with older Heratio builds.
