# RiC Group Post - CBOR-LD 1.0 (DRAFT)

> Draft for the RiC community group. Review before posting. Not yet sent.

---

**CBOR-LD 1.0: a compact binary future for RiC-O data?**

The W3C JSON-LD Working Group has just published a First Public Working Draft of **CBOR-LD 1.0** (2 July 2026) - a binary CBOR serialization of Linked Data.

**First, what is CBOR?** CBOR (Concise Binary Object Representation, IETF RFC 8949) is, in plain terms, **"binary JSON"**. It stores the same kind of data as JSON - objects, arrays, numbers, text - but as compact bytes instead of readable text, so it is smaller and faster for machines to parse. It is a mature, widely-used standard (it underpins things like WebAuthn/passkeys and IoT messaging). **CBOR-LD** simply applies CBOR to *Linked Data*: it takes a JSON-LD document and encodes it as CBOR, adding semantic compression on top.

Why it's worth RiC's attention:

- RiC-O is already RDF/Linked Data, and most exchange today is JSON-LD text. CBOR-LD encodes that **same JSON-LD** as compact binary - reported **~60% smaller** than generic compression, and fully **round-trippable** back to JSON-LD.
- It compresses semantically (term-to-integer maps drawn from the `@context`), so a RiC-O record's repeated property IRIs collapse to bytes.
- Payloads are small enough to **sign and transmit cheaply** - useful for verifiable records, high-volume harvesting, and constrained environments.

Our read: **take notice, don't adopt yet.** It's an early Working Draft and will change. The sensible path is to keep RiC exchange on JSON-LD now, and revisit CBOR-LD as an *optional* wire format once it reaches Candidate Recommendation.

Curious whether others in the group see a role for a binary RiC-O serialization - or whether JSON-LD text is enough for archival exchange in practice.

Spec: https://www.w3.org/TR/cbor-ld-10/

---

Johan Pieterse
The Archive and Heritage Digital Commons Group
https://theahg.co.za
https://heratio.theahg.co.za
