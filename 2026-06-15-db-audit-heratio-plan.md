# Heratio — data/feature gaps vs PSIS archive

**Date:** 2026-06-15
**Audit:** Bidirectional DB audit, second half (heratio ⇐ archive). What PSIS `archive` has that `heratio` lacks.
**DBs:** MySQL on host .112. `heratio` = 1190 tables, `archive` = 1042 tables (heratio is *larger overall* — it is ahead in many areas; this report covers only the reverse direction).
**Inputs:** `/tmp/only_archive.txt` (72 archive-only tables), `/tmp/coldiff.txt` (272 column diffs; 54 `archive_only`).

---

## Summary counts

### 72 archive-only tables
| Class | Count | Notes |
|-------|------:|-------|
| **NOISE / out-of-scope** | 39 | All `registry_*` — a *separate co-located application* (AHG software/instance registry; 169+ live rows in `registry_instance`, `registry_software`, `registry_institution`). Not an AtoM/Heratio feature. Heratio has **zero** `registry_*` tables by design. |
| **RENAMED / EQUIVALENT** | 3 | Capability exists in heratio under a different name (see appendix). |
| **GENUINE GAP** | 30 | Real PSIS features heratio lacks. This is the plan content. |

### 54 archive_only columns on shared tables
| Class | Count | Notes |
|-------|------:|-------|
| Real data field (worth porting) | ~10 | `library_item` MARC/FRBR, `ahg_exhibition_*` layout, `security_access_log` hash-chain. |
| Trivial / schema-drift / equivalent-renamed | ~44 | e.g. `library_z3950_target.database` vs heratio `database_name`; `scan_folder.created_by`; timestamps. |

> Skeptic's note: most gap tables are **empty in archive** (schema deployed, little/no data) because PSIS built them recently (library serials/MARC, ONIX, EU-AI-Act, help-system per MEMORY). They are *schema/feature* gaps, not *data-migration* gaps. heratio being the Laravel rewrite is **ahead** in 3D, audit-chaining, AI catalog/research, webhooks, marketplace, etc. — confirmed by the large `heratio_only` column set.

---

## GENUINE GAPS (ranked, grouped by feature area)

### 1. Library — interoperability & acquisitions backbone — **HIGH** `[buildable]`
PSIS extended `ahgLibraryPlugin` well beyond heratio. Missing tables:

| Table | Key columns | Capability |
|-------|-------------|------------|
| `library_z3950_server_config` | option_key, option_value, category | **Z39.50/SRU *server* mode** config (heratio only has `library_z3950_target` = client side) |
| `library_z3950_server_request` | client_addr, apdu_type, bytes_received, result_count, elapsed_ms | Z39.50 server request log |
| `library_sru_log` | query, cql_query, result_count, duration_ms, api_key_hint | SRU/CQL query audit |
| `library_usage_event` | library_item_id, patron_id, event_type, ip_address, session_id | Per-item circulation/usage events |
| `library_counter_settings` | setting_key, setting_value | COUNTER reporting config (7 rows in archive) |
| `library_sushi_access_log` | requestor_id, customer_id, report_type, period_begin/end | SUSHI harvest access log |
| `library_kbart_vendor` | name, feed_url, last_fetch_at, last_row_count | KBART vendor feed registry |
| `library_bindery_batch` | batch_number, vendor_id, status, sent/returned_date, item_count | Serials bindery batch management |
| `library_ill_status_history` | ill_request_id, from_status, to_status | ILL status transition history |
| `library_order_line_fund` | order_line_id, fund_code, amount | Acquisitions fund splitting per order line |
| `library_item_frbr_override` | library_item_id, target_work_key, forced_split, reason | Manual FRBR work-clustering override |

**Capability gap:** heratio has the library plugin but not the PSIS-extended Z39.50/SRU server, SUSHI/COUNTER metering, KBART vendor feeds, bindery, ILL history, fund accounting, or FRBR override controls. All empty in archive (newly built) → port schema + plugin code, no data migration.

### 2. EU AI Act governance (`ai_act_*`) — **HIGH** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `ai_act_system` | name, purpose, provider, role, risk_classification, lifecycle_status, human_oversight, owner, review dates | AI-system inventory & risk tiering |
| `ai_act_model` | system_id, model_id, version, modality, intended_purpose, training_data_summary, limitations, evaluation_summary, license | Model cards / transparency docs |
| `ai_act_risk` | system_id, category, likelihood, severity, mitigation, residual_*, status | Risk register w/ residual scoring |
| `ai_act_attestation` | system_id, type, statement, status, attested_by, evidence_url, next_review_date | Conformity attestations |

**Capability gap:** EU AI Act compliance governance module (system inventory, model cards, risk register, attestations). Heratio has AI features but **no governance/compliance layer** for them. Empty in archive → schema + admin UI port.

### 3. Request-to-Publish triage & review workflow (`rtp_*`) — **MEDIUM** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `rtp_workflow` | request_id, receipt_token, is_anonymous, triage_status, priority, assigned_to, internal_notes | RtP triage state machine + anonymous receipt tokens |
| `rtp_review` | request_id, reviewer_id, verdict, comments | Multi-reviewer voting on publish requests |

**Skeptic check:** heratio **does** have `request_to_publish` / `request_to_publish_i18n` (base) AND `ahg_publish_request` + `ahg_publish_gate_rule`/`_result` (a *different* gate model). But the PSIS triage/review layer (assignment, priority, multi-reviewer verdicts, anonymous receipt token) is **not** present. Genuine gap on top of an existing base capability.

### 4. RiC SHACL validation reporting (`ric_shacl_report`) — **MEDIUM** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `ric_shacl_report` | graph_uri, engine, conforms, data_triples, total_violations, warning/info_count, violations_json | SHACL conformance reports for the RiC triplestore |

heratio has many `ric_*` tables but **no SHACL validation report storage**. (MEMORY confirms SHACL was a recent PSIS host-extension.) Empty in archive → schema + reporting view port.

### 5. Privacy DPIA records (`privacy_dpia`) — **MEDIUM** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `privacy_dpia` | name, processing_activity_id, necessity_proportionality, risks_to_subjects, measures_to_mitigate, residual_risks, dpo_opinion, signed_off_by/at, status | Full DPIA *record* (POPIA/GDPR Art.35) |

**Skeptic check:** heratio has `privacy_dpia_log` (the *audit log* of DPIA actions) but **not the DPIA record table itself**. The log FKs a `dpia_id` that has no home table in heratio → the DPIA feature is half-present. Genuine gap.

### 6. Research — DMP datasets & ORCID schema parity — **LOW** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `research_dmp_dataset` | dmp_id, name, data_type, formats, sensitivity, personal_data, license, repository, retention_period, sharing_policy | Structured per-dataset records inside a Data Management Plan |

**Skeptic check:** heratio has `research_dmp` + `research_dmp_section` (free-text section model). PSIS uses a **structured dataset** sub-table instead/in-addition. Different modelling, low priority (empty in archive). `research_orcid_link` is **RENAMED** → heratio `researcher_orcid_link` (see appendix) — *not* a gap.

### 7. Accessibility — audio description tracks (`media_audio_description`) — **LOW** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `media_audio_description` | digital_object_id, language, label, vtt_content | WCAG audio-description VTT tracks for AV objects |

heratio has rich `media_*` (captions, chapters, speakers, transcription) but **no audio-description track**. Accessibility gap. Empty in archive.

### 8. Label/barcode template designer (`label_template`) — **LOW** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `label_template` | page_size, columns/rows, label_width/height_mm, margins, barcode_source, show_qr, qr_target, is_default | Configurable physical label/barcode print templates |

heratio has `ahgLabelPlugin` listed but **no template table** → templates are likely hardcoded. Empty in archive → schema + admin CRUD port.

### 9. Scan ingest run history (`scan_event`) — **LOW** `[buildable]`
| Table | Key columns | Capability |
|-------|-------------|------------|
| `scan_event` | folder_id, detected, enqueued, skipped_duplicate, skipped_quiet, failed, job_id, status | Hot-folder scan run statistics/history |

heratio has `scan_folder` + `scan_session_token` but **no per-run event log**. Operability gap. (Note: `scan_folder` shared-table cols differ too — see below.)

---

## Missing columns on SHARED tables (real data fields)

These are `archive_only` columns where the table exists in both DBs but heratio lacks the field. Most of the 54 are trivial/renamed (appendix); the **real** ones:

| Table | Missing column(s) in heratio | Verdict | Priority |
|-------|------------------------------|---------|----------|
| `library_item` | `marc_leader`, `marc_005`, `marc_008`, `frbr_work_key`, `frbr_override_type`, `description` | **Real** — MARC binary record fields + FRBR clustering keys. heratio has `work_key` (partial) but not the MARC leader/control fields. *(All 0/14 populated in archive — schema-ahead, port columns.)* | HIGH |
| `library_item_creator` | `is_primary` | Real — primary-creator flag | LOW |
| `library_serial_issue` | `bindery_batch_id` | Real — FK to bindery batch (pairs with gap #1) | MEDIUM |
| `library_subject_authority` | `subject_type` | Real — subject classification type | LOW |
| `ahg_exhibition_placement` | `wall`, `rotation`, `tour_order`, `item_w`, `item_h` | **Real & populated (4/4 rows)** — 2.5D wall placement + guided-tour ordering. heratio has *different* exhibition layout cols (`remote_*`, `recommendations_json`) → schema divergence; PSIS wall/tour model not in heratio. | MEDIUM |
| `ahg_exhibition_space` | `room_width`, `room_height`, `floor_color`, `furniture_json` | **Real (room_width populated)** — simple room-box model. heratio has a richer `building_plan_*`/`floorplan_*_m` model → likely **superseded** in heratio; verify before porting. | LOW |
| `security_access_log` | `entry_hash`, `prev_hash`, `compartment_id`, `session_id` | Real (hash-chain) BUT **table empty (0 rows)** in archive, and heratio already ships its own tamper-evident chaining on `ahg_audit_log` (`kid`/`seq`/`signature`, per MEMORY webauthn_mfa_126). Likely **equivalent under different table** → LOW / verify. | LOW |
| `research_dmp` | 12 archive_only cols (fair_*, data_security, ethics_legal, grant_number, researcher_id, version…) | Real — structured FAIR/DMP fields. heratio uses section-based model (`research_dmp_section`) instead. Modelling divergence → port only if structured DMP wanted. | LOW |
| `research_project_milestone` | `milestone_type` | Real — milestone categorisation | LOW |
| `scan_folder` | `auto_commit`, `created_by`, `failed_path`, `processed_path` | Real — auto-commit + path routing for hot-folder. heratio has `notify_*` instead → partial divergence. | MEDIUM (pairs with #9) |
| `object_3d_camera_bookmark` | `display_order`, `model_id` | heratio renamed (`object_3d_id`, `is_default`) → mostly **equivalent**; trivial. | LOW |
| `ahg_c2pa_manifest` | `asset_hash`, `digital_object_id`, `manifest_label`, `signature_hex` | heratio has a *richer* C2PA model (`manifest_cbor`, `claim_signature`, `model_id`) → **heratio ahead**; archive_only cols are an older shape. NOT a real gap. | — |
| `library_kbart_import_log`, `library_z3950_target` | various | **RENAMED/equivalent** (heratio cols cover same data: `database`→`database_name`, `password_hash`→`password`, `vendor_id`→`feed_id`, etc.) → not gaps. | — |

---

## Appendix A — RENAMED / EQUIVALENT (not gaps)

| archive table/col | heratio equivalent | Notes |
|-------------------|--------------------|-------|
| `research_orcid_link` | `researcher_orcid_link` | Same schema, prefix rename. Not a gap. |
| `ahg_email_suppression` | `ahg_email_bounce` (+ heratio email-delivery plugin) | Bounce/suppression handled under bounce table. Verify suppression-list parity in plugin code; schema-equivalent. |
| `ahg_web_annotation` | `research_annotation_v2` / `iiif_annotation` / `iiif_annotation_body` | W3C Web Annotation capability already present in heratio under annotation tables. Not a gap. |
| `library_z3950_target.database/password_hash/timeout/is_active` | `database_name/password/.../active` | Column renames on a shared table. |
| `library_kbart_import_log.*_count/vendor_id/fetched_at` | `added/removed/changed/feed_id/created_at` | Richer heratio shape; equivalent data. |
| `ahg_c2pa_manifest` archive_only cols | heratio `manifest_cbor`/`claim_signature`/`model_id` | heratio C2PA model is a superset. |
| `object_3d_camera_bookmark.model_id/display_order` | `object_3d_id`/`is_default` (heratio) | Equivalent FK rename. |
| `security_access_log` hash cols | `ahg_audit_log.kid/seq/signature` (heratio) | Tamper-evident chaining lives on the audit log in heratio (verify before porting). |

## Appendix B — FRAMEWORK NOISE / OUT-OF-SCOPE

- **39 × `registry_*` tables** — a **separate co-located application** (the AHG software/instance/institution registry: blog, discussion, newsletter, OAuth, ERD, reviews, vendors, etc.). Live data present in `archive` (169 instances, 17 software, 178 institutions). This is NOT AtoM/Heratio functionality and Heratio correctly carries none of it. **Exclude entirely.**
- No base AtoM `qubit_*`/`propel_*`/Symfony cache/session tables appeared in the archive-only list — both DBs are AtoM-Heratio installs, so the usual base-framework noise isn't present here. The only "noise" class is the registry app.

---

## Recommended build order

1. **HIGH** — Library interop/acquisitions backbone (11 tables) + `library_item` MARC/FRBR columns. Biggest single coherent gap; PSIS-ahead.
2. **HIGH** — EU AI Act governance module (`ai_act_*`, 4 tables) — compliance differentiator, fully buildable.
3. **MEDIUM** — RtP triage/review (`rtp_*`), RiC SHACL reporting (`ric_shacl_report`), Privacy DPIA record (`privacy_dpia` — completes the half-present feature), scan run history + folder routing.
4. **LOW** — DMP datasets, audio-description tracks, label templates, exhibition wall/tour columns (verify vs heratio's newer floorplan model first).

All gap tables are **empty in `archive`** → these are schema/feature ports, not data migrations. Each should be delivered as columns/tables in the relevant `ahg*Plugin` install SQL + plugin code, then enabled per CLAUDE.md workflow (never auto-INSERT into `atom_plugin`).
