AI Governance & Sovereignty design doc for Heratio

Summary

This design doc lays out an actionable, deployable AI Governance & Sovereignty plan for Heratio. It maps recommendations from Peter van Garderen's "Sovereign / Local AI for records management" to Heratio's existing architecture and code, identifies gaps, and prescribes practical workstreams: documentation, small prototypes (PoC), UI surfaces, and ops runbooks. The goal: enable organisations to run AI over records with provable provenance, local-first sovereignty, legal defensibility (POPIA/GDPR), and operational controls for RAG and model use.

Background & Motivation

- Heratio already provides strong foundations: LLM orchestration (packages/ahg-ai-services/src/Services/LlmService.php), inference recording and RDF-Star provenance (packages/ahg-provenance-ai/src/Services/InferenceService.php), and provenance services (packages/ahg-provenance/src/Services/ProvenanceService.php).
- The LinkedIn article (Peter van Garderen) advocates for local/sovereign AI, default audit trails, human-in-the-loop, cryptographic provenance, TEEs, and open standards like C2PA and OriginTrail. This doc proposes a map from that guidance to concrete Heratio deliverables.

Goals

1. Document deployment modes and operator responsibilities (local, hybrid, cloud).
2. Provide an AI Inventory & governance dashboard to make AI assets visible and manageable.
3. Strengthen provenance capture with cryptographic signatures and exportable manifests (C2PA/JSON-LD/OriginTrail prototype).
4. Add RAG guardrails and corporate-ontology enforcement for purpose limitation and hallucination detection.
5. Publish an ops runbook for air-gapped/TEE-capable deployments and for routine audits and updates.

1) Deployment modes and recommended operator controls

- Local (on-prem / air-gapped):
  - Models run in-host via Ollama or operator-provided model-serving stack.
  - No outbound network by default. Model weight updates delivered via signed artifacts on approved channels.
  - Use local triplestore (Fuseki), local Elasticsearch, and local vector DBs (Qdrant) in same trust domain.
  - Recommended: hardware attestation and an approved KMS for signing keys.

- Hybrid (trusted cloud for heavy models + local narrow models):
  - Sensitive PII/POPIA-covered content is processed only by local models or in a trusted enclave.
  - Non-sensitive enrichment (e.g., public-language summarisation) can call a vetted cloud provider with logging and masking.
  - Network egress policy enforced by policy service (see AI Inventory).

- Cloud-first: (only for non-sensitive workloads)
  - Full audit logging mandatory; operators must enable inference-record export and retention policies.

Operator responsibilities (all modes):
- Maintain an AI Inventory (see below).
- Maintain signer keys and rotate per policy.
- Configure confidence thresholds for human-in-the-loop review.
- Approve model manifests and training provenance before deployment.

2) TEE / air-gapped best practices & attestation

- Guidance:
  - Provide an "Air-gapped install checklist" (network, package mirror, model artifact signing, time sync, attestation keys).
  - Offer an opinionated hardware profile: 16–64 vCPU, 64–256 GB RAM, NVMe for model caching; GPU optional for HTR/vision.
  - For attested compute, document options: AWS Nitro Enclaves, Intel SGX (where available), AMD SEV, or vendor appliances with attestation APIs.
  - Workflow for TEE attestation: provision enclave → operator obtains attestation report → record attestation report into provenance store (RDF + signature) tied to model_manifest.

- Practical notes:
  - Start with attested model manifest capture (signed model_manifest.json) + signed inference manifests before attempting full TEE automation.

3) Required hardware profiles (opinionated)

- Small / research node:
  - 8 vCPU, 32 GB RAM, 1 TB NVMe, optional small GPU (T4).
  - Use for small deployments, dev/test.

- Production archival node (recommended minimal):
  - 16–32 vCPU, 128 GB RAM, 2–4 TB NVMe, 2x 10 GbE, GPU optional (A10/T4) for HTR.
  - Local Qdrant + Elasticsearch co-located.

- High-throughput / models + HTR:
  - 32+ vCPU, 256+ GB RAM, multi-GPU (A100 / V100 class) for heavy OCR/HTR batch jobs.

4) POPIA / GDPR compliance checklist (operator checklist)

- Data minimisation: only send retrieval pieces needed; redact PII before RAG retrievals.
- Purpose limitation: configure process-specific policies per ai configuration. Log purpose and retain policy in inference record.
- Human-in-the-loop: require manual review for inferences below confidence threshold; store review outcome.
- Retention & deletion: inference artifacts retention policy; support export & purge of inference records on subject request.
- Transparency & notice: surface AI usage in public-facing UI and researcher-facing metadata where appropriate.
- DPIA: require DPIA for any project that uses personal data with models outside the trust boundary.

5) AI Inventory & Governance Dashboard (spec)

- Data sources:
  - ahg_llm_config rows (models/configs)
  - ahg_ai_inference usage metrics (counts, timestamps, confidence distribution)
  - Provider endpoints and processingMode (local/hybrid/cloud)
  - Model manifests (model_manifest JSON field capturing checkpoint URI, hash, training provenance ids)
  - Active keys and signer_key_id (KMS pointer)

- UI surfaces:
  - Overview: counts of models, inferences/day, flagged low-confidence inferences, pending reviews.
  - Model catalogue: model name/version, manifest summary, trust zone (local/hybrid/cloud), signed? yes/no, last attestation timestamp.
  - Inference audit browser: query by researcher, record id, time range; view full manifest, input hash, output hash, signature, RDF-Star annotations.
  - Policy manager: per-config policy for purpose, allowed_data_scopes (e.g., exclude 'personal_identifiable'), required confidence thresholds, review flows.
  - Export & Compliance: export inference exports (signed) for auditors; on-demand C2PA/JSON-LD manifest export.

- API endpoints required (back-end):
  - GET /api/governance/models
  - GET /api/governance/inferences
  - POST /api/governance/models/:id/approve-manifest
  - POST /api/governance/inferences/:id/sign

6) Signing manifests, C2PA & OriginTrail integration (design)

- Minimal viable signing (PoC):
  - On InferenceService::record(), assemble canonical manifest:
    {
      "id": inference_uuid,
      "timestamp": iso8601,
      "input_hash": "sha256:...",
      "output_hash": "sha256:...",
      "model_manifest": { ... },
      "provider": "ollama|openai|...",
      "confidence": 0.72
    }
  - Compute an Ed25519 signature over a canonical serialization (e.g., compacted JSON-LD or canonical JSON). Store signature and signer_key_id in ahg_ai_inference and in RDF graph (signature triple).

- C2PA / OriginTrail prototype:
  - Provide an export button that produces:
    - C2PA-like signed manifest (where feasible) OR
    - JSON-LD with provenance triples that maps to existing RDF-Star inference graph.
  - Map fields:
    - subject → record URIs included
    - creator → Heratio instance id, user id
    - assertion → inference manifest + signature
  - Optionally register the manifest hash on an external anchor service (operator decision) for long-term tamper evidence.

7) RAG guardrails & corporate ontology enforcement

- RAG middleware responsibilities (executed before model call):
  - Filter retrieval candidates by policy (allowed_data_scopes). For sensitive requests, block cloud retrieval.
  - Annotate retrieval items with trust_score = f(source_authority, digital_signatures, embargo_state).
  - Include provenance bundle with retrieval pieces to the model (not raw documents) so the model output can reference sources.
  - After model returns, run ontology-checker: verify that entities/relations mentioned align with corporate ontology (RiC mapping). Flag violations as potential hallucinations.

- Implementation notes:
  - Reuse existing RiC triplestore and thesaurus services for mapping ontological terms.
  - Add a middleware class in LlmService pipeline to apply policies and attach provenance.

8) Model provenance capture & manifest fields (schema proposal)

- model_manifest JSON keys (store with inference record and model registry):
  - model_id, model_name, model_version
  - checkpoint_uri (signed URL or local path)
  - artifact_hash (sha256)
  - training_dataset_ids (list of dataset ids / checksum references)
  - fine_tune_info: { base_model:..., method: "LoRA|full-finetune|adapter", fine_tune_ids: [...] }
  - tokenizer_id + tokenizer_hash
  - declared_capabilities (e.g., classification, summarization)
  - attestation: { attestation_report_uri, attestation_hash }
  - publisher / owner

9) Attestation, tamper evidence & ledgering

- Short-term: store signatures and optionally anchor manifest hashes to an operator-chosen external anchor (git commit, signed timestamp authority, or blockchain anchor) as an optional workflow.
- Mid-term: implement Merkle-tree-backed append-only store for inference manifests to allow efficient proof-of-inclusion for auditors.

10) Operational runbook (updates, audits, key rotation)

- Key Management:
  - Use operator KMS (cloud HSM or on-prem KMS). Store only signer_key_id in DB; do not store private keys in app config.
  - Rotation schedule: annually or on compromise.

- Model updates:
  - Require manifest approval in governance dashboard. Record approval event in provenance store.
  - Signed model_artifact and signed manifest required for production deploys.

- Audits:
  - Support "replay mode" — given a time range and inference ids, export canonical manifests and RDF-Star bundles for auditor consumption.
  - Regular audit checklist: model_manifest completeness, signature presence, attestation reports present for TEEs, policy enforcement logs.

11) Implementation roadmap & immediate PoCs

- Immediate (1–2 sprints)
- Draft this design doc in repo (issue created) — DONE.
- Add model_manifest JSON column to ahg_llm_config or ahg_ai_inference (DB migration PR).
- PoC: Ed25519 signing of inference manifest in InferenceService::record() and store signature + signer_key_id.
- Dashboard stub: UI page listing LLM configs and last-used timestamps.

Medium (1–3 months)
- C2PA/JSON-LD export prototype and RDF mapping.
- RAG middleware enforcing allowed_data_scopes and attaching provenance bundles.
- AI Inventory UI: search, filter, export.

Strategic (3–9 months)
- TEE deployment playbooks and automation.
- Merkle append-only ledger and optional external anchoring.
- Full attestation automation and audit tools.

References (repo files inspected)
- packages/ahg-ai-services/src/Services/LlmService.php
- packages/ahg-provenance-ai/src/Services/InferenceService.php
- packages/ahg-provenance/src/Services/ProvenanceService.php

Next steps / Proposed GitHub tasks
1. Add DB migration to store model_manifest on inference and/or model config.
2. PoC: implement Ed25519 signing in InferenceService::record() and tests.
3. UI: AI Inventory page (backend endpoints + frontend blade/vue).
4. Docs: Air-gapped & TEE install checklist and operator playbook.
5. Prototype: C2PA/JSON-LD export and mapping job.

---

Please review. If you're happy, I'll create GitHub issues for the top-priority tasks (1–3) and open PR stubs or prototypes for the PoC signing in packages/ahg-provenance-ai.
