> Heratio Help Center article. Category: Heritage.

# Heritage Asset Carrying Amount Roll-forward

## What this feature does

The roll-forward report explains how the value of your heritage assets moved during a reporting period. It starts from the opening carrying amount, adds and subtracts each dated movement, and arrives at the closing amount. Finance teams and auditors can then see which assets the figures rest on and where the records disagree.

Amounts are replayed from the dated valuation and impairment histories, not copied from the current figure on each asset. That is what lets the report test itself: if an asset's register value cannot be reproduced from its history, the asset is listed as unreconciled.

The report reads the register and changes nothing. Wording follows the accounting standard you filter on. A market module can supply its own line labels, as the GRAP 103 module does, and any other standard uses neutral labels. The arithmetic is the same everywhere.

## Current limit: the two registers are not consolidated

Heratio still holds two heritage asset registers, the Heritage Accounting register and the separate IPSAS register. **Consolidating them has not been built.** The roll-forward covers the Heritage Accounting register only, so a value held only in the IPSAS register does not appear in it.

What you can do today is compare the two with a read-only command, described below. It shows where they differ but does not merge, copy or correct anything.

## Run the report

You need an administrator or editor account.

1. Open **Heritage Accounting** at `/heritage/accounting`.
2. In the side menu, under **Reports**, choose **Roll-forward**. The address is `/heritage/reports/roll-forward`.
3. Check the **From** and **To** dates. The page opens on your configured financial year, or on the calendar year if none is set.
4. Optionally pick a **Standard** and a **Class** to narrow the report.
5. Select **Run**.

The main table shows the opening amount, additions, revaluation increases and decreases, impairment losses, reversals, disposals and the closing amount, followed by the number of recognised assets at period end. Below it, the same movements are broken down by asset class.

Only assets that are recognised or derecognised are included. An asset with no accounting standard assigned appears when **Standard** is set to **All** but drops out as soon as you filter on a specific standard.

## Read the reconciliation messages

A green message means every asset agrees with its dated ledgers. Anything else deserves attention before the figures are relied on.

The **Unreconciled assets** table lists each asset whose register amount differs from what its history produces, with both amounts and the difference. Select the asset number to open it. Depreciation is a common cause. Heratio keeps no dated depreciation ledger, so an asset carrying depreciation is reported here and is not quietly absorbed into another line.

The **Reserve ledger movements without a register entry** table lists movements on the OCI ledger that no valuation or impairment explains, typically entries keyed directly on the OCI Movements screen. They are left out of the totals until resolved.

Both tables show the first 200 rows. The warning above them gives the full count.

## Record impairments and revaluations

Recording an impairment on an asset now also posts it to the reserve ledger, taking it from the asset's revaluation reserve first. Heratio refuses an impairment loss larger than the carrying amount.

A later revaluation is measured from the current carrying amount, so an impairment since the last valuation is taken into account. When the value rises, the increase first reverses any loss previously taken to profit or loss, and only the remainder goes to the reserve.

## Include the roll-forward in a disclosure note

The GRAP 103 disclosure note now carries the roll-forward table. An administrator with server access can generate it:

```bash
sudo -u www-data php artisan heritage:disclosure-note --standard=grap-103 --period=2025-04-01..2026-03-31 --out=/path/to/note.md
```

This note covers only assets assigned to that standard. Recognised assets with no standard are left out and counted in a warning inside the note, and any unreconciled items are flagged there as well. Clear those warnings before anyone signs. The IPSAS 45 and transitional notes do not include the roll-forward table.

## Compare the two registers

Run this on the server:

```bash
sudo -u www-data php artisan heritage:register-reconcile
```

It prints how many rows each register holds, how many records appear in both, how many of those carry different values, and how many exist in only one register or have no linked record. It lists counts only and writes nothing. If one of the registers is not installed, it says so and stops.

## Troubleshooting

If the page reports that the end of the period cannot be before its start, correct the dates and run it again.

Totals that look too low usually come from a filter. Set **Standard** and **Class** back to **All**, then check that the assets you expect have a recognition status of recognised or derecognised.

When an asset stays unreconciled, compare its register amount with its valuation and impairment history. A difference equal to accumulated depreciation is the known limit described above and cannot be cleared by re-running the report.

If a value you expect is missing altogether, check whether it lives in the IPSAS register by running `heritage:register-reconcile`. Until the registers are consolidated, the roll-forward will not include it.
