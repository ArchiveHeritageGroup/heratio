# Archivematica -> Heratio uploader

`upload_heratio.py` is an **Archivematica MCPClient client script** that pushes a
DIP's files into Heratio. It replaces the stock `upload_qubit.py` (whose legacy
SWORD deposit Heratio's Laravel front controller rejects with `405`).

It lives here for version control; it **runs on the Archivematica server**, not in
Heratio. Install it in the MCPClient client-scripts directory and wire it to the
"Upload DIP" microservice in the same place `upload_qubit.py` is referenced.

## Install on the Archivematica server (one-off)

1. **Register Heratio as an access system.** In the AM dashboard: *Administration ->
   General*, set the *AtoM/Binder* (access system) fields to the Heratio target -
   **URL** = `https://<heratio-host>` (the Heratio base URL) and the **API key** =
   a Heratio API key with upload rights. AM passes these to the upload script as the
   `--url` / `--key` job arguments, exactly as it does for a real AtoM target.
2. **Drop the script in place.** Copy `upload_heratio.py` into the MCPClient
   client-scripts directory (alongside the stock `upload_qubit.py`, typically
   `/src/MCPClient/lib/clientScripts/` in the `archivematica-mcp-client` container /
   host), owned by the AM service user and executable.
3. **Point the "Upload DIP" microservice at it.** In *Administration -> Processing
   configuration* (or the workflow / FPR command that runs the
   *"Upload DIP to access system"* chain), replace the `upload_qubit.py` reference
   with `upload_heratio.py`. This is the only wiring change - the job arguments
   (`--url`, `--key`, `--slug`, DIP path) are passed the same way.

## Per-transfer: set the Access system ID = Heratio parent slug

For **each** transfer/SIP whose DIP should land under a specific Heratio record, the
archivist sets the AM **"Access system ID"** to that record's **Heratio slug**
(the last path segment of its URL, e.g. `mobrey-family-archive` from
`https://<heratio-host>/mobrey-family-archive`). AM forwards it to the script as the
parent `--slug`, and every file in the DIP is created as a **child description under
that parent**. Leave it blank and the files land at the top level.

> The slug must already exist in Heratio. Create the parent description first (or
> confirm its slug), then set it as the Access system ID on the transfer.

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
