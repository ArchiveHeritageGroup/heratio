"""
ingest.py - Ingest scraped Google Groups threads into Qdrant vector DB.

Reads all_threads.json, chunks messages, embeds with all-MiniLM-L6-v2,
and upserts into Qdrant collection 'km_threads'.

Usage:
    python ingest.py                      # Ingest all
    python ingest.py --reset              # Drop + recreate collection first
    python ingest.py --file threads.json  # Use specific file
"""

import argparse
import json
import hashlib
import re
import sys
from pathlib import Path

from qdrant_client import QdrantClient
from qdrant_client.models import (
    Distance, VectorParams, PointStruct,
    Filter, FieldCondition, MatchValue,
)
from sentence_transformers import SentenceTransformer

QDRANT_URL   = "http://localhost:6333"
COLLECTION   = "km_threads"
VECTOR_SIZE  = 384
MODEL_NAME   = "all-MiniLM-L6-v2"
CHUNK_MAX    = 512   # max chars per chunk (roughly)
BATCH_SIZE   = 100


def make_chunk_id(thread_id: str, msg_idx: int, chunk_idx: int) -> str:
    raw = f"{thread_id}:{msg_idx}:{chunk_idx}"
    return hashlib.md5(raw.encode()).hexdigest()


# #49 follow-up: km_threads is populated from public AtoM Google Groups
# threads, which routinely contain user-posted credentials. Use the shared
# redactor so a regex tightening in /opt/ai/km/redact.py reaches every
# ingest script.
from redact import redact_secrets  # noqa: E402


def chunk_text(text: str, max_len: int = CHUNK_MAX) -> list[str]:
    """Split text into chunks at paragraph/sentence boundaries."""
    if len(text) <= max_len:
        return [text]

    chunks = []
    paragraphs = text.split("\n\n")
    current = ""

    for para in paragraphs:
        if len(current) + len(para) + 2 <= max_len:
            current = (current + "\n\n" + para).strip()
        else:
            if current:
                chunks.append(current)
            # If a single paragraph exceeds max, split by sentences
            if len(para) > max_len:
                sentences = re.split(r'(?<=[.!?])\s+', para)
                current = ""
                for sent in sentences:
                    if len(current) + len(sent) + 1 <= max_len:
                        current = (current + " " + sent).strip()
                    else:
                        if current:
                            chunks.append(current)
                        current = sent[:max_len]
            else:
                current = para

    if current:
        chunks.append(current)

    return chunks if chunks else [text[:max_len]]


def process_threads(threads: list[dict]) -> list[dict]:
    """Convert threads+messages into flat list of chunks with metadata."""
    records = []

    for thread in threads:
        tid     = thread.get("thread_id", "")
        subject = thread.get("subject", "").strip()
        url     = thread.get("url", "")
        msgs    = thread.get("messages") or []

        if not msgs:
            continue

        for msg_idx, msg in enumerate(msgs):
            body   = (msg.get("body") or "").strip()
            author = (msg.get("author") or "").strip()
            date   = (msg.get("date") or "").strip()

            if not body or len(body) < 10:
                continue

            # Prepend subject as context for embeddings
            full_text = f"Subject: {subject}\n\n{body}" if subject else body
            full_text = redact_secrets(full_text)  # #49 follow-up
            chunks = chunk_text(full_text)

            for chunk_idx, chunk in enumerate(chunks):
                records.append({
                    "id":         make_chunk_id(tid, msg_idx, chunk_idx),
                    "text":       chunk,
                    "thread_id":  tid,
                    "subject":    subject,
                    "author":     author,
                    "date":       date,
                    "url":        url,
                    "msg_idx":    msg_idx,
                    "chunk_idx":  chunk_idx,
                    "msg_count":  len(msgs),
                })

    return records


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", default="all_threads.json")
    ap.add_argument("--reset", action="store_true", help="Drop and recreate collection")
    args = ap.parse_args()

    # Load threads
    path = Path(args.file)
    if not path.exists():
        print(f"[!] File not found: {args.file}")
        sys.exit(1)

    threads = json.loads(path.read_text())
    with_msgs = [t for t in threads if t.get("messages")]
    print(f"[1] Loaded {len(threads)} threads, {len(with_msgs)} have messages")

    # Chunk
    print("[2] Chunking messages...")
    records = process_threads(with_msgs)
    print(f"    {len(records)} chunks created")

    if not records:
        print("[!] No records to ingest.")
        sys.exit(0)

    # Connect to Qdrant
    client = QdrantClient(url=QDRANT_URL)

    if args.reset:
        print("[3] Resetting collection...")
        try:
            client.delete_collection(COLLECTION)
        except:
            pass

    # Create collection if not exists
    collections = [c.name for c in client.get_collections().collections]
    if COLLECTION not in collections:
        print(f"[3] Creating collection '{COLLECTION}'...")
        client.create_collection(
            collection_name=COLLECTION,
            vectors_config=VectorParams(size=VECTOR_SIZE, distance=Distance.COSINE),
            on_disk_payload=True,
        )
    else:
        info = client.get_collection(COLLECTION)
        print(f"[3] Collection '{COLLECTION}' exists ({info.points_count} points)")

    # Load model
    print(f"[4] Loading embedding model ({MODEL_NAME})...")
    model = SentenceTransformer(MODEL_NAME)

    # Embed and upsert in batches
    print(f"[5] Embedding and upserting {len(records)} chunks...")
    for i in range(0, len(records), BATCH_SIZE):
        batch = records[i:i + BATCH_SIZE]
        texts = [r["text"] for r in batch]
        embeddings = model.encode(texts, show_progress_bar=False)

        points = []
        for rec, emb in zip(batch, embeddings):
            points.append(PointStruct(
                id=rec["id"],
                vector=emb.tolist(),
                payload={
                    "text":       rec["text"],
                    "thread_id":  rec["thread_id"],
                    "subject":    rec["subject"],
                    "author":     rec["author"],
                    "date":       rec["date"],
                    "url":        rec["url"],
                    "msg_idx":    rec["msg_idx"],
                    "chunk_idx":  rec["chunk_idx"],
                    "msg_count":  rec["msg_count"],
                },
            ))

        client.upsert(collection_name=COLLECTION, points=points)

        done = min(i + BATCH_SIZE, len(records))
        if done % 500 == 0 or done == len(records):
            print(f"    {done}/{len(records)} chunks ingested")

    info = client.get_collection(COLLECTION)
    print(f"\n[OK] Done. Collection '{COLLECTION}' has {info.points_count} points.")


if __name__ == "__main__":
    main()
