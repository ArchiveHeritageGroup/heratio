"""
ingest_atom_docs.py - Ingest scraped AtoM 2.10 docs into Qdrant.

Reads atom_docs.json (output of scrape_atom_docs.py), chunks by headings,
embeds, and upserts into km_heratio collection.

Usage:
    python ingest_atom_docs.py                  # Ingest atom_docs.json
    python ingest_atom_docs.py --file other.json
    python ingest_atom_docs.py --collection km_atom_docs  # Use separate collection
    python ingest_atom_docs.py --dry-run        # Show stats without ingesting
"""

import argparse
import hashlib
import json
import re
import sys
from pathlib import Path

from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct
from sentence_transformers import SentenceTransformer
from redact import redact_secrets  # #49: shared redaction floor

QDRANT_URL = "http://localhost:6333"
COLLECTION = "km_heratio"  # Same collection as Heratio docs
VECTOR_SIZE = 384
MODEL_NAME = "all-MiniLM-L6-v2"
BATCH_SIZE = 100


def chunk_by_headings(text: str, max_len: int = 600) -> list[dict]:
    """Split text by markdown headings into semantic chunks."""
    chunks = []
    sections = re.split(r"\n(#{1,4}\s+.+)\n", text)

    current_heading = ""
    current_text = ""

    for part in sections:
        part = part.strip()
        if not part:
            continue

        if re.match(r"^#{1,4}\s+", part):
            if current_text and len(current_text) > 30:
                chunks.append({"heading": current_heading, "text": current_text.strip()})
            current_heading = part.lstrip("#").strip()
            current_text = ""
        else:
            current_text += "\n" + part

    if current_text and len(current_text) > 30:
        chunks.append({"heading": current_heading, "text": current_text.strip()})

    # Split oversized chunks by paragraphs
    final = []
    for chunk in chunks:
        text = chunk["text"]
        if len(text) <= max_len:
            final.append(chunk)
        else:
            paragraphs = text.split("\n\n")
            current = ""
            for para in paragraphs:
                if len(current) + len(para) + 2 <= max_len:
                    current = (current + "\n\n" + para).strip()
                else:
                    if current:
                        final.append({"heading": chunk["heading"], "text": current})
                    current = para[:max_len] if len(para) > max_len else para
            if current:
                final.append({"heading": chunk["heading"], "text": current})

    return final


def make_id(url: str, heading: str, idx: int) -> str:
    """Create deterministic ID for upsert idempotency."""
    raw = f"atom_docs:{url}:{heading}:{idx}"
    return hashlib.md5(raw.encode()).hexdigest()


def main():
    ap = argparse.ArgumentParser(description="Ingest AtoM docs into Qdrant")
    ap.add_argument("--file", default="atom_docs.json", help="Input JSON file")
    ap.add_argument("--collection", default=COLLECTION, help="Qdrant collection name")
    ap.add_argument("--dry-run", action="store_true", help="Show stats without ingesting")
    ap.add_argument("--reset-collection", action="store_true",
                    help="Drop and recreate collection (WARNING: removes ALL data in collection)")
    args = ap.parse_args()

    # Load scraped data
    if not Path(args.file).exists():
        print(f"[ERROR] File not found: {args.file}")
        print("  Run scrape_atom_docs.py first to generate it.")
        sys.exit(1)

    data = json.loads(Path(args.file).read_text())
    pages = data.get("pages", [])
    ref_url = data.get("ref_url", "")
    print(f"[1] Loaded {len(pages)} pages from {args.file}")
    print(f"    Source: {data.get('source', 'unknown')}")
    print(f"    Ref URL: {ref_url}")
    print()

    # Chunk all pages
    all_chunks = []
    for page in pages:
        chunks = chunk_by_headings(page["text"])

        # If no heading-based chunks, treat entire page as one chunk
        if not chunks and len(page["text"]) > 50:
            chunks = [{"heading": page.get("title", ""), "text": page["text"][:2000]}]

        for chunk in chunks:
            all_chunks.append({
                "heading": chunk["heading"] or page.get("title", ""),
                # #49: redact at chunk-build time so payloads + embeddings agree
                "text": redact_secrets(chunk["text"]),
                "url": page["url"],
                "path": page.get("path", ""),
                "manual": page.get("manual", ""),
                "section": page.get("section", ""),
                "page_name": page.get("page_name", ""),
                "ref_url": ref_url,
            })

    print(f"[2] Chunked into {len(all_chunks)} segments")

    # Deduplicate
    seen = set()
    unique = []
    for c in all_chunks:
        key = c["text"][:200]
        if key not in seen:
            seen.add(key)
            unique.append(c)
    all_chunks = unique
    print(f"    After dedup: {len(all_chunks)} segments")

    # Stats by manual
    manual_counts = {}
    for c in all_chunks:
        m = c["manual"] or "Other"
        manual_counts[m] = manual_counts.get(m, 0) + 1
    for m, cnt in sorted(manual_counts.items()):
        print(f"    {m}: {cnt} chunks")
    print()

    if args.dry_run:
        print("[DRY RUN] No data ingested. Remove --dry-run to ingest.")
        # Show sample chunks
        print("\nSample chunks:")
        for c in all_chunks[:5]:
            print(f"  [{c['manual']}] {c['heading']}: {c['text'][:100]}...")
        return

    # Connect to Qdrant
    client = QdrantClient(url=QDRANT_URL)

    if args.reset_collection:
        print(f"[!] Resetting collection '{args.collection}'...")
        try:
            client.delete_collection(args.collection)
        except Exception:
            pass

    collections = [c.name for c in client.get_collections().collections]
    if args.collection not in collections:
        print(f"[3] Creating collection '{args.collection}'...")
        client.create_collection(
            collection_name=args.collection,
            vectors_config=VectorParams(size=VECTOR_SIZE, distance=Distance.COSINE),
            on_disk_payload=True,
        )
    else:
        info = client.get_collection(args.collection)
        print(f"[3] Collection '{args.collection}' exists ({info.points_count} points)")

    # Load embedding model
    print("[4] Loading embedding model...")
    model = SentenceTransformer(MODEL_NAME)

    # Embed and upsert in batches
    print(f"[5] Embedding and upserting {len(all_chunks)} chunks...")
    total_upserted = 0

    for i in range(0, len(all_chunks), BATCH_SIZE):
        batch = all_chunks[i : i + BATCH_SIZE]

        # Prepend heading + manual context for better embedding
        texts = []
        for c in batch:
            prefix = f"{c['manual']} - {c['heading']}" if c["heading"] else c["manual"]
            texts.append(f"{prefix}: {c['text']}" if prefix else c["text"])

        embeddings = model.encode(texts, show_progress_bar=False)

        points = []
        for idx, (chunk, emb) in enumerate(zip(batch, embeddings)):
            points.append(
                PointStruct(
                    id=make_id(chunk["url"], chunk["heading"], i + idx),
                    vector=emb.tolist(),
                    payload={
                        "text": chunk["text"],
                        "heading": chunk["heading"],
                        "source_file": chunk["url"],
                        "category": chunk["manual"].lower().replace(" ", "-"),
                        "section": chunk["section"],
                        "page_name": chunk["page_name"],
                        "source": "atom-docs",
                        "ref_url": chunk["ref_url"],
                    },
                )
            )

        client.upsert(collection_name=args.collection, points=points)
        total_upserted += len(points)

        done = min(i + BATCH_SIZE, len(all_chunks))
        if done % 200 == 0 or done == len(all_chunks):
            print(f"    {done}/{len(all_chunks)} chunks upserted")

    info = client.get_collection(args.collection)
    print(f"\n[OK] Upserted {total_upserted} AtoM doc chunks")
    print(f"     Collection '{args.collection}' now has {info.points_count} total points")
    print(f"     Ref URL: {ref_url}")


if __name__ == "__main__":
    main()
