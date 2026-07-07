# CBOR-LD 1.0 — Reference Summary

> W3C Working Draft, published **2 July 2026** (JSON-LD Working Group). Experimental, Recommendation-track, subject to change. Source: https://www.w3.org/TR/cbor-ld-10/

## What it is

A **CBOR-based binary serialization of Linked Data** that reuses the existing JSON-LD ecosystem. Through *semantic compression* it can beat generic compressors by **60%+** on typical Linked Data documents, while staying fully **round-trippable** to/from JSON-LD.

## Why it exists (use cases)

1. **Storage- and bandwidth-constrained environments** — Linked Data on IoT / edge / constrained runtimes.
2. **Semantic wire-level protocols** — interoperable systems exchanging compact structured data.
3. **CBOR-based storage engines** — persisting Linked Data efficiently in binary.

Design balance: simplicity (buildable on existing JSON-LD libs), efficiency (aggressive compaction), generality, and semantic optimisation. Deliberately **rejected**: CBOR-LD-specific expansion/compaction algorithms, large centralised term registries, and anything that breaks lossless round-trip.

## How the compression works (three independent strategies)

### 1. Semantic compression (term → integer)
- Parses the JSON-LD **`@context`** to build a term-to-byte map.
- Only **external (URI) contexts** are compressible; embedded/inline contexts stay uncompressed.
- Terms are sorted in **Unicode code-point order**, then assigned integer IDs incrementally (deterministic, reproducible).
- Encoding replaces JSON-LD keys with their integer IDs; decoding reverses it.
- Standard JSON-LD keywords get **reserved IDs 0–99** (e.g. `@context`→0, `@type`→2, `@id`→4, `@value`→6); custom terms start at **100**.

### 2. Typed-value compression (codecs)
- Codecs compress values using *a priori* type knowledge — e.g. stripping the scheme from URLs, encoding `@id`/`@type`/`@vocab` as URLs, converting integers to raw bytes when the type is in `typesEncodedAsBytes`.

### 3. Registry dictionary compression
- Static, use-case-specific compression dictionaries defined in **CBOR-LD Registry Entries** (`typeTables`).

Implementations may mix any combination of the three.

## Wire format

- Identified by CBOR tag **`0xCB1D` (51997)**, IANA-registered.
- Structure is a **two-element array**: `tag([registryEntryId, payload])`.
- `registryEntryId` (a CBOR integer) points at a **CBOR-LD Registry Entry** that carries:
  - use-case classification,
  - `typeTables` (value-compression dictionaries),
  - `processingModel` (which compression/codecs apply),
  - a `provisional` stability flag.
- The **global registry** is maintained at the W3C JSON-LD Community Group level.

## Encoding (JSON-LD → CBOR-LD)
1. Look up the CBOR tag structure for the registry entry ID.
2. Init conversion state (strategy, type tables, entry ID).
3. If the entry needs semantic compression: process contexts + convert the document.
4. CBOR-encode the transformed output.
5. Prepend the tag structure → final payload.

## Decoding (CBOR-LD → JSON-LD)
1. Read the registry entry ID from the CBOR tag.
2. Load type + reverse-type tables.
3. Init decompression state.
4. Re-apply context processing to reconstruct JSON-LD.
5. Return the document. (If semantic compression wasn't used, it's just plain CBOR in/out.)

## Context / term processing (the JSON-LD scoping rules it honours)
- **Initialize / Apply Embedded Contexts** — build the active context from external + inline contexts.
- **Property-scoped** and **Type-scoped** contexts — conditional context application.
- **Update / Revert Term Map** — merge new definitions respecting **protected terms** and propagation; restore on exiting a scope.
- **Context loader** caches contexts, assigns term IDs from 100 up, handles `@import`.

## Modes
- **Compressed (default):** semantic term mapping + value codecs + type tables.
- **Uncompressed:** direct CBOR encoding of the JSON-LD, bypassing term/codec processing — used when compression overhead outweighs the benefit.
- Both are fully round-trip faithful.

## Conformance / security / privacy
- RFC 2119/8174 keywords; intros, examples, notes are non-normative.
- Implementations must handle tag validation, term-ID mapping, reversible (de)compression, scoped contexts, protected-term constraints.
- Decoders throw `ERR_UNKNOWN_COMPRESSED_VALUE` on a failed integer lookup.
- **Security** and **Privacy** sections exist but are thin in this draft — flagged as needing implementer analysis for untrusted payloads.

## Reference implementation
Digital Bazaar ships a reference implementation covering all features (doubles as a conformance baseline).

---

## Why this matters for AHG / Heratio (OpenRiC angle)

Your OpenRiC post (#6) frames "an IIIF-style contract for archival linked data." CBOR-LD is directly relevant if you ever want:
- **compact, signable** linked-data payloads for archival records / verifiable credentials (60%+ smaller than JSON-LD text, so cheaper to store, sign, and transmit),
- a **binary wire format** for record exchange that is still losslessly JSON-LD,
- efficient linked-data on constrained or high-volume ingestion paths.

Caveat: it's an early **Working Draft** (Jul 2026) — good to track and prototype against, not yet stable to build a production contract on.

**Sources:**
- W3C TR: https://www.w3.org/TR/cbor-ld-10/
- News announcement: https://www.w3.org/news/2026/first-public-working-draft-cbor-ld-1-0/
- Editor's draft: https://w3c.github.io/cbor-ld/
- Repo + reference impl: https://github.com/w3c/cbor-ld
