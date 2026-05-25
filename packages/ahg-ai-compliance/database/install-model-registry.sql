-- Heratio AI Compliance - EU AI Act Article 11 / Annex IV model registry.
--
-- Per-service registry of every AI model that has been (or is currently)
-- deployed. Drives the Annex IV technical-documentation generator and
-- gives operators a single canonical record of model identity, training
-- data, known limits, and accuracy metrics for regulator-facing docs.
--
-- Rows are append-only in practice. When a model is retired set
-- retired_at, then deploy a new row with the next model_version. Read
-- paths current model via service + retired_at IS NULL.
--
-- NOTE on syntax. This file is parsed by AhgAiComplianceServiceProvider
-- with a naive explode statement-by-semicolon splitter. Do not use any
-- raw semicolon inside string literals (use a comma or period instead).
-- Comment lines containing a semicolon are also unsafe.

CREATE TABLE IF NOT EXISTS `ai_model_registry` (
    `id`                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service`                VARCHAR(32) NOT NULL,
    `model_id`               VARCHAR(128) NOT NULL,
    `model_version`          VARCHAR(64) NOT NULL,
    `deployed_at`            DATETIME NOT NULL,
    `retired_at`             DATETIME DEFAULT NULL,
    `gateway_endpoint`       VARCHAR(255) DEFAULT NULL,
    `training_data_summary`  TEXT,
    `known_limits`           TEXT,
    `accuracy_metrics_json`  JSON DEFAULT NULL,
    `intended_purpose`       TEXT,
    `created_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_service_deployed` (`service`, `deployed_at`),
    KEY `idx_model_version` (`model_id`, `model_version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed rows for the six AI services currently instrumented by issue #693.
-- Operators can edit / extend these via /admin/ai-compliance/models.
-- INSERT IGNORE so a re-run on an already-seeded instance is a no-op.

INSERT IGNORE INTO `ai_model_registry`
    (`service`, `model_id`, `model_version`, `deployed_at`, `gateway_endpoint`,
     `training_data_summary`, `known_limits`, `accuracy_metrics_json`, `intended_purpose`)
VALUES
    ('llm', 'mistral:7b-instruct-v0.2', 'mistral-7b@2024-01', '2026-01-01 00:00:00',
     'https://ai.theahg.co.za/ai/v1/ollama',
     'Mistral 7B Instruct v0.2 base weights (Apache 2.0). Pretrained by Mistral AI on web-scale multilingual corpora, instruct-tuned on supervised pairs. Heratio does not fine-tune. The model is consumed as-is via the AHG AI gateway.',
     'English-dominant, weaker on low-resource African languages. Reasoning over long context degrades past ~8k tokens. Can hallucinate citations. Not used for autonomous decisions, human-in-the-loop required for every research output.',
     JSON_OBJECT('source', 'mistralai/Mistral-7B-Instruct-v0.2 model card', 'mmlu', 0.55, 'note', 'Operator should append per-deployment held-out metrics here'),
     'General language tasks inside research workspace - summarisation, drafting, answering grounded questions over archival records. Always invoked with a human reviewer in the loop.'),

    ('htr', 'kraken:archive-historic-en-v1', 'kraken-historic-en@2025-09', '2026-01-01 00:00:00',
     'https://ai.theahg.co.za/ai/v1/htr',
     'Kraken handwritten-text-recognition model trained on EN/LA/AF historic-script line images. Training corpus is publicly available historic-record line transcriptions plus AHG-curated SADC mission-archive lines.',
     'Optimised for 18-20c Latin-script European and SADC mission-archive hands. Accuracy degrades sharply on cursive Arabic-script, syllabaries, and heavily faded ink. CER target 8-15 percent on in-distribution lines.',
     JSON_OBJECT('cer_target_pct', 12.0, 'corpus', 'AHG mission-archive held-out set', 'note', 'Per-collection CER published in Annex IV bundle'),
     'Assistive transcription of historic handwritten records. Output is always presented for human review before being committed to the archival description.'),

    ('ner', 'spacy:en-core-web-trf', 'spacy-trf@3.7', '2026-01-01 00:00:00',
     'https://ai.theahg.co.za/ai/v1/ner',
     'spaCy transformer-based English NER model (RoBERTa backbone). Standard OntoNotes 5 entity inventory. No Heratio-specific fine-tuning at present.',
     'English only. People, place, and organisation categories only. Misses fictional entities, archaic placenames, and many SADC-specific organisations. Confidence threshold required before auto-suggesting an actor authority.',
     JSON_OBJECT('source', 'spaCy v3.7 release notes', 'f1_ontonotes', 0.89, 'note', 'Per-archive precision/recall published in Annex IV bundle'),
     'Suggesting candidate actor authorities for archivists. Suggestions are human-confirmed before any authority record is created.'),

    ('donut', 'donut:base-finetuned-cord-v2', 'donut-cord@v2', '2026-01-01 00:00:00',
     'https://ai.theahg.co.za/ai/v1/donut',
     'NAVER Donut document-understanding transformer fine-tuned on CORD v2 (consolidated receipt dataset). Used by Heratio for structured extraction from typed/digitised forms.',
     'Trained on receipts and tabular documents. Performs poorly on free-form prose, multi-column historic newspapers, or handwritten material (use HTR pipeline instead). Schema-bound output - hallucinates fields not in the training schema.',
     JSON_OBJECT('source', 'donut-cord-v2 model card', 'note', 'Per-template field-level accuracy published in Annex IV bundle'),
     'Structured field extraction from digitised forms and typed records. Output is reviewed by an archivist before being persisted to ISAD fields.'),

    ('guardrail', 'heratio:rag-guardrail-policy', 'guardrail@v1.0', '2026-01-01 00:00:00',
     NULL,
     'Rule-based content-policy gate written in-house. No ML training. Reviews prompts and responses against an allow-list of source-grounded patterns plus a deny-list of risk patterns (PII leak, copyright lift, jailbreak). See docs/reference/ai-rag-guardrails.md.',
     'Deterministic rule engine, will not catch novel jailbreak phrasings. Operates as defence-in-depth on top of model-level alignment, not a sole safeguard.',
     JSON_OBJECT('rule_count', 47, 'last_audited', '2026-05-01', 'note', 'Audit log published in Annex IV bundle'),
     'Inspects every prompt and response in the research portal and refuses or redacts when a policy rule fires.'),

    ('translate', 'nllb-200:distilled-600M+mistral:7b', 'mzansilm@2026-03', '2026-01-01 00:00:00',
     'https://ai.theahg.co.za/ai/v1/translate',
     'Two-stage - Meta NLLB-200 distilled 600M handles isiZulu, isiXhosa, Sesotho, Setswana, and Afrikaans pivots, Mistral 7B handles post-editing fluency. Heratio does not retrain NLLB.',
     'NLLB struggles on archaic and dialectal SADC variants. Mistral post-edit can introduce fluent-but-wrong content (hallucinated tone). Always presented with a machine-translation disclaimer to the end user.',
     JSON_OBJECT('source', 'NLLB-200 model card + AHG eval on SADC corpus', 'note', 'BLEU/chrF per language-pair published in Annex IV bundle'),
     'Assistive translation of archival descriptions for multilingual discovery. Output is always marked as machine-translated and is reviewable by a human translator.');
