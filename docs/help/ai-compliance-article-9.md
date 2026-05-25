> Heratio Help Center article. Category: AI / Compliance.

# EU AI Act Article 9 - Risk Management

## What this is

A register of every known risk for every AI service Heratio runs. Operators sign off on review; sign-off writes an immutable receipt to the #693 inference chain. The EU AI Act Article 9 calls for continuous, iterative risk management over the system's lifecycle.

## Where to find it

`/admin/ai-compliance/risk`

The page shows:
- Dashboard cards: active risks, open incidents, overdue reviews, inferences in last 7 days
- Filterable table by service + status
- Per-row actions: edit, sign-off, archive, report-incident

## What gets logged

For each risk:
- Service (llm/htr/ner/donut/guardrail/translate)
- Description, severity, likelihood
- Whether the risk comes from intended use or reasonably foreseeable misuse (Art. 9(2)(b))
- Affected group (e.g. researchers, indigenous_language_collections, data_subjects)
- Mitigation in place
- Residual risk after mitigation
- When operator last signed off + who

For each incident: description, observed severity, optional link to the specific inference receipt that triggered the report, resolution date.

## Operator workflow

### Reviewing a risk

1. Open `/admin/ai-compliance/risk`
2. Read the risk + mitigation
3. If still accurate, click the green check (sign-off). This writes a receipt to the #693 inference chain capturing the review.
4. If outdated, click the pencil (edit), update the mitigation, save.

### Recording an incident

1. Click the orange flag on the relevant risk row
2. Describe what happened + pick observed severity
3. Submit. The incident appears in the post-market monitoring digest.

### Adding a new risk

`/admin/ai-compliance/risk/new`. All fields with `*` are required.

### Annual review

Risks with `last_reviewed_at` older than 1 year are surfaced in the "Overdue reviews" dashboard card and in the weekly post-market notification.

## Post-market monitoring

A weekly cron runs `ai-compliance:risk-monitor` and posts a digest to the workbench notification bell when there are open incidents, overdue reviews, or unusual guardrail-event volume.

To trigger manually:

```bash
php artisan ai-compliance:risk-monitor
```

To skip the workbench notification when nothing is notable:

```bash
php artisan ai-compliance:risk-monitor --quiet-empty
```

## Vulnerable groups (Article 9(9))

The `affected_group` field tags risks that disproportionately affect specific groups - children, persons with disabilities, indigenous-language collections, data subjects. The EU AI Act requires elevated protection for these populations; Heratio surfaces them in the register so review prioritisation favours them.

## Related compliance work

- EU AI Act Article 11 (Annex IV technical documentation) - issue #725 - consumes this register for the technical-documentation bundle
- EU AI Act Article 12 (record-keeping) - issue #693 (closed) - sign-off events write to its tamper-evident chain
- EU AI Act Article 14 (human oversight) - issue #726 - shares the vulnerable-group escalation logic

## See also

- `docs/reference/ai-compliance-article-9.md` - implementation reference
- `packages/ahg-ai-compliance/` - Laravel plug-in source
