> Heratio Help Center article. Category: AI & Automation / Operations.

# AI operations runbook - air-gapped deployment, attestation and audits

This runbook is for operators running Heratio's AI over records in a sovereign
or air-gapped deployment, and for anyone responsible for routine AI audits. It
covers the air-gapped install checklist, the TEE attestation workflow, the
routine audit procedures, and the model-update / key-rotation procedure.

It applies to **both** deployments - Heratio (the Laravel platform) and the
AtoM-AHG plugins. Where a command differs the runbook gives both; Heratio uses
`php artisan`, AtoM-AHG uses `php symfony`.

## Deployment modes

Heratio and the AtoM-AHG plugins are AI *clients* - they never host a model.
Every AI call leaves the application as an HTTP request. Three modes:

- **Local / air-gapped** - models run in-host on a GPU box inside the same
  trust domain (Ollama, or an operator-provided serving stack). No outbound
  internet. This runbook's default.
- **Hybrid** - sensitive content goes to local models; non-sensitive
  enrichment may call a vetted cloud provider, gated by the RAG guardrails.
- **Cloud-first** - only for non-sensitive workloads; full audit logging is
  mandatory.

The mode is a deployment choice, enforced by which providers exist in
`ahg_llm_config` and by the RAG guardrail policy (see below).

## 1. Air-gapped install checklist

Work through this before the deployment processes any records.

**Network isolation**
- [ ] No outbound internet egress from the application host by default.
- [ ] The only AI traffic permitted is to the local AI gateway / Ollama host
      on the LAN. All other egress is denied at the firewall.
- [ ] No cloud LLM providers (`openai`, `anthropic`) are configured in
      `ahg_llm_config`, or they are marked inactive.

**Local package mirror**
- [ ] Composer, npm and OS packages are installed from an internal mirror -
      no packagist / npmjs / distro-internet access at deploy time.
- [ ] Application code is delivered from an approved internal channel.

**Model artifacts**
- [ ] Model weights are delivered on approved media, with a checksum and a
      signature from the model publisher or the internal model registry.
- [ ] Verify the checksum and signature before loading the weights into
      Ollama. Record the artifact hash - it goes into the model manifest.

**Time synchronisation**
- [ ] The host clock is synced from an internal NTP source. Inference
      signatures and attestation reports carry timestamps; clock skew breaks
      signature-validity and attestation windows.

**Signing keys**
- [ ] Generate the inference-signing keypair on the host (see section 4).
- [ ] Store the private key per the operator KMS policy, or operator-held on
      the host. It is never placed in the database or in git.

**RAG guardrails**
- [ ] Set the guardrail mode for a sovereign deployment:
      Heratio - `rag_guardrail_mode` in `ahg_ner_settings`;
      AtoM-AHG - `rag_guardrail_mode` in `ahg_ai_settings` (feature `guardrails`).
      `block` is the strict choice; `mask` redacts PII on any cloud-bound
      prompt. In a fully air-gapped install with no cloud providers, `warn` is
      sufficient because no data leaves the trust domain.
- [ ] Review `rag_cloud_allowed_scopes` and `rag_sanctioned_purposes`.

**AI gateway**
- [ ] The AI gateway URL points at the LAN endpoint, not a public host.
- [ ] HTR is resolved from the `htr_url` setting (Heratio) / `app_htr_url`
      config (AtoM-AHG) - confirm it is the local gateway.

## 2. TEE attestation workflow

For deployments that run models inside a Trusted Execution Environment (AWS
Nitro Enclaves, Intel SGX, AMD SEV, or a vendor appliance with an attestation
API).

The workflow ties a hardware attestation to the model provenance:

1. **Provision the enclave** and load the verified model weights into it.
2. **Obtain the attestation report** from the enclave's attestation API.
3. **Record the attestation** into the provenance store: add an `attestation`
   block to the operator-curated **model manifest** on the `ahg_llm_config`
   row for that model -
   `{ "attestation_report_uri": "...", "attestation_hash": "sha256:..." }`.
4. From then on every inference's per-inference manifest carries that
   attestation (the per-inference manifest overlays the operator-curated one),
   and the Ed25519 signature over the canonical manifest covers it. An auditor
   verifying a signature is therefore also verifying the attestation pointer.

Pragmatic order: start with **signed model-manifest capture** (steps 3-4 - an
operator-maintained manifest plus signed inferences) before attempting full
TEE automation. That alone gives a defensible record of which attested model
produced which result.

## 3. Routine audit procedures

### The Governance dashboard

The AI Inventory & Governance dashboard is the audit surface: model inventory,
recent inferences, signed status, manifest coverage.

- Heratio: **Admin -> AI Inventory & Governance** (`/admin/governance`).
- AtoM-AHG: the AI menu **AI Governance** (`/ai/governance`).

Both are read-only and admin-gated.

### Verifying inference signatures

Each recorded inference is Ed25519-signed over a canonical manifest. Verifying
re-derives the manifest and checks the signature against the public key.

- AtoM-AHG: `php symfony ai-provenance:verify` (re-checks recorded rows;
  `--id=N` for one row, `--limit=N` to widen the batch; exits non-zero on any
  failure). Rows signed by a retired key are skipped with a note.
- Heratio: the Governance dashboard's **signed** column reads the real
  `signer_key_id`; signature verification is performed by `InferenceSigner`
  over the canonical manifest.

A failed verification means the manifest was altered after recording -
investigate immediately.

### Checking model manifests

On the dashboard, each inference carries a manifest key-count badge. A full
manifest (model id, version, artifact hash, attestation, publisher) means the
result is reproducible and auditable years later. Rows recorded before
manifests were introduced show an empty badge.

### Reviewing guardrail activity

Every LLM/RAG call carries a RAG-guardrail verdict (data scope, purpose,
PII-masking count, grounding score, flags). On the Heratio side the verdict is
persisted on `ahg_ai_inference.guardrail`. Review flagged inferences -
`data_scope_out_of_policy`, `purpose_not_sanctioned`, `low_grounding` - as part
of the routine audit.

### Exporting inference records (retention / FOIA / legal defensibility)

- Heratio: the JSON endpoints `GET /admin/governance/models` and
  `GET /admin/governance/inferences`; the per-record provenance trace
  `GET /api/v1/provenance/{entityType}/{id}/trace`; and the coverage endpoint
  `GET /api/v1/provenance/coverage?days=N`.
- AtoM-AHG: `GET /ai/governance/models` and `GET /ai/governance/inferences`.

These give the inventory and the inference log in machine-readable form for an
auditor or a freedom-of-information response.

### Keeping the provenance store complete

The RDF-Star / PROV-O writes to Fuseki are SQL-first: a row whose synchronous
Fuseki write failed keeps a NULL graph URI and is retried by the replay job.

- AtoM-AHG: schedule `php symfony ai-provenance:replay` on cron, every five
  minutes.
- Heratio: `php artisan ahg:provenance-ai:replay` is registered on the
  scheduler; confirm the Laravel scheduler cron is running.

### Suggested audit cadence

- **Weekly:** open the Governance dashboard; confirm the signed share is not
  dropping; skim guardrail flags.
- **Monthly:** run signature verification across the recent batch; confirm the
  replay job has no large backlog; spot-check model manifests.
- **On any model change:** see section 4.

## 4. Model-update and key-rotation procedure

### Applying a model update

1. Receive the new model weights on an approved channel, with a checksum and
   signature.
2. Verify the checksum and signature; record the new artifact hash.
3. Load the new weights into the local serving stack (Ollama).
4. Update the operator-curated **model manifest** on the `ahg_llm_config` row:
   new `model_version`, new `artifact_hash`, refreshed `attestation` if the
   model runs in a TEE.
5. Inferences from that point carry the new manifest; older inferences keep
   the manifest they were recorded with. The provenance record stays correct
   across the upgrade.

Require manifest review before a model update goes live. Do not apply unsigned
model artifacts in a sovereign deployment.

### Rotating the signing keypair

The inference-signing keypair should be rotated annually, or immediately on
suspected compromise.

1. Generate a fresh keypair:
   - Heratio: `php artisan ahg:provenance-ai:keygen --force`
   - AtoM-AHG: `php symfony ai-provenance:keygen --force`
2. New inferences are signed with the new key automatically.
3. **Retain every retired public key.** Signatures made with an old key still
   verify against that old public key - `ai-provenance:verify` skips rows
   signed by a key other than the current one, so keep the retired public keys
   for historical verification.
4. The private key is written to a gitignored directory -
   `storage/app/ai-signing/` (Heratio) or `data/ahg-ai-signing/` (AtoM-AHG) -
   never the database, never git. Run the keygen task as the web user so the
   web request can read the key.

## 5. Command quick reference

| Action | Heratio (Laravel) | AtoM-AHG (Symfony) |
|---|---|---|
| Generate signing keypair | `php artisan ahg:provenance-ai:keygen` | `php symfony ai-provenance:keygen` |
| Rotate signing keypair | `php artisan ahg:provenance-ai:keygen --force` | `php symfony ai-provenance:keygen --force` |
| Verify signatures | via the Governance dashboard / `InferenceSigner` | `php symfony ai-provenance:verify` |
| Replay deferred Fuseki writes | `php artisan ahg:provenance-ai:replay` | `php symfony ai-provenance:replay` |
| Governance dashboard | `/admin/governance` | `/ai/governance` |

Run artisan / symfony tasks as the web user (`sudo -u www-data ...`) so they do
not leave root-owned cache, log or key files.

## See also

- **AI Inventory & Governance** - the dashboard, model manifests and inference
  signing in day-to-day use.
- **AI inference provenance** - the inference and reviewer-override chain and
  the provenance trace API.
- ADR-0002, *Provenance discipline baseline*.
- AI Governance & Sovereignty design doc (`docs/ai-governance-issue.md`).
