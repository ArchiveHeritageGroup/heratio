# Research Funding tracker

The Research Funding tracker is part of the Research Operating System. For each research project it records the funding sources that support the work - the funder, award reference, amount, currency, status and award period for each line - so a project's financial backing is documented in one place, alongside its Data Management Plan, outputs, grants and ethics record.

This is the awarded-funding ledger. It is distinct from the Grant Engine, which is about drafting a grant proposal: the Funding tracker records what a project actually has applied for or been awarded, with amounts and dates.

## What it records

Each funding line on a project captures:

- Title - a short label for the funding line.
- Funder name and funder type - government, research council, foundation, charity or non-profit, industry or commercial, internal or institutional, or other.
- Award reference - the funder's grant or award number.
- Amount and currency - an exact decimal amount in an ISO 4217 currency.
- Status - applied, awarded, active, completed, or declined.
- Award start date and end date.
- Notes, and an optional link to the project's Data Management Plan.

All of the funder-type, status and currency choices are drawn from the Dropdown Manager, so an administrator can extend them without code changes. No currency or funder country is assumed or defaulted - the tracker works across jurisdictions, and the currency list seeds a spread of common codes (USD, EUR, GBP, ZAR, AUD, CAD, JPY, CHF and others) that an administrator can add to.

## Amounts and currency

The amount is held as an exact decimal value, not a floating-point number, so figures are stored and totalled without rounding drift.

The per-project summary totals awarded amounts separately for each currency. Amounts in different currencies are never added together, because summing across currencies is not meaningful. Each currency is shown on its own line with its total and the number of awards counted. Only funding lines whose status is awarded, active or completed contribute to these totals; lines that are only applied for, or that were declined, are excluded.

## Active funding

Each funding line shows whether it is active right now. A line is treated as active when its status is "active", or when its award period brackets today (the start date is on or before today and the end date is on or after today, or the period is open-ended) and the line has not been declined or completed. The summary shows how many lines are active now.

## Summary and export

The project summary shows the total number of funding lines, the awarded total per currency, counts by status and by funder type, and the active-now count. A machine-readable JSON export of a project's funding is available - each line with its codes, human labels, exact amount, currency, status, award period and active flag, plus the awarded totals grouped per currency (never cross-summed).

## Notes

- Entries are scoped to a project and to the researcher; you manage the funding records of projects you belong to.
- The tracker is read and written only through its own table - it does not change any catalogue record.
- It is jurisdiction-neutral; no country, currency or funding regime is assumed or defaulted.
