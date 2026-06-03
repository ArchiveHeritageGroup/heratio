# Field-level redaction of archival descriptions

Field-level redaction lets you hide individual metadata fields on an archival
description (for example `creator_birth_date` or `subject_biography`) from public
viewers while administrators and authorised researchers continue to see the full
record. It implements the GDPR / POPIA data-minimisation principle: granular,
per-field decisions instead of all-or-nothing access control.

> Jurisdiction-neutral: the same mechanism serves GDPR, POPIA, and equivalent
> regimes. The legal-basis reference field lets you cite the relevant provision
> (e.g. POPIA s.37, GDPR Art.17(3)(e)).

## How redaction is applied

Each description can have a **privacy profile** (a reason, a status, and a legal
basis) and a list of **redacted fields**. For each field you choose a redaction
type:

- **Full** - the value is replaced with `[REDACTED — personal data removed]`.
- **Partial** - a pattern keeps part of the value visible: `email_partial`
  (`j***@***`), `phone_partial` (`******4567`), `id_last4` (`********3456`),
  `year_only` (`1954`).
- **Pseudonymised** - replaced with a stable, non-reversible token
  (`Subject-4f9a2c`).

Public users see the redacted version; administrators see the original. Every
decision and access is logged with the field, type, reason, user, date, and
legal basis.

## Managing redaction on a description

1. Open any archival description detail page as an administrator.
2. Use the **Field redaction** panel (bottom-right of the page) to see the
   current status and which fields are redacted, then choose **Manage field
   redaction**. You can also go directly to
   **Admin -> Privacy -> Description privacy** for a description.
3. Set the privacy profile (reason, status, legal basis), then add field
   redactions one at a time (field, type, optional pattern, reason).

## DSAR redaction scope

When preparing a response to a data subject access request (DSAR), you can mark
which descriptions are in scope and have their privacy profiles pre-populated:

1. Open the DSAR and choose **Redaction scope**.
2. Add each archival description in scope (by numeric id or slug). Each one gets
   a privacy profile created at status **pending** with the *access request*
   reason, ready for you to mark fields for redaction.
3. Moving a DSAR to **processing** automatically pre-populates profiles for every
   description already in scope.

Each in-scope description links straight to its field-redaction panel so you can
complete the redactions as part of the response.
