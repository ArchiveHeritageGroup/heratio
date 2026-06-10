# Native C2PA embedding setup - c2patool (#1201)

Heratio's provenance & authenticity layer (`packages/ahg-c2pa`) signs every digitisation /
AI-output manifest with an Ed25519 claim signature. That part works on **any** install -- it
needs only `ext-sodium`, produces a verifiable `.c2pa.json` sidecar, and stores a durable DB
record that `/verify` and `/admin/c2pa` check.

The optional second tier is **native embedding**: writing the signed manifest *into the media
bytes* (JUMBF / C2PA-in-file) so the content credentials travel with the file when it leaves
Heratio. That needs the [`c2patool`](https://github.com/contentauth/c2pa-rs) Rust binary, which
is **per-host** (not vendored in the repo), exactly like FBX2glTF and PotreeConverter. When
`c2patool` is absent the package degrades gracefully to signed sidecars + DB records -- nothing
breaks, you just don't get in-file credentials.

Supported embeddable container formats: **JPEG, PNG, TIFF, MP4**. Other formats (PDF, glTF,
plain text, JP2, ...) stay sidecar-only.

## 1. Install the c2patool binary

`c2patool` is published as a Rust crate. Install per server, once.

### Option A - prebuilt release (fastest)

```bash
# Grab the latest linux x86_64 release asset, extract, drop into /usr/local/bin
cd /tmp
curl -fsSL -o c2patool.tar.gz \
  "https://github.com/contentauth/c2pa-rs/releases/latest/download/c2patool-x86_64-unknown-linux-gnu.tar.gz"
tar xzf c2patool.tar.gz
sudo install -m 0755 c2patool*/c2patool /usr/local/bin/c2patool
c2patool --version
```

(Check the project's releases page for the exact current asset name - the prefix is
`c2patool-` and the target triple is `x86_64-unknown-linux-gnu`.)

### Option B - build from source with cargo

```bash
# Needs a Rust toolchain (rustup) on the host
sudo apt-get install -y build-essential pkg-config libssl-dev
curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y
source "$HOME/.cargo/env"
cargo install c2patool
sudo install -m 0755 "$HOME/.cargo/bin/c2patool" /usr/local/bin/c2patool
c2patool --version
```

## 2. Configuration

Heratio resolves the binary **config-first**, then falls back to a PATH probe:

| Config key (`config/heratio.php`) | `.env` override        | Default                   |
|-----------------------------------|------------------------|---------------------------|
| `c2patool_bin`                    | `HERATIO_C2PATOOL_BIN` | `/usr/local/bin/c2patool` |

If you installed elsewhere, set the env var:

```dotenv
HERATIO_C2PATOOL_BIN=/opt/c2pa/c2patool
```

No symlink-publish step is needed (unlike the point-cloud viewer). The binary is only ever
invoked server-side.

## 3. Verify Heratio sees it

```bash
php artisan ahg:c2pa-embed        # dry-run; prints "Using c2patool: <path>" when found
```

If it prints `c2patool not installed on this host`, check the path / env var and that the file
is executable by the web/cron user.

## 4. Backfill existing records

New provenance records embed automatically on capture (best-effort) when the binary is present
and the master is an embeddable format. To backfill records that were signed *before* the binary
was installed:

```bash
php artisan ahg:c2pa-embed                 # dry-run: list what would be embedded
php artisan ahg:c2pa-embed --commit        # write embedded copies
php artisan ahg:c2pa-embed --id=12345      # restrict to one information_object
```

- **Dry-run by default.** `--commit` writes the embedded copies.
- The **original master is never modified in place**: c2patool writes a sibling
  `<master>.c2pa.<ext>` copy alongside it.
- Records are eligible when they have a bound signed manifest, a linked digital object whose
  master resolves on disk, and that master is JPEG/PNG/TIFF/MP4.

## Notes

- The signed sidecar + `ahg_c2pa_manifest` DB row remain the **authoritative** provenance.
  Embedding is an additive convenience for downstream portability; verification inside Heratio
  does not depend on it.
- Like FBX2glTF / PotreeConverter, the binary is intentionally **not** committed to the repo -
  provision it per host so each server controls its own toolchain version.
- The web/cron user that runs `php artisan` and serves uploads must be able to execute the
  binary and write the sibling `.c2pa.<ext>` copy next to the master.
