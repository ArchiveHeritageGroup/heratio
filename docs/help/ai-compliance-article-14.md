> Heratio Help Center article. Category: AI / Compliance.

# EU AI Act Article 14 - Human Oversight

## What this is

The controls that put a human in the loop on every AI service - confidence thresholds, halt buttons, automation-bias acknowledgement, and the two-person verification flow for biometric ID.

## Where to find it

`/admin/ai-compliance/oversight`

The page has three sections:

1. **Automation-bias attestation** - your annual acknowledgement. Without an active attestation you cannot approve AI output.
2. **Per-service oversight policies** - one row per AI service: halted yes/no, review required yes/no, confidence threshold, dual-review yes/no, banner text. Edit inline. Halt / resume buttons per row.
3. **Pending two-person verifications** - face-detect (and any other dual-review service) decisions awaiting a second-person countersignature under Article 14(5).

## Operator attestation (annual)

The EU AI Act Article 14(4)(b) requires that operators be aware of automation bias - the tendency to over-trust AI output. Once a year you attest that you understand this risk and will critically review AI suggestions against the source before approving them.

To attest:
1. Open `/admin/ai-compliance/oversight`
2. If you see "Not attested" or "Expired", click **Attest now**
3. A receipt is written to the #693 inference chain capturing your acknowledgement

## Per-service controls

For each AI service:

- **Requires human review** - toggle whether output of this service needs operator approval before being persisted
- **Confidence threshold** - 0 to 1. Output below this confidence auto-routes to review even if "Requires human review" is off
- **Dual review (Art. 14(5))** - biometric services require a second-person countersignature
- **Bias prompt** - the banner shown above this service's output in the UI ("AI suggestion - verify against source")

Edit policies inline via the pencil button.

## Halting a service

**Per-service**: click the orange octagon on the relevant row. Optionally provide a reason. Service goes offline immediately; receipts to the #693 chain capture the halt event.

**Emergency global halt**: red "Halt ALL AI services" button at the top of the policies table. Halts every running service.

**Resume**: green play button on a halted row.

**CLI alternative**:

```bash
php artisan ai-compliance:halt llm --reason="Investigating prompt injection"
php artisan ai-compliance:halt --reason="Outage - block all"
php artisan ai-compliance:halt llm --resume
php artisan ai-compliance:halt --resume
```

## Two-person verification (Article 14(5))

For biometric ID services like face-detect, the EU AI Act requires that no action be taken on the basis of the AI output unless verified by at least two separate, qualified persons.

How this works in Heratio:

1. First reviewer makes a decision (confirm / override / reject) on a face-match suggestion
2. The decision lands in `ai_review_decision` with `countersigner_user_id = null`
3. The "Pending two-person verifications" section on the oversight page shows the entry
4. A different operator clicks "Countersign". (The server rejects the countersignature if it's the same person.)
5. Now the decision is binding and the downstream action can proceed
6. Both signatures are written to the #693 chain

## Status indicators

| Badge / icon | Meaning |
|---|---|
| Green "Running" | Service active and healthy |
| Red "HALTED" | Service is halted; new calls are blocked |
| Green check (attestation) | Operator attestation active |
| Yellow warning (attestation) | Operator never attested |
| Red X (attestation) | Operator attested but expired |
| Yellow people-fill icon | Dual-review required (Art. 14(5)) |
| Yellow warning badge on pending list | Awaiting countersignature |

## Related compliance work

- Article 9 (risk register) - #724 - shares the vulnerable-group escalation logic
- Article 11 (Annex IV technical docs) - #725 - oversight controls described per service
- Article 12 (record-keeping) - #693 (closed) - sign-off / decision / halt / attestation events all write here

## See also

- `docs/reference/ai-compliance-article-14.md` - implementation reference
- `packages/ahg-ai-compliance/` - Laravel plug-in source
