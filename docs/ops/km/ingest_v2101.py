#!/usr/bin/env python3
"""Ingest AtoM 2.10.1 documentation into km_heratio Qdrant collection."""

import json
import hashlib
import uuid
from qdrant_client import QdrantClient
from qdrant_client.models import PointStruct
from sentence_transformers import SentenceTransformer
from redact import redact_secrets  # #49: shared redaction floor

QDRANT_URL = "http://localhost:6333"
COLLECTION = "km_heratio"
MODEL_NAME = "all-MiniLM-L6-v2"

# AtoM 2.10.1 documentation chunks
DOCS = [
    # Release notes
    {
        "heading": "AtoM 2.10.1 Release Notes",
        "category": "release",
        "source_file": "https://github.com/artefactual/atom/releases/tag/2.10.1",
        "url": "https://github.com/artefactual/atom/releases",
        "content": """AtoM 2.10.1 was released on December 3, 2024.

Enhancements:
- Added a local only option to the text extraction PDF task
- Added the ability to add archival descriptions to clipboard from a Physical Storage view
- Clarified physical storage reports by including reference codes
- Expanded acquisition date fields in accession/deaccession forms to support YYYY-only or free-form entries
- Introduced new CSV Validator tests for title length validation and event date verification
- AtoM now enforces CSP (Content Security Policy) by default, and is no longer report-only

Bug Fixes:
- Fixed Elasticsearch issue that resulted in incorrect search result count for more than 10k results
- Resolved Content Security Policy violations affecting page refresh and navigation links
- Updated CSV imports to handle unmatched records appropriately
- Fixed CSV import failures when repository data is inherited
- Corrected user menu styling for Single Sign-On implementations
- Fixed fullwidth treeview freezing when item limits increased
- Removed deprecated DebugPDO references
- Corrected street address input to single-line format

Dependencies:
- Updated composer packages to address security and compatibility concerns."""
    },
    # System requirements
    {
        "heading": "AtoM 2.10 System Requirements",
        "category": "admin",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/installation/requirements/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/installation/requirements/",
        "content": """AtoM 2.10 System Requirements:

Hardware: 2 vCPUs @ 2.3GHz, 7GB RAM, 50GB disk minimum plus storage for digital objects.

Operating Systems: Windows, Mac OS X, Ubuntu Linux 24.04 LTS (Noble Numbat).

Required Software:
- Web server: Apache or Nginx
- Database: MySQL 8.0
- Search: Elasticsearch 7.x (7.10 used in development)
- PHP 8.x (8.1, 8.2, 8.3 all supported). As of AtoM 2.9.1, PHP 7 is NOT supported.
- Java 8 (required for Elasticsearch)
- Gearman job server

Required PHP Extensions: cURL, JSON, APC (apcu-bc also required), PDO, PDO-MySQL, XSL.

Optional: Memcached, Readline PHP extension, ImageMagick (image derivatives), Ghostscript (PDF processing), FFmpeg (video derivatives), pdftotext/poppler-utils (PDF text extraction), Apache FOP (PDF finding aids)."""
    },
    # CSV Import guide
    {
        "heading": "CSV Import in AtoM 2.10 - Complete Guide",
        "category": "user-guide",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/user-manual/import-export/csv-import/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/user-manual/import-export/csv-import/",
        "content": """CSV Import in AtoM 2.10:

File Requirements: UTF-8 encoding, Unix/Linux line endings (\\n), column headers must match AtoM template names exactly. Only administrators can import CSV files. Imports run as asynchronous jobs via Gearman.

Templates available for: ISAD archival descriptions, RAD descriptions, Authority records, Accessions, Deaccessions, Events, Repository records, Physical storage.

Hierarchical Data - Two methods:
1. LegacyID/ParentID: Parent records must appear before children. Parents include legacyId, children reference it in parentID column.
2. qubitParentSlug: References existing AtoM descriptions via URL slug. Don't use both in same row.

Key Columns: legacyId, parentID, qubitParentSlug, source_name, eventActors (pipe-separated), eventTypes, eventDates, eventStartDates/eventEndDates (ISO 8601), digitalObjectPath (local), digitalObjectURI (external), physicalObjectName/Location/Type (all three required together), title, identifier, levelOfDescription, repository, culture (ISO 639-1).

Import Process (UI): Import > CSV, set Type, select update behavior (ignore/update/delete-and-replace), optionally skip matched/unmatched, click Import. Monitor via Manage > Jobs.

Matching: Checks legacyId+source_name first, then title+repository+identifier. Use --roundtrip CLI option for legacyId-only matching.

Authority Records: Import authority CSV before description CSV to avoid duplicates. Exact name + same repository required for match."""
    },
    # CSV Import CLI
    {
        "heading": "AtoM 2.10 CLI Import Commands",
        "category": "admin",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/cli-import-export/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/cli-import-export/",
        "content": """AtoM 2.10 CLI Import Commands:

CSV Import (descriptions): php symfony csv:import /path/to/file.csv
Options: --source-name, --default-parent-slug, --skip-nested-set-build, --index, --update="match-and-update" or "delete-and-replace", --limit="slug", --skip-unmatched, --skip-matched, --keep-digital-objects, --skip-derivatives, --roundtrip, --rows-until-update, --skip-rows, --error-log, --no-confirmation

CSV Import (authority records): php symfony csv:authority-import /path/to/file.csv
CSV Import (accessions): php symfony csv:accession-import /path/to/file.csv
CSV Import (repositories): php symfony csv:repository-import /path/to/file.csv
CSV Import (events): php symfony csv:event-import /path/to/file.csv
CSV Import (authority relationships): php symfony csv:authority-relation-import /path/to/file.csv

CSV Validation: php symfony csv:check-import /path/to/file.csv
Options: --verbose, --source, --class-name, --specific-tests, --path-to-digital-objects

Bulk XML Import: php symfony import:bulk /path/to/xmlFolder
Supports: MODS, EAD 2002, SKOS RDF XML, EAC-CPF XML
Options: --index, --taxonomy="ID", --completed-dir, --output, --update="delete-and-replace", --limit, --skip-unmatched, --skip-matched

Bulk XML Export: php symfony export:bulk /path/to/folder
Options: --format="mods" (default EAD), --criteria="SQL", --current-level-only, --single-slug, --public

EAC-CPF Export: php symfony export:auth-recs /path/to/folder

Post-import: php symfony cc && php symfony search:populate
Rebuild nested set: php symfony propel:build-nested-set"""
    },
    # Elasticsearch
    {
        "heading": "Elasticsearch Configuration in AtoM 2.10",
        "category": "admin",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/elasticsearch/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/elasticsearch/",
        "content": """Elasticsearch in AtoM 2.10:

AtoM uses Elasticsearch 7.x as its search and analytics engine.

Disable auto-discovery in /etc/elasticsearch/config/elasticsearch.yml:
transport.type: local

Check index status: php symfony search:status
Shows search host, port, index name, and document counts per entity type.

Check cluster health: curl -XGET 'http://localhost:9200/_cluster/health?pretty=true'
Find problem shards: curl -XGET 'http://localhost:9200/_cluster/health/?level=shards&pretty=true'

Yellow cluster health means primary shard allocated but replicas not. Fix by setting replicas to 0:
curl -XPUT 'localhost:9200/my_index/_settings' -d '{"index":{"number_of_replicas":0}}'

Populate search index: php symfony search:populate
This rebuilds the entire search index from the database. Run after imports, setting changes, or index corruption.

IMPORTANT: Always rebuild nested set BEFORE populating search:
Step 1: php symfony propel:build-nested-set
Step 2: php symfony search:populate
NEVER reverse this order."""
    },
    # Settings
    {
        "heading": "AtoM 2.10 Settings - Global Configuration",
        "category": "user-guide",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/user-manual/administer/settings/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/user-manual/administer/settings/",
        "content": """AtoM 2.10 Global Settings:

Results per page: 5-100 (default 10). Sort options: title/name, date modified, identifier, reference code. Uses ASCII ordering.

Multiple repositories: Enable for multi-institution setups. Adds repository facets in search.

Escape special characters: Define characters (like /) to escape in searches.

Default publication status: Draft or Published for new descriptions.
Description change logging: Audit trail for creation/modification.
Tooltips: Show/hide during data entry.
Permalinks: Generate from title, identifier, or reference code.

Permissive slugs: Allow uppercase and Unicode in URLs (RFC 3987).

CSV Validator: Off, Permissive (errors only block), or Strict (errors+warnings block). UI imports only.

Saved clipboard max age: Days before auto-deletion. Default 0 = purged next day. Set to 30+.

Default templates: Set descriptive standard for descriptions (ISAD(G), DACS, DC, RAD, MODS), authority records, and institutions.

Digital object derivatives: PDF page number for thumbnails (default: first page). Maximum image width for reference copies (default: 480px).

Identifier settings: Accession mask (default %Y-%m-%d/#i), accession counter, identifier mask (disabled by default), identifier counter, reference code separator (default dash), inherit reference code.

Header: Upload logo (PNG, max 50px height), favicon (ICO), header background color."""
    },
    # Archival descriptions
    {
        "heading": "Managing Archival Descriptions in AtoM 2.10",
        "category": "user-guide",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/user-manual/add-edit-content/archival-descriptions/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/user-manual/add-edit-content/archival-descriptions/",
        "content": """Archival Descriptions in AtoM 2.10:

Creating: Add > Archival description. Must be authenticated (contributor/editor/admin). New records default to DRAFT status. Generate identifiers automatically if configured.

Child descriptions: Method 1 - On-the-fly skeleton creation in parent edit page. Method 2 - Full child via 'Add new' button on parent view page.

Publishing: Draft records invisible to unauthenticated users. Update via More > Update publication status. Enable 'Update descendants' to affect children. Large updates run asynchronously.

Calculate dates: Automatically updates parent dates from descendant date ranges. Runs asynchronously. Only affects controlled start/end dates, not display date.

Duplicate records: Simplifies workflow for similar descriptions. Parent duplicates exclude children. Child duplicates stay under same parent.

Link related descriptions: Reciprocal linking via auto-complete. Available in ISAD, DACS, RAD.

Change display standard: Modify template per description (ISAD(G), DACS, DC, RAD, MODS). Can apply to children.

Alternative identifiers: Add legacy/supplementary identifier numbers with labels.

Move descriptions: Drag-and-drop for sibling sort order (manual sort mode). Move button for relocating across hierarchy.

Supported standards: ISAD(G), DACS, Dublin Core, MODS, RAD. Default configurable in Admin > Settings.

Deletion: Removes all children and associated relationships."""
    },
    # Permissions and access
    {
        "heading": "AtoM 2.10 User Roles and Permissions",
        "category": "user-guide",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/user-manual/administer/manage-user-accounts/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/user-manual/administer/manage-user-accounts/",
        "content": """AtoM 2.10 User Roles:

Administrator: Full access to all features including settings, plugins, themes, user management, and all CRUD operations.

Editor: Can create, edit, publish, and delete descriptions and related entities. Cannot access admin settings.

Contributor: Can create and edit descriptions but cannot publish or delete. New records default to draft.

Translator: Can translate interface and content strings but cannot create/edit descriptions.

Researcher: Read-only access to published descriptions. Can use clipboard and saved searches.

Anonymous/Public: Read-only access to published descriptions only.

User groups: Custom permission groups can be created by administrators to fine-tune access control per repository.

Multi-repository access: Permissions can be scoped to specific repositories when multiple repositories are enabled."""
    },
    # Troubleshooting
    {
        "heading": "AtoM 2.10 Common Troubleshooting",
        "category": "admin",
        "source_file": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/troubleshooting/",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/maintenance/troubleshooting/",
        "content": """AtoM 2.10 Troubleshooting:

Clear cache: php symfony cc OR rm -rf cache/*
Restart services: sudo systemctl restart php8.1-fpm (or php8.2-fpm/php8.3-fpm) && sudo systemctl restart nginx

Search not working: Check Elasticsearch is running (sudo systemctl status elasticsearch). Rebuild index: php symfony search:populate. Check cluster health: curl -XGET 'http://localhost:9200/_cluster/health?pretty=true'

500 errors: Check PHP error log (/var/log/nginx/error.log or /var/log/apache2/error.log). Common causes: PHP memory limit, missing PHP extensions, database connection issues.

Slow performance: Increase PHP memory_limit in php.ini. Optimize MySQL with appropriate buffer sizes. Ensure Elasticsearch has adequate heap space (ES_JAVA_OPTS in jvm.options).

CSV import failures: Verify UTF-8 encoding, Unix line endings, correct column headers. Run csv:check-import first. Check Gearman is running for async jobs.

Digital objects not displaying: Check file permissions on uploads directory. Verify ImageMagick/FFmpeg installed. Regenerate derivatives: php symfony digitalobject:regen-derivatives.

Nested set issues (wrong hierarchy display): Rebuild with php symfony propel:build-nested-set THEN php symfony search:populate.

Treeview not loading: Clear browser cache. Check for JavaScript errors in console. May be caused by CSP (Content Security Policy) blocking inline scripts in AtoM 2.10.1+.

Generate slugs: php symfony propel:generate-slugs (regenerates all slugs from titles)."""
    },
]


def chunk_text(text, max_chars=500):
    """Split text into chunks at paragraph boundaries."""
    paragraphs = text.strip().split('\n\n')
    chunks = []
    current = ""
    for para in paragraphs:
        if len(current) + len(para) > max_chars and current:
            chunks.append(current.strip())
            current = para
        else:
            current = current + "\n\n" + para if current else para
    if current.strip():
        chunks.append(current.strip())
    return chunks


def main():
    print("Loading embedding model...")
    model = SentenceTransformer(MODEL_NAME)
    client = QdrantClient(url=QDRANT_URL)

    points = []
    for doc in DOCS:
        # Split large docs into chunks
        chunks = chunk_text(doc["content"], max_chars=600)
        for i, chunk in enumerate(chunks):
            # Create searchable text: heading + chunk
            search_text = f"{doc['heading']}: {chunk}"
            embedding = model.encode(search_text).tolist()

            point_id = str(uuid.uuid5(uuid.NAMESPACE_URL, f"{doc['source_file']}#{i}"))

            points.append(PointStruct(
                id=point_id,
                vector=embedding,
                payload={
                    "question": doc["heading"],
                    # #49: chunk passes through shared redactor
                    "answer": redact_secrets(chunk),
                    "heading": doc["heading"],
                    "url": doc["url"],
                    "source": "heratio",
                    "source_file": doc["source_file"],
                    "category": doc["category"],
                    "year": "2024",
                    "reply_count": 0,
                }
            ))

    print(f"Ingesting {len(points)} chunks into {COLLECTION}...")
    # Batch upsert
    batch_size = 50
    for i in range(0, len(points), batch_size):
        batch = points[i:i+batch_size]
        client.upsert(collection_name=COLLECTION, points=batch)
        print(f"  Uploaded {min(i+batch_size, len(points))}/{len(points)}")

    # Verify
    info = client.get_collection(COLLECTION)
    print(f"\nDone! {COLLECTION} now has {info.points_count} points.")


if __name__ == "__main__":
    main()
