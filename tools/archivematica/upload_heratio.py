#!/usr/bin/env python
# upload_heratio.py
#
# Archivematica MCPClient client script: uploads a DIP's files to Heratio
# (AtoM 2.10 + Laravel overlay by AHG) via the REST API v2 endpoint
#   POST /api/v2/descriptions/{slug}/upload
# instead of the legacy SWORD deposit used by upload_qubit.py, which Heratio's
# Laravel front controller intercepts and rejects (405 Method Not Allowed).
#
# Modeled on archivematica/MCPClient/clientScripts/upload_qubit.py — reuses
# the same SIP/Job/Access bookkeeping so dashboard status tracking still
# works, but replaces the single SWORD POST with one API call per file found
# in the DIP directory, and keeps going on individual file failures rather
# than aborting the whole job (bytes-in-reliably-first priority).
#
# ITEM-LEVEL HIERARCHY (added):
# Rather than uploading every file in a DIP as a separate Master digital
# object on the same parent description (which AtoM's classic view only
# ever displays one of), each file now gets its own CHILD description
# created under the DIP's parent slug via POST /api/v2/descriptions, and
# is uploaded to that child's slug instead. This matches AtoM's expected
# one-Master-per-description data shape.
#
# RETRY-SAFETY (added):
# Because Heratio's create-description endpoint (generateSlug) only
# guarantees unique *slugs*, not unique *records*, and its upload endpoint
# (uploadForDescription) blindly inserts a new digital_object with no
# existing-Master check, re-running this script against the same DIP could
# otherwise create duplicate child descriptions and/or duplicate Masters.
# resolve_child_slug() avoids this by checking for an existing description
# at the expected slug (reusing it if found) and checking whether it
# already has a digital object attached (skipping the upload if so).
import csv
import mimetypes
import optparse
import os
import re
import subprocess
import sys
import unicodedata
import django
import requests
from archivematica.archivematicaCommon.custom_handlers import get_script_logger
django.setup()
from django.conf import settings as mcpclient_settings
from django.core.exceptions import ValidationError
from django.db import transaction
import archivematica.dashboard.main.models as models
logger = get_script_logger("archivematica.upload.heratio")
PREFIX = "[uploadHeratio]"
# SSH access to Heratio for triggering post-upload derivative regeneration
# (thumbnail/reference generation) — see heratio:regenerate-derivatives,
# a custom artisan command added to work around several bugs in Heratio's
# own DerivativeService/UploadController (usage_id mislabeling, missing
# object-row creation, wrong storage disk — see AHG bug report). Runs as
# www-data via a narrowly-scoped passwordless sudo rule on the Heratio side.
# These are read from the environment so no deployment's host/user is baked
# into the (public) repo. As of Heratio v1.154.448 the v2 upload endpoint
# generates the reference/thumbnail derivatives inline, so this SSH step is
# OPTIONAL - leave HERATIO_SSH_HOST unset and regenerate_derivatives() no-ops.
HERATIO_SSH_HOST = os.environ.get("HERATIO_SSH_HOST", "")
HERATIO_SSH_USER = os.environ.get("HERATIO_SSH_USER", "")
HERATIO_SSH_KEY = os.environ.get("HERATIO_SSH_KEY", "")
HERATIO_SSH_TIMEOUT = int(os.environ.get("HERATIO_SSH_TIMEOUT", "60"))
def regenerate_derivatives(digital_object_id, debug=False):
    """SSH into Heratio and trigger derivative regeneration for one
    digital_object_id. Returns (success, output). Failure here is treated
    as a soft warning by the caller — the upload itself already succeeded,
    so a derivative-generation hiccup shouldn't fail the whole job.

    No-op when HERATIO_SSH_HOST is unset: Heratio >= v1.154.448 generates the
    derivatives inline during the upload, so this out-of-band step is only
    needed against older Heratio builds."""
    if not HERATIO_SSH_HOST:
        return True, "skipped (HERATIO_SSH_HOST unset; derivatives generated inline by Heratio v1.154.448+)"
    remote_cmd = (
        f"sudo -u www-data /usr/bin/php /usr/share/nginx/heratio/artisan "
        f"heratio:regenerate-derivatives {int(digital_object_id)}"
    )
    ssh_cmd = [
        "ssh",
        "-i", HERATIO_SSH_KEY,
        "-o", "BatchMode=yes",
        "-o", "StrictHostKeyChecking=accept-new",
        f"{HERATIO_SSH_USER}@{HERATIO_SSH_HOST}",
        remote_cmd,
    ]
    try:
        result = subprocess.run(
            ssh_cmd,
            capture_output=True,
            text=True,
            timeout=HERATIO_SSH_TIMEOUT,
        )
    except subprocess.TimeoutExpired:
        return False, "SSH command timed out"
    except Exception as exc:
        return False, f"SSH invocation error: {exc}"
    output = (result.stdout or "") + (result.stderr or "")
    if debug:
        log(f"> regen-derivatives({digital_object_id}): exit={result.returncode} output={output.strip()}")
    if result.returncode != 0:
        return False, output.strip() or f"non-zero exit ({result.returncode})"
    return True, output.strip()
# Files inside a DIP that are structural/metadata, not digital objects to
# upload as content. METS files are named METS.<uuid>.xml, not a fixed
# name, so we match by prefix/suffix rather than exact string.
SKIP_FILENAME_EXACT = {"processingMCP.xml"}
def is_skip_filename(filename):
    if filename in SKIP_FILENAME_EXACT:
        return True
    if filename.startswith("METS.") and filename.endswith(".xml"):
        return True
    # Skip any .xml sidecar (e.g. techMD/PREMIS sidecars living alongside a
    # master file inside objects/, such as "<uuid>-somefile.tif.xml") — these
    # describe a file, they aren't content to upload themselves.
    if filename.endswith(".xml"):
        return True
    # metadata.csv is now expected to live inside objects/ (alongside the
    # content files) rather than in the separate top-level metadata/
    # directory, since Archivematica's DIP generation does not carry
    # metadata/ through into the DIP, but does carry objects/ through.
    # Without this check it would otherwise be treated as a content file
    # and uploaded to Heratio as if it were an image. Matched the same way
    # find_metadata_csv() matches it: case-insensitively, after stripping
    # any Archivematica UUID prefix.
    if metadata_filename_key(filename) == METADATA_CSV_BASENAME:
        return True
    return False
# Directories inside a DIP we don't want to upload as digital objects.
# NOTE: "thumbnails" is skipped because the v2 upload endpoint hardcodes
# usage_id=166 (Master) for every file — there is currently no way to tag
# a thumbnail as an access/derivative copy via this API. Uploading them
# would create incorrect duplicate "master" digital objects per item.
# "OCRfiles" (derived text) is skipped for the same reason — revisit both
# once/if the API supports setting usage_id per upload.
SKIP_DIR_NAMES = {"thumbnails", "OCRfiles", "metadata", "logs", "submissionDocumentation"}
def hilite(string, status=True):
    if not os.isatty(sys.stdout.fileno()):
        return string
    attr = ["32"] if status else ["31"]
    return "\x1b[{}m{}\x1b[0m".format(";".join(attr), string)
def log(message, access=None):
    logger.error(f"{PREFIX} {hilite(message)}")
    if access:
        access.status = message
        access.save()
def error(job, message, code=1):
    job.pyprint(f"{PREFIX} {hilite(message, False)}", file=sys.stderr)
    return 1
# os.walk() returns files in filesystem/inode order, not alphabetical or
# natural order. Left uncorrected, that order becomes the order child
# descriptions are created in, which becomes their lft/rgt hierarchy
# position in Heratio, which is what Mirador's gallery preserves -- so a
# DIP's files can end up displayed in an arbitrary, non-numeric order
# (e.g. "p0006, p0008, p0010, p0003, ..."). natural_sort_key() sorts
# filenames the way a person would expect ("p0002" before "p0010"), by
# splitting into alternating text/number chunks and comparing numeric
# chunks numerically rather than as strings.
_NATURAL_SORT_RE = re.compile(r"(\d+)")
def natural_sort_key(filename):
    """Return a sort key that orders filenames the way a human would
    expect (p0002 before p0010), by treating runs of digits as numbers
    rather than comparing them character-by-character as strings."""
    return [
        int(chunk) if chunk.isdigit() else chunk.lower()
        for chunk in _NATURAL_SORT_RE.split(filename)
    ]
def collect_files(directory):
    """Walk the DIP directory and return a list of absolute file paths to
    upload, skipping known structural/metadata files and directories.
    Files are returned in natural filename order (not filesystem/os.walk
    order) so that child-description creation order -- and therefore the
    resulting Heratio hierarchy and Mirador gallery order -- matches the
    order a human would expect from the filenames themselves."""
    collected = []
    for root, dirs, files in os.walk(directory):
        # Prune skip-directories in place so os.walk doesn't descend into them
        dirs[:] = [d for d in dirs if d not in SKIP_DIR_NAMES]
        for filename in files:
            if is_skip_filename(filename):
                continue
            collected.append(os.path.join(root, filename))
    # Sort using the visible filename after removing Archivematica's random
    # UUID prefix. Sorting by the raw basename would order files by UUID rather
    # than by their meaningful names, producing arbitrary child-description
    # and Mirador gallery ordering.
    #
    # The raw basename is included as a deterministic secondary key in case
    # multiple files reduce to the same cleaned display filename.
    collected.sort(
        key=lambda path: (
            natural_sort_key(
                strip_am_uuid_prefix(os.path.basename(path))
            ),
            natural_sort_key(os.path.basename(path)),
        )
    )
    return collected
# Archivematica prefixes every file with a UUID during transfer/ingest
# processing (e.g. "745cf08b-538c-407e-aa13-7c0393c2e699-gray00002.jpg").
# That's useful for AM's own internal tracking but clutters up Heratio's
# hierarchy/title display with a 36-character prefix nobody wants to read.
# We keep the FULL raw filename (UUID prefix included) for slug generation
# and idempotency matching — that's unchanged and still exactly matches
# what create_child_description() and resolve_child_slug() see — but use
# a cleaned-up version, with the UUID prefix stripped, as the *display*
# title sent to Heratio. Extension is kept (e.g. "gray00002.jpg").
_AM_UUID_PREFIX_RE = re.compile(
    r"^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}-"
)
def strip_am_uuid_prefix(filename):
    """Strip a leading Archivematica UUID prefix from a filename for
    display purposes only. Returns the filename unchanged if no such
    prefix is present (e.g. files that didn't go through AM's renaming,
    or were named without one to begin with) — this is a display cleanup,
    not a required transformation, so we fail open rather than raising."""
    return _AM_UUID_PREFIX_RE.sub("", filename)
def slugify(text):
    """Best-effort client-side mimic of Laravel's Str::slug() default
    behavior (ASCII transliteration, lowercase, non-alphanumeric runs
    collapsed to single hyphens). Used only to guess the slug a filename's
    title WOULD produce, so we can look up whether a child description for
    it already exists before creating a new one. Does not need to be a
    perfect match to Heratio's actual Str::slug() implementation for
    typical filenames (letters/digits/./_/-) — see note in
    resolve_child_slug() about the edge case where it diverges."""
    text = unicodedata.normalize("NFKD", text).encode("ascii", "ignore").decode("ascii")
    text = text.lower()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    text = text.strip("-")
    return text or "untitled"
def get_description(base_url, slug, api_key, timeout):
    """GET /api/v2/descriptions/{slug}. Returns (data, error) where data is
    the parsed 'data' payload (or None if not found) and error is a string
    on unexpected failure, None otherwise."""
    url = f"{base_url}/api/v2/descriptions/{slug}"
    headers = {"X-API-Key": api_key}
    try:
        response = requests.get(url, headers=headers, timeout=timeout)
    except requests.exceptions.RequestException as exc:
        return None, f"Request error: {exc}"
    if response.status_code == 404:
        return None, None
    if response.status_code != 200:
        try:
            body = response.json()
            message = body.get("message", response.text)
        except ValueError:
            message = response.text
        return None, f"HTTP {response.status_code}: {message}"
    try:
        body = response.json()
    except ValueError:
        return None, "Invalid JSON response from GET /descriptions/{slug}"
    return body.get("data", body), None
def create_child_description(base_url, parent_slug, title, api_key, timeout, debug=False):
    """POST /api/v2/descriptions to create a child description under
    parent_slug. Returns (success, info) where info is the parsed response
    JSON on success (including the server-generated 'slug') or an error
    message string on failure. Published immediately (publication_status)
    so it doesn't sit invisible in draft state — this is unattended
    automation, there's no human in the loop to publish it later."""
    url = f"{base_url}/api/v2/descriptions"
    headers = {"X-API-Key": api_key}
    payload = {
        "title": title,
        "parent_slug": parent_slug,
        "publication_status": "published",
    }
    try:
        response = requests.post(url, headers=headers, data=payload, timeout=timeout)
    except requests.exceptions.RequestException as exc:
        return False, f"Request error: {exc}"
    if debug:
        log(f"> create_child_description({title!r}): HTTP {response.status_code}")
    if response.status_code == 201:
        try:
            body = response.json()
        except ValueError:
            return False, "Create succeeded (201) but response was not valid JSON"
        return True, body.get("data", body)
    try:
        body = response.json()
        message = body.get("message", response.text)
    except ValueError:
        message = response.text
    return False, f"HTTP {response.status_code}: {message}"
def resolve_child_slug(base_url, parent_slug, filename, api_key, timeout, debug=False):
    """Idempotently resolve the child description slug for one file under
    the DIP's parent slug, creating the description if it doesn't exist
    yet. Returns (slug, already_uploaded, error):
      - error is not None  -> resolution failed, caller should record a
        per-file failure and move on (do not attempt upload).
      - already_uploaded is True -> a description already exists at this
        slug AND already has at least one digital object attached, i.e. a
        prior run already uploaded this file successfully. Caller should
        skip the upload step entirely rather than re-POSTing (Heratio's
        upload endpoint has no existing-Master check and would create a
        second Master alongside the first).
      - otherwise -> slug is ready to upload to (either freshly created or
        an existing-but-empty description from a prior partial failure).

    NOTE: this does not verify that an existing description found at the
    expected slug actually has parent_slug as its parent — it's treated as
    reusable purely by slug match. Fine for the current filename patterns
    (distinct per DIP, no unicode/heavy punctuation), but if two different
    DIPs ever contained identically-named files, this could reuse the
    wrong record. Revisit with a parent-id check in the lookup if that
    becomes a real risk.

    Also note: the client-side slugify() here is a best-effort mimic of
    Heratio's server-side Str::slug(), not a guaranteed match. For unusual
    filenames it could diverge, causing a false-404 (i.e. missing an
    existing description) and creating a duplicate rather than reusing it.
    Not expected to bite typical filenames like the DIP contents seen so
    far.
    """
    expected_slug = slugify(filename)
    desc, err = get_description(base_url, expected_slug, api_key, timeout)
    if err:
        return None, False, err
    if desc is not None:
        digital_objects = desc.get("digital_objects") or []
        already_uploaded = len(digital_objects) > 0
        if debug:
            log(f"> resolve_child_slug({filename!r}): found existing '{expected_slug}', "
                f"already_uploaded={already_uploaded}")
        return expected_slug, already_uploaded, None
    display_title = strip_am_uuid_prefix(filename)
    ok, info = create_child_description(
        base_url, parent_slug, display_title, api_key, timeout, debug=debug
    )
    if not ok:
        return None, False, info
    child_slug = info.get("slug") if isinstance(info, dict) else None
    if not child_slug:
        return None, False, "Description created but response contained no slug"
    if debug:
        log(f"> resolve_child_slug({filename!r}): created new child '{child_slug}'")
    return child_slug, False, None
def upload_one_file(base_url, slug, api_key, filepath, timeout, debug=False):
    """Upload a single file to Heratio via the v2 API. Returns (success, info)
    where info is either the parsed response JSON or an error message."""
    filename = os.path.basename(filepath)
    mime_type, _ = mimetypes.guess_type(filepath)
    mime_type = mime_type or "application/octet-stream"
    url = f"{base_url}/api/v2/descriptions/{slug}/upload"
    headers = {"X-API-Key": api_key}
    try:
        with open(filepath, "rb") as fh:
            response = requests.post(
                url,
                headers=headers,
                files={"file": (filename, fh, mime_type)},
                timeout=timeout,
            )
    except requests.exceptions.RequestException as exc:
        return False, f"Request error: {exc}"
    if debug:
        log(f"> {filename}: HTTP {response.status_code}")
        log(f"> {filename}: Content received: {response.content}")
    if response.status_code == 201:
        try:
            return True, response.json()
        except ValueError:
            return True, {"raw": response.text}
    # Not a success — try to extract a useful message from the JSON error body
    try:
        body = response.json()
        message = body.get("message", response.text)
    except ValueError:
        message = response.text
    return False, f"HTTP {response.status_code}: {message}"

# =============================================================================
# CSV METADATA SIDECAR SUPPORT
#
# A metadata CSV may be included anywhere in the DIP as metadata.csv. Because
# Archivematica may prepend its UUID to transferred files, a filename such as
# "<UUID>-metadata.csv" is also recognised after UUID-prefix removal.
#
# CSV rows are matched to digital objects using the visible filename after the
# Archivematica UUID prefix has been stripped. Only the descriptive fields in
# METADATA_ALLOWED_FIELDS may be sent to Heratio. Blank values are omitted so
# that existing Heratio metadata is never cleared accidentally.
# =============================================================================

METADATA_CSV_BASENAME = "metadata.csv"

METADATA_ALLOWED_FIELDS = (
    "scope_and_content",
    "extent_and_medium",
    "archival_history",
    "acquisition",
    "appraisal",
    "accruals",
    "arrangement",
    "access_conditions",
    "reproduction_conditions",
    "physical_characteristics",
    "finding_aids",
    "location_of_originals",
    "location_of_copies",
    "related_units_of_description",
    "rules",
    "sources",
    "revision_history",
)


def metadata_filename_key(filename):
    """Return a case-insensitive matching key for a CSV filename or a DIP
    filename. Directory components and Archivematica UUID prefixes are removed,
    but the meaningful filename and extension remain."""
    filename = os.path.basename((filename or "").strip())
    filename = strip_am_uuid_prefix(filename)
    return filename.casefold()


def find_metadata_csv(directory):
    """Find one metadata.csv inside the DIP.

    Returns:
        (path, None) when exactly one CSV is found;
        (None, None) when none is present;
        (None, error_message) when multiple candidates are present.

    The metadata directory is searched rather than pruned here because it is
    intentionally excluded only from digital-object uploads, not from metadata
    discovery.
    """
    candidates = []

    for root, dirs, files in os.walk(directory):
        # Avoid derived/log folders while still allowing metadata/.
        dirs[:] = [
            dirname
            for dirname in dirs
            if dirname not in {
                "thumbnails",
                "OCRfiles",
                "logs",
                "submissionDocumentation",
            }
        ]

        for filename in files:
            if metadata_filename_key(filename) == METADATA_CSV_BASENAME:
                candidates.append(os.path.join(root, filename))

    candidates.sort()

    if not candidates:
        return None, None

    if len(candidates) > 1:
        return None, (
            "Multiple metadata CSV files were found: "
            + ", ".join(candidates)
        )

    return candidates[0], None


def find_metadata_csv_in_transfer(transfer):
    """Look for metadata.csv under a Transfer's currentlocation
    (typically currentlyProcessing/<transfer>/metadata/metadata.csv).

    Returns (path, None) when found, (None, None) when not found for
    this transfer. This does not raise on a missing/moved transfer
    directory; it simply reports nothing found so other transfers (or
    the "not found anywhere" case) can be tried.
    """
    location = (transfer.currentlocation or "").replace(
        "%sharedPath%",
        "/var/archivematica/sharedDirectory/",
    )
    location = location.rstrip("/")

    if not location or not os.path.exists(location):
        return None, None

    candidate = os.path.join(location, "metadata", METADATA_CSV_BASENAME)

    if os.path.exists(candidate):
        return candidate, None

    return None, None


def find_metadata_csv_with_fallback(directory, sip_uuid):
    """Find metadata.csv, checking the DIP first, then falling back to
    the currentlyProcessing/ location of any Transfer(s) linked to this
    SIP. This handles the case where Archivematica leaves metadata.csv
    behind in the transfer working directory instead of carrying it
    through into the DIP.

    Returns (path, source, error), where source is "DIP",
    "currentlyProcessing", or None (nothing found anywhere).
    """
    csv_path, error = find_metadata_csv(directory)

    if error:
        return None, None, error

    if csv_path:
        log("Metadata CSV found in DIP: %s" % csv_path)
        return csv_path, "DIP", None

    log("No metadata CSV found in DIP. Checking currentlyProcessing/ "
        "via linked Transfer(s).")

    transfers = list(
        models.Transfer.objects.filter(file__sip_id=sip_uuid).distinct()
    )

    if not transfers:
        log("No Transfer records linked to this SIP.")
        log("No metadata CSV found anywhere.")
        return None, None, None

    for transfer in transfers:
        log(
            "Checking transfer %s (currentlocation: %s)"
            % (transfer.uuid, transfer.currentlocation)
        )
        csv_path, _ = find_metadata_csv_in_transfer(transfer)

        if csv_path:
            log("Metadata CSV found in currentlyProcessing: %s" % csv_path)
            return csv_path, "currentlyProcessing", None

    log("No metadata CSV found anywhere.")
    return None, None, None


def load_metadata_csv(csv_path):
    """Read and validate metadata.csv.

    The preferred matching column is 'filename'. For compatibility with a
    Heratio-exported CSV, 'title' is accepted when no filename column exists.

    Returns:
        (metadata_by_filename, source_rows, error)

    metadata_by_filename maps a cleaned, case-insensitive filename to the
    nonblank, approved Heratio PATCH fields for that image.
    """
    try:
        handle = open(csv_path, "r", encoding="utf-8-sig", newline="")
    except OSError as exc:
        return None, None, "Could not open metadata CSV: %s" % exc

    with handle:
        try:
            reader = csv.DictReader(handle)
            fieldnames = reader.fieldnames or []
        except csv.Error as exc:
            return None, None, "Could not read CSV header: %s" % exc

        normalized_headers = {
            (name or "").strip().casefold(): name
            for name in fieldnames
            if name is not None
        }

        if "filename" in normalized_headers:
            matching_header = normalized_headers["filename"]
        elif "title" in normalized_headers:
            matching_header = normalized_headers["title"]
        else:
            return None, None, (
                "metadata.csv requires a 'filename' column. "
                "A 'title' column is accepted only as a compatibility fallback."
            )

        field_header_map = {}
        for allowed_field in METADATA_ALLOWED_FIELDS:
            original_header = normalized_headers.get(allowed_field.casefold())
            if original_header is not None:
                field_header_map[allowed_field] = original_header

        metadata_by_filename = {}
        source_rows = {}

        try:
            for row_number, row in enumerate(reader, start=2):
                supplied_filename = (row.get(matching_header) or "").strip()

                # Completely empty rows are harmless.
                if not supplied_filename and not any(
                    (value or "").strip() for value in row.values()
                ):
                    continue

                if not supplied_filename:
                    return None, None, (
                        "CSV row %d has metadata but no filename/title value."
                        % row_number
                    )

                key = metadata_filename_key(supplied_filename)

                if not key:
                    return None, None, (
                        "CSV row %d has an invalid filename/title."
                        % row_number
                    )

                if key in metadata_by_filename:
                    return None, None, (
                        "Duplicate metadata row for filename '%s' "
                        "(rows %d and %d)."
                        % (
                            supplied_filename,
                            source_rows[key],
                            row_number,
                        )
                    )

                payload = {}

                for api_field, csv_header in field_header_map.items():
                    value = row.get(csv_header)

                    if value is None:
                        continue

                    value = value.strip()

                    # Blank fields are intentionally omitted so PATCH cannot
                    # erase an existing Heratio value.
                    if value:
                        payload[api_field] = value

                metadata_by_filename[key] = payload
                source_rows[key] = row_number

        except csv.Error as exc:
            return None, None, "CSV parsing failed: %s" % exc

    return metadata_by_filename, source_rows, None


def patch_description_metadata(
    base_url,
    slug,
    api_key,
    metadata,
    timeout,
    debug=False,
):
    """PATCH approved, nonblank descriptive metadata to one exact Heratio
    description slug.

    The caller supplies a payload already restricted to
    METADATA_ALLOWED_FIELDS. Returns (success, info).
    """
    if not metadata:
        return True, {"skipped": True, "reason": "no nonblank metadata"}

    unexpected_fields = sorted(
        set(metadata).difference(METADATA_ALLOWED_FIELDS)
    )

    if unexpected_fields:
        return False, (
            "Refusing metadata PATCH containing unapproved field(s): %s"
            % ", ".join(unexpected_fields)
        )

    url = "%s/api/v2/descriptions/%s" % (base_url, slug)
    headers = {
        "X-API-Key": api_key,
        "Accept": "application/json",
        "Content-Type": "application/json",
    }

    try:
        response = requests.patch(
            url,
            headers=headers,
            json=metadata,
            timeout=timeout,
        )
    except requests.exceptions.RequestException as exc:
        return False, "Request error: %s" % exc

    if debug:
        log(
            "> patch_description_metadata(%r): HTTP %s fields=%s"
            % (
                slug,
                response.status_code,
                ",".join(sorted(metadata)),
            )
        )

    if response.status_code == 200:
        try:
            return True, response.json()
        except ValueError:
            return True, {"raw": response.text}

    try:
        body = response.json()
        message = body.get("message", response.text)
    except ValueError:
        message = response.text

    return False, "HTTP %s: %s" % (response.status_code, message)


def start(job, data):
    # Make sure we are working with an existing SIP record
    if not models.SIP.objects.filter(pk=data.uuid).exists():
        return error(job, "UUID not recognized")

    # Get directory (same lookup pattern as upload_qubit.py)
    jobs = models.Job.objects.filter(sipuuid=data.uuid, jobtype="Upload DIP")

    if jobs.count():
        directory = (
            jobs[0]
            .directory.rstrip("/")
            .replace(
                "%sharedPath%",
                "/var/archivematica/sharedDirectory/",
            )
        )
    else:
        return error(job, "Cannot determine directory")

    if not os.path.exists(directory):
        log("Directory not found: %s" % directory)
        log("Looking up uploadedDIPs/")
        directory = directory.replace("uploadDIP", "uploadedDIPs")

        if os.path.exists(directory) is False:
            return error(
                job,
                "Directory not found: %s" % directory,
            )

    # Restore or create the Access record (same bookkeeping as upload_qubit.py)
    try:
        access = models.Access.objects.get(sipuuid=data.uuid)
    except (models.Access.DoesNotExist, ValidationError):
        access = models.Access(sipuuid=data.uuid)
        transfers = models.Transfer.objects.filter(
            file__sip_id=data.uuid
        ).distinct()

        if transfers.count() == 1:
            access.target = transfers[0].access_system_id

        access.save()

    # This is the PARENT slug. Every content file receives its own child
    # description beneath it.
    parent_slug = access.target
    log("Parent slug: %s" % parent_slug)

    if not parent_slug:
        return error(job, "No target was selected")

    # Locate and validate metadata before creating or uploading any records.
    # Checks the DIP first, then falls back to the currentlyProcessing/
    # location of any linked Transfer, since Archivematica does not always
    # carry metadata.csv through into the DIP.
    metadata_csv, metadata_source, metadata_find_error = (
        find_metadata_csv_with_fallback(directory, data.uuid)
    )

    if metadata_find_error:
        return error(job, metadata_find_error)

    metadata_by_filename = {}
    metadata_source_rows = {}

    if metadata_csv:
        (
            metadata_by_filename,
            metadata_source_rows,
            metadata_load_error,
        ) = load_metadata_csv(metadata_csv)

        if metadata_load_error:
            return error(job, metadata_load_error)

        log(
            "Loaded metadata rows for %d filename(s)."
            % len(metadata_by_filename)
        )
    else:
        log(
            "No metadata.csv found. "
            "Continuing with image upload only."
        )

    # Collect content files. metadata/ remains excluded from digital-object
    # uploading by collect_files().
    files_to_upload = collect_files(directory)

    if not files_to_upload:
        return error(
            job,
            "No files found to upload in %s" % directory,
        )

    log("Found %d file(s) to upload" % len(files_to_upload))

    dip_filename_keys = {
        metadata_filename_key(os.path.basename(path))
        for path in files_to_upload
    }

    for metadata_key in sorted(metadata_by_filename):
        if metadata_key not in dip_filename_keys:
            log(
                "WARNING: Metadata CSV row %s references '%s', "
                "but no matching DIP content file was found. "
                "No Heratio record will be created for that row."
                % (
                    metadata_source_rows.get(metadata_key, "?"),
                    metadata_key,
                )
            )

    access.statuscode = 13
    access.status = (
        "Uploading %d file(s) to Heratio."
        % len(files_to_upload)
    )
    access.save()

    succeeded = []
    failed = []

    for i, filepath in enumerate(files_to_upload, start=1):
        filename = os.path.basename(filepath)
        visible_filename = strip_am_uuid_prefix(filename)
        filename_key = metadata_filename_key(filename)
        metadata_payload = metadata_by_filename.get(filename_key)

        log(
            "(%d/%d) Resolving child description for: %s"
            % (
                i,
                len(files_to_upload),
                filename,
            )
        )

        (
            child_slug,
            already_uploaded,
            resolve_err,
        ) = resolve_child_slug(
            base_url=data.url,
            parent_slug=parent_slug,
            filename=filename,
            api_key=data.api_key,
            timeout=(
                mcpclient_settings.AGENTARCHIVES_CLIENT_TIMEOUT
            ),
            debug=data.debug,
        )

        if resolve_err:
            failed.append(
                (
                    filename,
                    "Description resolution failed: %s"
                    % resolve_err,
                )
            )
            log(
                "(%d/%d) FAILED (description): %s -> %s"
                % (
                    i,
                    len(files_to_upload),
                    filename,
                    resolve_err,
                )
            )
            continue

        upload_info = {
            "slug": child_slug,
        }

        if already_uploaded:
            log(
                "(%d/%d) SKIPPED image upload "
                "(already uploaded on prior run): %s -> %s"
                % (
                    i,
                    len(files_to_upload),
                    filename,
                    child_slug,
                )
            )
            upload_info["skipped"] = True
        else:
            log(
                "(%d/%d) Uploading: %s -> child slug '%s'"
                % (
                    i,
                    len(files_to_upload),
                    filename,
                    child_slug,
                )
            )

            ok, upload_info = upload_one_file(
                base_url=data.url,
                slug=child_slug,
                api_key=data.api_key,
                filepath=filepath,
                timeout=(
                    mcpclient_settings.AGENTARCHIVES_CLIENT_TIMEOUT
                ),
                debug=data.debug,
            )

            if not ok:
                failed.append((filename, upload_info))
                log(
                    "(%d/%d) FAILED: %s -> %s"
                    % (
                        i,
                        len(files_to_upload),
                        filename,
                        upload_info,
                    )
                )
                continue

            digital_object_id = (
                upload_info
                .get("data", {})
                .get("digital_object_id")
            )

            log(
                "(%d/%d) OK: %s -> digital_object_id %s"
                % (
                    i,
                    len(files_to_upload),
                    filename,
                    digital_object_id or "?",
                )
            )

            if digital_object_id:
                regen_ok, regen_info = regenerate_derivatives(
                    digital_object_id,
                    debug=data.debug,
                )

                if regen_ok:
                    log(
                        "(%d/%d) Derivatives regenerated "
                        "for digital_object_id %s"
                        % (
                            i,
                            len(files_to_upload),
                            digital_object_id,
                        )
                    )
                else:
                    # Soft failure: the master image upload succeeded.
                    log(
                        "(%d/%d) WARNING: derivative regeneration "
                        "failed for digital_object_id %s -> %s"
                        % (
                            i,
                            len(files_to_upload),
                            digital_object_id,
                            regen_info,
                        )
                    )

        # Apply metadata after the exact existing/created child slug is known.
        # This runs even when image upload was skipped on a retry.
        if metadata_payload is not None:
            if metadata_payload:
                log(
                    "(%d/%d) Applying metadata to '%s': %s"
                    % (
                        i,
                        len(files_to_upload),
                        child_slug,
                        ", ".join(sorted(metadata_payload)),
                    )
                )

                metadata_ok, metadata_info = patch_description_metadata(
                    base_url=data.url,
                    slug=child_slug,
                    api_key=data.api_key,
                    metadata=metadata_payload,
                    timeout=(
                        mcpclient_settings.AGENTARCHIVES_CLIENT_TIMEOUT
                    ),
                    debug=data.debug,
                )

                if not metadata_ok:
                    failed.append(
                        (
                            filename,
                            "Image handled, but metadata PATCH failed: %s"
                            % metadata_info,
                        )
                    )
                    log(
                        "(%d/%d) FAILED (metadata): %s -> %s"
                        % (
                            i,
                            len(files_to_upload),
                            visible_filename,
                            metadata_info,
                        )
                    )
                    continue

                log(
                    "(%d/%d) Metadata updated successfully: "
                    "%s -> %s"
                    % (
                        i,
                        len(files_to_upload),
                        visible_filename,
                        child_slug,
                    )
                )
            else:
                log(
                    "(%d/%d) Metadata row found for %s, "
                    "but all approved fields are blank; PATCH skipped."
                    % (
                        i,
                        len(files_to_upload),
                        visible_filename,
                    )
                )
        else:
            log(
                "(%d/%d) No metadata row for %s; metadata PATCH skipped."
                % (
                    i,
                    len(files_to_upload),
                    visible_filename,
                )
            )

        succeeded.append((filename, upload_info))

    # Final status: report partial success rather than hiding image or
    # metadata failures.
    access.statuscode = 14 if not failed else 12
    access.status = (
        "Processed %d/%d file(s) successfully for Heratio."
        % (
            len(succeeded),
            len(files_to_upload),
        )
    )

    if metadata_csv:
        access.status += (
            " Metadata source: %s."
            % os.path.basename(metadata_csv)
        )

    if failed:
        failure_summary = "; ".join(
            "%s: %s" % (name, message)
            for name, message in failed
        )
        access.status += " Failures: %s" % failure_summary

    access.save()
    log(access.status)

    if failed:
        job.pyprint(
            (
                "%s %d of %d file(s) had upload or metadata "
                "failures. See Access record for details."
            )
            % (
                PREFIX,
                len(failed),
                len(files_to_upload),
            ),
            file=sys.stderr,
        )
        return 1

    return 0


def call(jobs):
    parser = optparse.OptionParser(usage="Usage: %prog [options]")
    options = optparse.OptionGroup(parser, "Basic options")
    options.add_option("-u", "--url", dest="url", metavar="URL",
                        help="Base URL of Heratio, e.g. https://heratio.artorius.co.za")
    options.add_option("-k", "--api-key", dest="api_key", metavar="API_KEY",
                        help="Heratio X-API-Key value")
    options.add_option("-U", "--uuid", dest="uuid", metavar="UUID", help="SIP UUID")
    options.add_option("-d", "--debug", dest="debug", metavar="DEBUG",
                        default="no", type=str,
                        help="Debug mode, prints per-file HTTP responses")
    parser.add_option_group(options)
    with transaction.atomic():
        for job in jobs:
            with job.JobContext(logger=logger):
                (opts, args) = parser.parse_args(job.args[1:])
                if opts.url is None or opts.api_key is None or opts.uuid is None:
                    parser.print_help()
                    job.print_error("Invalid syntax", 2)
                    job.set_status(1)
                    continue
                opts.url = opts.url.rstrip("/")
                opts.debug = opts.debug.lower() in ["yes", "y", "true", "1"]
                try:
                    job.set_status(start(job, opts))
                except Exception as inst:
                    job.pyprint("Exception!", file=sys.stderr)
                    job.pyprint(type(inst), file=sys.stderr)
                    job.pyprint(inst.args, file=sys.stderr)
                    import traceback
                    job.print_error(traceback.format_exc())
                    job.set_status(1)
