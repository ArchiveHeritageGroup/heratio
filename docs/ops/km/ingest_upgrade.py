#!/usr/bin/env python3
"""Ingest AtoM 2.10 upgrade documentation into km_heratio."""

import uuid
from qdrant_client import QdrantClient
from qdrant_client.models import PointStruct
from sentence_transformers import SentenceTransformer
from redact import redact_secrets  # #49: shared redaction floor

QDRANT_URL = "http://localhost:6333"
COLLECTION = "km_heratio"
MODEL_NAME = "all-MiniLM-L6-v2"

DOCS = [
    {
        "heading": "How to Upgrade to AtoM 2.10 - Complete Guide",
        "category": "admin",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/installation/upgrading/",
        "content": """Upgrading to AtoM 2.10 - Complete Step-by-Step Guide:

Prerequisites: AtoM 2.10 requires PHP 8.x (8.1, 8.2, or 8.3), MySQL 8.0, Elasticsearch 7.10, Java 8, Ubuntu 24.04 LTS. Review release notes before upgrading.

Step 1 - Fresh Installation: Install AtoM 2.10 following the standard installation docs for your OS. Create a NEW database - do NOT reuse the old one or the web installer will erase your data.

Step 2 - Transfer uploads:
rsync -av /var/www/old_atom/uploads/ /usr/share/nginx/atom/uploads/
rsync -av /var/www/old_atom/downloads/ /usr/share/nginx/atom/downloads/
Optionally clean job files: rm -f /usr/share/nginx/atom/downloads/jobs/*

Step 3 - Database Migration:
Dump old database: mysqldump -u username -p atom > /tmp/atom_db.sql
For upgrades from 2.5.x or lower, recreate with utf8mb4:
mysql -u username -p -e "DROP DATABASE IF EXISTS atom;"
mysql -u username -p -e "CREATE DATABASE atom CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
Import: mysql -u username -p atom < /tmp/atom_db.sql

Step 4 - Run upgrade SQL:
cd /usr/share/nginx/atom
php -d memory_limit=-1 symfony tools:upgrade-sql

Step 5 - Restore custom configuration (session timeouts, API keys, etc.)

Step 6 - Regenerate derivatives (if upgrading from 1.3.1 or earlier):
php symfony digitalobject:regen-derivatives
For video files (from 2.6.2 or earlier - replaces FLV with MP4):
php symfony digitalobject:regen-derivatives --media-type=video

Step 7 - Rebuild search index and clear cache:
php -d memory_limit=-1 symfony search:populate
php symfony cc

Step 8 - Restart services:
sudo systemctl restart php8.3-fpm
sudo systemctl restart memcached
sudo systemctl restart atom-worker

Step 9 - Configure site base URL in Admin > Settings > Site information.

Step 10 - If using custom theme, recompile:
npm install && npm run build
php symfony cc && sudo systemctl restart php8.3-fpm

Verify all data migrated correctly before going live."""
    },
    {
        "heading": "Upgrading AtoM from 2.7/2.8/2.9 to 2.10",
        "category": "admin",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/installation/upgrading/",
        "content": """Upgrading from AtoM 2.7.x, 2.8.x, or 2.9.x to AtoM 2.10:

Key changes in AtoM 2.10:
- PHP requirement: PHP 8.x (8.1, 8.2, 8.3). PHP 7 is NOT supported since AtoM 2.9.1.
- MySQL 8.0 required (MySQL 5.7 no longer supported)
- Elasticsearch 7.x required (ES 5.x no longer supported)
- Ubuntu 24.04 LTS (Noble Numbat) is the recommended OS
- CSP (Content Security Policy) enforced by default in 2.10.1

Upgrade steps summary:
1. Backup your database: mysqldump -u root -p atom > atom_backup.sql
2. Backup uploads directory
3. Install fresh AtoM 2.10 on Ubuntu 24.04 with PHP 8.3, MySQL 8.0, ES 7.10
4. Import old database into new installation
5. Run: php -d memory_limit=-1 symfony tools:upgrade-sql
6. Run: php -d memory_limit=-1 symfony search:populate
7. Clear cache: php symfony cc
8. Restart services: sudo systemctl restart php8.3-fpm nginx

If upgrading from AtoM 2.7 or earlier on Ubuntu 20.04:
- You must upgrade to Ubuntu 24.04 first (or do a fresh Ubuntu 24.04 install)
- Install PHP 8.3, MySQL 8.0, Elasticsearch 7.10 on the new OS
- Then migrate the database and uploads

Common upgrade issues:
- PHP 7 incompatibility: AtoM 2.10 will not work with PHP 7. Must use PHP 8.x.
- MySQL 5.7 charset: If upgrading database from MySQL 5.7, convert to utf8mb4.
- Elasticsearch reindex: Always rebuild the search index after upgrade.
- Custom themes: Must recompile after upgrade (npm install && npm run build)."""
    },
    {
        "heading": "AtoM 2.10 Installation on Ubuntu 24.04 LTS",
        "category": "admin",
        "url": "https://www.accesstomemory.org/en/docs/2.10/admin-manual/installation/ubuntu/",
        "content": """Installing AtoM 2.10 on Ubuntu 24.04 LTS (Noble Numbat):

System requirements: 2 vCPUs, 7GB RAM minimum, 50GB disk. Ubuntu 24.04 LTS.

Key packages to install:
- PHP 8.3 with extensions: php8.3-fpm php8.3-cli php8.3-curl php8.3-json php8.3-mysql php8.3-xsl php8.3-zip php8.3-mbstring php8.3-xml php8.3-opcache php8.3-apcu
- MySQL 8.0: mysql-server
- Elasticsearch 7.10: Install from Elastic APT repository
- Java 8: openjdk-8-jre-headless (required for Elasticsearch)
- Nginx web server
- Gearman job server: gearman-job-server php8.3-gearman
- Other: git, ImageMagick, Ghostscript, FFmpeg, poppler-utils

Database setup:
mysql -u root -e "CREATE DATABASE atom CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -e "CREATE USER 'atom'@'localhost' IDENTIFIED BY 'password';"
mysql -u root -e "GRANT ALL ON atom.* TO 'atom'@'localhost';"

AtoM installation:
cd /usr/share/nginx
git clone -b stable/2.10.x https://github.com/artefactual/atom.git atom
cd atom
composer install --no-dev
npm install && npm run build

Nginx configuration: Configure server block with PHP-FPM upstream.

After web installer completes:
php symfony cc
sudo systemctl restart php8.3-fpm
php -d memory_limit=-1 symfony search:populate

The atom-worker service handles background jobs:
sudo systemctl enable atom-worker
sudo systemctl start atom-worker"""
    },
]


def chunk_text(text, max_chars=600):
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
        chunks = chunk_text(doc["content"], max_chars=600)
        for i, chunk in enumerate(chunks):
            search_text = f"{doc['heading']}: {chunk}"
            embedding = model.encode(search_text).tolist()
            point_id = str(uuid.uuid5(uuid.NAMESPACE_URL, f"{doc['url']}#upgrade#{i}"))
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
                    "source_file": doc["url"],
                    "category": doc["category"],
                    "year": "2024",
                    "reply_count": 0,
                }
            ))

    print(f"Ingesting {len(points)} chunks...")
    client.upsert(collection_name=COLLECTION, points=points)
    info = client.get_collection(COLLECTION)
    print(f"Done! {COLLECTION} now has {info.points_count} points.")


if __name__ == "__main__":
    main()
