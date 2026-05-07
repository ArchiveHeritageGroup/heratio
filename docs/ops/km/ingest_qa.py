"""
ingest_qa.py - Ingest Q&A pairs into Qdrant for semantic search.

Embeds questions for search matching, stores answers as payload.

Usage:
    python ingest_qa.py                  # Ingest qa_pairs.json
    python ingest_qa.py --reset          # Drop + recreate collection
"""

import argparse
import json
import sys
from pathlib import Path

from qdrant_client import QdrantClient
from qdrant_client.models import Distance, VectorParams, PointStruct
from sentence_transformers import SentenceTransformer
from redact import redact_secrets  # #49: shared redaction floor

QDRANT_URL  = "http://localhost:6333"
COLLECTION  = "km_qa"
VECTOR_SIZE = 384
MODEL_NAME  = "all-MiniLM-L6-v2"
BATCH_SIZE  = 100


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", default="qa_pairs.json")
    ap.add_argument("--reset", action="store_true")
    args = ap.parse_args()

    qa = json.loads(Path(args.file).read_text())
    print(f"[1] Loaded {len(qa)} Q&A pairs")

    client = QdrantClient(url=QDRANT_URL)

    if args.reset:
        try:
            client.delete_collection(COLLECTION)
        except:
            pass

    collections = [c.name for c in client.get_collections().collections]
    if COLLECTION not in collections:
        print(f"[2] Creating collection '{COLLECTION}'...")
        client.create_collection(
            collection_name=COLLECTION,
            vectors_config=VectorParams(size=VECTOR_SIZE, distance=Distance.COSINE),
            on_disk_payload=True,
        )
    else:
        info = client.get_collection(COLLECTION)
        print(f"[2] Collection exists ({info.points_count} points)")

    print(f"[3] Loading model...")
    model = SentenceTransformer(MODEL_NAME)

    # Embed the QUESTION text - that's what users will search against
    print(f"[4] Embedding and upserting...")
    for i in range(0, len(qa), BATCH_SIZE):
        batch = qa[i:i + BATCH_SIZE]
        # Embed the short question for matching
        texts = [q["question"] for q in batch]
        embeddings = model.encode(texts, show_progress_bar=False)

        points = []
        for rec, emb in zip(batch, embeddings):
            points.append(PointStruct(
                id=abs(hash(rec["id"])) % (2**63),  # Qdrant needs int or uuid
                vector=emb.tolist(),
                payload={
                    # #49: every text-bearing field passes through redact_secrets
                    "question":      redact_secrets(rec["question"]),
                    "full_question": redact_secrets(rec["full_question"]),
                    "answer":        redact_secrets(rec["answer"]),
                    "url":           rec["url"],
                    "reply_count":   rec["reply_count"],
                    "has_answer":    rec["has_answer"],
                    "thread_id":     rec["id"],
                    "date":          rec.get("date", ""),
                    "year":          rec.get("year", ""),
                },
            ))

        client.upsert(collection_name=COLLECTION, points=points)
        done = min(i + BATCH_SIZE, len(qa))
        if done % 500 == 0 or done == len(qa):
            print(f"    {done}/{len(qa)}")

    info = client.get_collection(COLLECTION)
    print(f"\n[OK] {info.points_count} Q&A pairs indexed in '{COLLECTION}'")


if __name__ == "__main__":
    main()
