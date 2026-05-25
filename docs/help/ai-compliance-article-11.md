# EU AI Act Article 11 - Annex IV Documentation

> In-app help for the AI Compliance > Annex IV documentation admin page.

## Overview

The EU AI Act Article 11 requires the provider of a high-risk AI system to draw up technical documentation **before** the system is placed on the market and to keep it up to date. Annex IV enumerates the nine sections that must be included. Heratio generates one Markdown bundle per AI service through the AI Compliance admin pages.

Enforcement deadline: **2 August 2026**. Retention obligation: **at least 10 years** from the date of last placing on the market.

## Usage

### Where it lives

- AI Model Registry: `/admin/ai-compliance/models`
- Annex IV Documentation: `/admin/ai-compliance/documentation`
- Generated bundles on disk: `storage/ai-compliance/annex-iv/`

### Day-to-day workflow

1. **Keep the registry current.** When you deploy a new model version (e.g. switch the LLM from `mistral:7b` to a successor), open `/admin/ai-compliance/models` and:
   - Set `retired_at` on the row for the outgoing model. Do not delete it. Annex IV section 6 requires a full lifecycle history.
   - Click **Add model** to create a row for the new model. Fill in the intended purpose, training-data summary, known limits, and accuracy metrics.
2. **Generate the bundle.** Open `/admin/ai-compliance/documentation` and click **Generate bundle(s)**. Pick a specific service or leave blank to generate for all services.
3. **Review the output.** Open each generated file. Verify the Declaration of Conformity at the top, the lifecycle table in section 6, and the accuracy metrics in section 3. Sign the printed Declaration physically if a regulator requires a wet signature.
4. **Archive on rotation.** When the storage volume for `storage/ai-compliance/` is rotated, copy the entire `annex-iv/` directory to the new volume. Article 11(3) requires regulators to be able to obtain the documents for 10 years.

### Command-line equivalent

Operators can drive the same generator from the CLI:

```
php artisan ai-compliance:annex-iv
php artisan ai-compliance:annex-iv --service=htr
php artisan ai-compliance:annex-iv --out=/tmp/regulator-export
```

### What each bundle contains

Each per-service Markdown file holds, in order:

1. The full **EU Declaration of Conformity** (Annex V) with substituted Heratio version, deployment date, and signing party.
2. The nine Annex IV sections: general description, system elements, monitoring/functioning/control, performance metrics, risk management, lifecycle changes, harmonised standards, conformity declaration cross-reference, and the post-market monitoring plan.
3. A receipt-chain fingerprint - every bundle write is recorded in `ai_inference_log` (Article 12), so the bundle on disk and its proof of authorship are tamper-evident.

## Configuration

Default signatures and addresses come from `config/ahg.php`. Adjust the keys below to match your deployment:

| Config key | Purpose | Default |
| --- | --- | --- |
| `ahg.compliance.signing_party_name` | Name printed on the EU Declaration | Johan Pieterse |
| `ahg.compliance.signing_party_role` | Role printed on the EU Declaration | Provider authorised representative |
| `ahg.compliance.signing_party_email` | Contact on the EU Declaration | johan@theahg.co.za |
| `ahg.compliance.provider_address` | Provider postal address | Plain Sailing Information Systems |
| `ahg.compliance.place_of_issue` | Place of issue on the EU Declaration | Republic of South Africa |

## References

- Source: `packages/ahg-ai-compliance/`
- Reference doc: `docs/reference/ai-compliance-article-11.md`
- Sibling features: `/help/ai-compliance-article-12` (Article 12 logging), Article 9 risk register (issue #724)
- Issue: [GH #725](https://github.com/ArchiveHeritageGroup/heratio/issues/725)
