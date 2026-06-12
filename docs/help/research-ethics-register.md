# Research Ethics and Consent register

The Research Ethics and Consent register is part of the Research Operating System. For each research project it records the project's ethics approvals and the consent basis for any human-subject or sensitive data it works with, so the governance position of a project is documented in one place and visible on the researcher journey.

## What it records

Each ethics entry on a project captures:

- Approval type - human subjects, animal, data protection, biosafety, or other.
- Reference number and the committee or body that issued the decision.
- Status - not required, pending, approved, approved with conditions, expired, or rejected.
- Decision date and expiry date.
- Consent basis - informed consent, legitimate interest, public task, anonymised, or not applicable.
- Data sensitivity - none, personal, special category, or restricted.
- Notes, and an optional link to the project's Data Management Plan.

All of the status, approval-type, consent-basis and sensitivity choices are drawn from the Dropdown Manager, so an administrator can extend them without code changes. The terms are deliberately generic governance concepts - they are not tied to any single country's law, so the register works across jurisdictions.

## Expiring approvals

Each entry computes an expiry flag: approvals that have passed their expiry date are marked "expired", and approvals due within the next 60 days are flagged "expiring soon". The per-project summary surfaces a warning banner with the counts so a researcher or administrator can renew an approval before it lapses.

## Summary and export

The project summary shows totals and counts by status and type. A machine-readable JSON export of a project's ethics records is available (each entry with its codes, human labels, dates, computed expiry flag, and any DMP link), so the governance record can be reused in reports or returns.

## Notes

- Entries are scoped to a project and to the researcher; you manage the ethics records of projects you belong to.
- The register is read and written only through its own table - it does not change any catalogue record.
- It is jurisdiction-neutral; no country-specific regime is assumed or defaulted.
