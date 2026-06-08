# PDF web optimisation (fast viewer loading) - setup for new / client instances

## Why

The document viewer embeds PDFs in an iframe and lets the browser render them.
Large documents - especially scanned ones (e.g. 8 pages at ~26 MB/page = 200 MB+) -
load slowly because:

1. The master PDF is **not linearized** ("fast web view" off), so the viewer must
   fetch the cross-reference table at the *end* of the file before page 1 renders.
2. Each page is a full-resolution scan image, so even one page is a big download.

Heratio fixes this by generating a **web-optimized derivative**: the embedded scan
images are downsampled to a screen-sensible DPI and the file is linearized. A
200 MB scan typically becomes a few MB and shows page 1 almost instantly.

The **master is never modified.** The optimized copy is stored alongside it and
registered as a reference (`usage_id` 141, mime `application/pdf`) digital object.
The viewer prefers this reference for display while keeping the master for download
and "open in new tab". Documents with PII redactions are excluded for non-admins
(they keep going through the redacted-asset stream).

## One-time host setup (required for the `ahg:optimize-pdfs` command)

Install Ghostscript and qpdf (most distros package both):

```bash
sudo apt-get install -y ghostscript qpdf      # Debian/Ubuntu
# or: sudo dnf install -y ghostscript qpdf     # RHEL/Fedora
```

Verify they are on PATH (the service resolves them via `command -v`):

```bash
gs --version        # e.g. 10.x
qpdf --version      # e.g. 11.x
```

No `.env` config is needed. If the tools are missing, the command and the
scheduled job no-op cleanly (they log and exit) - nothing breaks.

## Backfill existing documents

Dry-run first (lists what would be optimized; touches nothing):

```bash
cd /usr/share/nginx/heratio
php artisan ahg:optimize-pdfs --min-mb=20
```

Apply (run as www-data so derivative files land with the right ownership on the
NAS - never as root, which would create www-data-unreadable files):

```bash
sudo -u www-data php artisan ahg:optimize-pdfs --commit --min-mb=20 --dpi=200
```

Options:

| Option | Default | Meaning |
|---|---|---|
| `--commit` | (off) | Actually generate + register. Without it, dry-run. |
| `--min-mb` | `20` | Only PDFs larger than this. |
| `--dpi` | `200` | Target DPI for colour/grey images (mono = dpi x1.5, capped 600). 150 = smallest, 300 = crisp. |
| `--max-ratio` | `0.8` | Keep the derivative only if it is at most this fraction of the master (skip already-lean PDFs). |
| `--limit` | `0` | Max PDFs to process (0 = all). |
| `--id` | - | Restrict to one information object (`object_id`). |

Idempotent: a document that already has a web-PDF reference is skipped, so it is
safe to re-run and safe to schedule.

## Automatic optimisation

`AhgCoreServiceProvider` schedules a daily off-peak pass:

```
ahg:optimize-pdfs --commit --min-mb=20 --dpi=200   # daily at 03:10, background, withoutOverlapping
```

so freshly-ingested large PDFs get a web derivative without operator action. The
job no-ops when gs/qpdf are not installed.

## Reversibility

Masters are never touched. To undo a derivative, delete its `digital_object` +
`object` rows (usage 141, mime application/pdf, name `reference_*.web.pdf`) and the
file on disk - the viewer falls straight back to the master.
