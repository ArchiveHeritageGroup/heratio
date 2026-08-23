# POPIA direct-marketing consent, withdrawal and privacy-notice wording (eDerma pack)

**Summary.** A reusable, POPIA-compliant wording pack for a South African clinic
(drafted for eDerma, a dermatology/skincare practice) that wants to use client
registration data for marketing with opt-in consent and a withdrawal option. The
core compliance points: marketing consent must be a **separate, unticked, optional
opt-in** (never bundled with treatment consent, never pre-ticked); using
treatment/skin (health) data for marketing is **"special personal information"**
needing its own explicit consent; a client can withdraw anytime and you must
**stop marketing immediately** - a "we keep marketing for 30 days after you
withdraw" clause is NOT defensible. The legitimate use of 30 days is the
**data-purge window** (removing details from all systems/backups), not continued
sending. Withdrawing marketing consent does **not** delete the patient's medical
record, because retention is legally required (HPCSA/National Health Act).

## Key POPIA basis (South Africa)

- **s26/s27/s32** - health data is special personal information; s32 lets medical
  practitioners process it for care under confidentiality.
- **s69** - direct marketing by electronic means is prohibited unless the person
  opted in (or is an existing customer for own similar goods/services); every
  message must carry an opt-out. Marketing consent may be requested only once
  (Regulation Form 4).
- **s11(2)(b)** - consent may be withdrawn at any time; withdrawal must be as easy
  as giving it.
- **s14** - don't keep data longer than necessary, UNLESS retention is required or
  authorised by law; destroy securely once the obligation ends, "as soon as
  reasonably practicable" (no fixed number of days).
- **s24** - data subject may request correction/deletion; the responsible party
  may refuse where law still requires retention.

## Medical-record retention (overrides consent withdrawal)

Set by HPCSA (Booklet 9) + National Health Act, not by POPIA numbers:
- General: at least **6 years** from the last consultation (record dormant).
- Minors: kept until age **21**.
- Mentally incompetent patients: kept for the patient's **lifetime**.
- Occupational/hazardous-exposure records: longer (OHSA / Mine Health & Safety,
  often 20-40 years).

## 1. Registration form - marketing consent (separate, unticked opt-ins)

> **Staying in touch (optional)** - this does not affect your treatment.
> [ ] Yes, send me eDerma marketing (promotions, events, general skincare information).
> [ ] Yes, you may personalise it using my treatment and skin-care records.
> Preferred channel(s): [ ] Email [ ] SMS [ ] WhatsApp
> You may withdraw at any time. On withdrawal we stop marketing to you immediately;
> full removal from our systems may take up to 30 days. See our Privacy Notice (POPIA).

The two consents are separate because personalising with health/treatment data is
special personal information needing its own explicit consent. Boxes must be
unticked and not required (a blank form = no consent).

## 2. In every marketing message (mandatory opt-out)

> You're receiving this because you agreed to marketing from eDerma. To stop,
> [click Unsubscribe] / reply STOP / email [privacy@ederma.co.za]. We'll action it right away.

## 3. Withdrawal-of-consent clause (corrected 30-day wording)

> **Withdrawing your consent.** You may withdraw at any time, at no cost, by
> [Unsubscribe / reply STOP / email / call]. When you withdraw, we will **stop
> sending you marketing immediately** and add you to our do-not-contact list.
> **Removing your details from all our systems, including backups and third-party
> mailing tools, may take up to 30 days.** During this period we will not send you
> further marketing. We keep a minimal record that you opted out, solely to honour
> your choice. Withdrawing marketing consent does not affect your patient records,
> which we are legally required to keep.

## 4. Website Privacy Notice (POPIA)

> **Who we are.** [Entity] is the responsible party under POPIA.
> **Purpose.** To provide and manage your dermatological/skincare treatment,
> appointments, billing, legal duties, and - only if you agreed - marketing.
> **Lawful basis.** Health/treatment data processed for your care under POPIA s32
> and a duty of confidentiality; legal obligations (National Health Act) and
> contract; consent only for optional marketing (withdrawable anytime).
> **Retention.** Patient records kept per HPCSA/National Health Act (generally >=6
> years from last visit; minors to 21; lifetime if mentally incompetent; longer
> where law requires). Marketing data kept until consent withdrawn, then stop
> immediately and remove within 30 days, keeping only a minimal opt-out record.
> **Your rights.** Access, correction, objection, withdraw marketing consent,
> request deletion of data no longer required, and complain to the Information
> Regulator.
> **Information Officer.** [Name, address, email, telephone].
> **Information Regulator (SA).** inforegulator.org.za;
> enquiries@inforegulator.org.za; POPIAComplaints@inforegulator.org.za.

## 5. Implementation guardrails

- Boxes unticked and not required; blank = no consent.
- Keep the two marketing consents separate; keep treatment/terms consent in its
  own section (never bundle marketing in).
- Capture the audit trail server-side: boxes ticked, channel(s), consent-text
  version, server timestamp. Don't trust the browser for the date.
- Do not re-ask someone who declined (marketing consent request allowed once).
- The do-not-contact / suppression record survives deletion, so a future import
  can't re-market an opt-out.

## 6. Standalone unsubscribe / withdraw-consent page

Reachable without login (tokenised link ideal). Lets a client stop all marketing
or fine-tune (keep general/personalised, per channel). States plainly: stop is
immediate; removal from systems up to 30 days; patient records unaffected. On
submit: stop sending at once, add to suppression list, log the change (choice,
method, server timestamp) for the audit trail.

---
*Source of the full pack (plain + HTML snippets, docx): heratio `stuff/ederma/`.
Draft wording for legal review, not legal advice; fill all [bracketed]
placeholders. Related: [[project_privacy_dpia_redaction]].*
