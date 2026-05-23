# Spectrum 5.1 procedure starter pack

The Spectrum procedure starter pack installs 21 workflows on your Heratio install — one for each of the Spectrum 5.1 primary procedures defined by [UK Collections Trust](https://collectionstrust.org.uk/spectrum/). Each shipped workflow comes with a sensible set of paraphrased starter steps you can immediately use, customise, or replace.

## What it is

| Procedure | Starter steps |
|---|---|
| Object entry | Receive object → Issue receipt → Initial condition check → Provisional location → Handover for processing |
| Acquisition and accessioning | Acquisition proposal → Governance approval → Transfer of title → Assign accession number → Formal accession record |
| Inventory | Define inventory scope → Physical verification → Reconcile against records → Investigate discrepancies → Inventory report |
| Location and movement control | Move request → Plan move → Pre-move record → Execute move → Confirm new location |
| Cataloguing | Identify object → Descriptive catalogue → Subject indexing → Quality review → Publication |
| Object exit | Reason for exit → Authorisation → Pre-exit record → Handover → Close record |
| Loans in (borrowing) | Loan request → Lender agreement → Insurance & transport → Receive & inspect → Return at end of loan |
| Loans out (lending) | Borrower request review → Loan agreement → Condition documentation → Dispatch → Return inspection |
| Insurance and indemnity | Valuation for insurance → Policy review → Document declarations → Annual review |
| Damage and loss | Discovery & immediate response → Damage assessment → Incident report → Recovery/repair planning → Final disposition |
| Conservation and collections care | Condition assessment → Treatment proposal → Approval → Treatment & documentation → Preventive care review |
| Audit | Audit planning → Sampling & verification → Findings documentation → Management response → Audit report |
| Object condition checking and technical assessment | Initial visual inspection → Detailed examination → Technical analysis → Condition report |
| Object valuation | Valuation request → Comparable research → Independent appraisal → Documentation & review schedule |
| Risk management | Risk identification → Risk assessment & scoring → Mitigation planning → Implementation → Periodic review |
| Emergency planning for collections | Hazard identification → Plan development → Staff training → Drill & exercise → Plan review/update |
| Use of collections | Use request → Eligibility review → Approval & conditions → Supervised use → Post-use condition check |
| Rights management | Rights identification → Rights documentation → Permission requests → Licence tracking → Periodic review |
| Reproduction | Reproduction request → Rights clearance → Production → Quality control → Delivery & record |
| Deaccessioning and disposal | Deaccession proposal → Governance review → Authorisation → Disposal execution → Permanent record |
| Retrospective documentation | Identify undocumented items → Prioritise → Catalogue → Verify → Integrate |

## Important — what it is NOT

> ⚠️ **These step names and instructions are paraphrased starting templates**, not the verbatim Spectrum 5.1 specification text. Spectrum 5.1 is © UK Collections Trust and we do not redistribute their copyrighted text.
>
> Before claiming Spectrum compliance, review each workflow's steps and instructions against your own Collections Trust subscription and adjust to match your institution's adopted Spectrum implementation.

## How to install

### From the admin UI

1. Log in as administrator
2. Go to **Workflow → Workflows & diagrams** (top-right AHG Plugins dropdown)
3. Click **Install Spectrum pack** in the page header
4. Confirm the dialogue
5. Workflows tagged with their Spectrum procedure now appear in the list

The button is **safe to re-click** — by default it only adds missing procedures. Existing workflows with the same `spectrum_procedure` tag are left untouched.

### From the command line

```bash
# Install missing procedures (default — safe, idempotent)
php artisan workflow:seed-spectrum

# Dry-run — show what would change, write nothing
php artisan workflow:seed-spectrum --dry-run

# Install only a specific procedure (or several)
php artisan workflow:seed-spectrum --only=object_entry --only=cataloguing

# RESET existing Spectrum workflows to seed defaults
# ⚠️ This DELETES hand-customised steps for those procedures
php artisan workflow:seed-spectrum --overwrite
```

## The `--overwrite` flag — what it does

| Without `--overwrite` (default) | With `--overwrite` |
|---|---|
| Existing Spectrum workflows are **skipped** | Existing Spectrum workflows have their **name + description updated** to seed defaults |
| Steps are **not touched** | All steps for that workflow are **deleted and re-inserted** from the seed |
| Safe to run repeatedly | Safe ONLY if you want a clean reset |

If your team has hand-customised the steps for, say, Cataloguing — running with `--overwrite` will **lose those customisations**. Use `--only` to scope the reset to specific procedures if you only want to reset some.

## Customising after install

After install, each Spectrum workflow is a normal Heratio workflow. You can:

- Add/edit/reorder steps from the workflow edit page
- Use the **drag-and-drop designer** (`Designer` button) to add branching between steps
- Adjust step types, approvers, escalation rules
- View the workflow diagram (`Diagram` button) including the Spectrum badge

Your customisations stay until you run `workflow:seed-spectrum --overwrite` on that procedure.

## Removing a procedure

If a procedure doesn't apply to your institution, delete the workflow normally from the admin page. Re-running the install will recreate it; either skip re-running, or use `--only` to install just the procedures you want.

## Reporting

Once installed, you can:

- Filter the workflow list to show only one procedure type
- See compliance progress per object (when Phase C — compliance dashboard — ships)
- Surface the procedure name on the diagram and on tasks for that procedure

## Reference

- UK Collections Trust — [Spectrum 5.1 framework](https://collectionstrust.org.uk/spectrum/)
- Seed file source — `packages/ahg-workflow/database/spectrum_procedures.json`
- Command source — `packages/ahg-workflow/src/Console/Commands/SeedSpectrumCommand.php`
