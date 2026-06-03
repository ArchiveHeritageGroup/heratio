# Data Protection Impact Assessment (DPIA)

A DPIA documents how a high-risk processing activity affects the rights and
freedoms of data subjects, and what is done to reduce that risk. Heratio's DPIA
module implements the GDPR Article 35 / WP29 assessment workflow and links it to
the Article 30 Record of Processing Activities (ROPA).

> The module is jurisdiction-neutral. GDPR is the reference framework, but the
> same assessment satisfies equivalent regimes (POPIA s.4 risk assessments,
> UK DPA, and similar) in other markets.

## When a DPIA is required

Heratio screens every Article 30 processing activity automatically. An activity
is flagged **DPIA required** when any of the four high-risk triggers is present:

1. **Special category data** - health, racial/ethnic origin, political opinions,
   religion, genetic, biometric, sex life, trade-union membership, criminal data.
2. **Large-scale profiling** - profiling, scoring, systematic monitoring,
   automated decision-making, behavioural prediction.
3. **Biometric or genetic processing** - fingerprints, facial recognition, DNA.
4. **Cross-border transfer to a non-adequate jurisdiction** - a transfer outside
   the EEA with no documented safeguards (SCCs, adequacy decision, BCRs).

The screen runs whenever an activity is created or edited. A Data Protection
Officer can override any trigger on the Article 30 entry (force on, force off, or
leave on auto). The result is stored on the register (`dpia_required`) and shown
in the regulator export (JSON / CSV / Markdown).

## Running a DPIA

1. Go to **Admin -> Privacy -> DPIA** and choose **Start DPIA**.
2. Optionally link the DPIA to the Article 30 processing activity it covers.
3. Work through the four steps:
   - **Step 1 - Necessity and proportionality** - why the processing is needed
     and why it is proportionate to the rights involved.
   - **Step 2 - Risks to data subjects** - confidentiality, integrity,
     availability, discrimination, profiling, secondary use.
   - **Step 3 - Mitigation and residual risk** - measures taken and the risk
     that remains afterwards.
   - **Step 4 - DPO opinion and sign-off** - record the DPO opinion and the
     consultation date.
4. **Sign off** marks the DPIA *completed* and writes a tamper-evident row in the
   audit trail. When the DPIA is linked to a processing activity, the ROPA entry
   is automatically marked `dpia_completed` with the sign-off date.

## Audit trail

Every DPIA lifecycle event - created, updated, moved to review, signed off,
archived, and each linked-ROPA completion - is recorded in `privacy_dpia_log`
with the actor, timestamp, status transition, and IP address. Sign-off and
archive events additionally write to the chained audit log so the record is
tamper-evident.

## Statuses

| Status     | Meaning                                            |
|------------|----------------------------------------------------|
| draft      | Being prepared.                                    |
| review     | Submitted for DPO review.                           |
| completed  | Signed off; linked ROPA marked dpia_completed.     |
| archived   | Retained for audit history, no longer active.      |
