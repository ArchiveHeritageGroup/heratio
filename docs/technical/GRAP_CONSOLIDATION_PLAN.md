# GRAP Table Consolidation Plan

## Overview

GRAP 103 (Generally Recognised Accounting Practice for Heritage Assets) compliance requires tracking of heritage asset valuations, movements, and accounting entries. Currently, this functionality is split across multiple plugins with potential overlapping functionality.

## Current State

### ahgHeritageAccountingPlugin Tables (9 tables)

| Table | Purpose | Records |
|-------|---------|---------|
| `heritage_accounting_standard` | GRAP/IFRS standards reference | Lookup |
| `heritage_asset_class` | Asset classification (art, historical, natural) | Lookup |
| `heritage_asset` | Links objects to accounting data | Transaction |
| `heritage_valuation_history` | Historical valuations | Transaction |
| `heritage_impairment_assessment` | Impairment tests per GRAP 103 | Transaction |
| `heritage_movement_register` | Asset movements for audit | Transaction |
| `heritage_journal_entry` | Accounting journal entries | Transaction |
| `heritage_compliance_rule` | Validation rules | Config |
| `heritage_transaction_log` | Audit trail | Audit |

### ahgSpectrumPlugin Valuation Tables (2 tables)

| Table | Purpose | Records |
|-------|---------|---------|
| `spectrum_valuation` | Object valuations (insurance, market) | Transaction |
| `spectrum_valuation_alert` | Valuation review alerts | Notification |

## Analysis

### Overlap Assessment

1. **Valuation Data:**
   - `heritage_valuation_history` and `spectrum_valuation` both store valuations
   - Different schemas but similar purpose
   - Spectrum focuses on insurance/market value
   - Heritage focuses on GRAP-compliant book value

2. **Movement Tracking:**
   - `heritage_movement_register` overlaps with `spectrum_movement`
   - Heritage focuses on accounting impact
   - Spectrum focuses on physical location

### Recommendation

**Option A: Keep Separate (Recommended for Now)**
- ahgSpectrumPlugin: Museum collections management (Spectrum 5.0 standard)
- ahgHeritageAccountingPlugin: Financial/accounting compliance (GRAP 103)

**Rationale:**
- Different audiences (curators vs. accountants)
- Different compliance requirements
- Can be linked via `object_id` foreign keys

**Option B: Consolidate (Future Consideration)**
- Merge valuation tables with type indicator
- Create unified movement register
- Would require significant migration effort

## Action Items

1. [ ] Verify no data in `spectrum_valuation` that should be in `heritage_valuation_history`
2. [ ] Add foreign key relationship documentation
3. [ ] Create sync mechanism if needed
4. [ ] Schedule review after 6 months of usage

## Migration Notes

If consolidation is decided:

```sql
-- Example: Migrate spectrum_valuation to heritage_valuation_history
INSERT INTO heritage_valuation_history (
    object_id, valuation_date, valuation_amount, valuation_type,
    valuer_name, valuation_method, notes, created_at
)
SELECT
    object_id, valuation_date, amount,
    CASE type WHEN 'insurance' THEN 'insurance' ELSE 'market' END,
    assessed_by, method, notes, created_at
FROM spectrum_valuation
WHERE NOT EXISTS (
    SELECT 1 FROM heritage_valuation_history h
    WHERE h.object_id = spectrum_valuation.object_id
    AND h.valuation_date = spectrum_valuation.valuation_date
);
```

## Related Documentation

- GRAP 103: Heritage Assets standard
- Spectrum 5.0: Valuation procedure
- CLAUDE.md: Plugin architecture guidelines
