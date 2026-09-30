> Heratio Help Center article. Category: Collections Management / Accessions.

# CAAIS Profile for Accessions

The CAAIS profile adds the Canadian Archival Accession Information Standard to accession records. CAAIS Final 1.0 was published by the Canadian Council of Archives on 15 May 2019. It is an optional module. The core accession record does not change, and an institution that never switches the profile on sees only one new field, Repository.

## What it adds

With the profile switched on, the accession edit form gains a **Repository and CAAIS profile** section, and the view page shows the same information under the Administrative area.

| CAAIS element | What you record | Where it lives |
| --- | --- | --- |
| 1.1 Repository | The repository that accepts legal responsibility for the material | Always shown, profile on or off |
| 2.1.6 Source confidentiality | A handling instruction per linked donor, such as "Donor wishes to remain anonymous" | Profile |
| 3.2 Extent statement | One row per statement: extent type, quantity (with a "ca." tick for estimates), unit, content type, carrier type, digital file formats, note | Profile |
| 3.4 Language of material | A language, plus an optional statement such as "with partial English translation" | Profile |
| 4.3 Preservation requirements | Type, requirement and note, one row each | Profile |
| 5.1 Events | Event type, date, agent and note. CAAIS requires at least the physical transfer and, if different, the legal transfer | Profile |
| 7.1 Rules or conventions | The standard or template the record follows | Profile |
| 7.2 Date of creation or revision | Written for you: who created or saved the record, and when | Always recorded |

: CAAIS elements added by the profile

Everything else CAAIS asks for already exists on the core accession record, and the export reads it from there: identifier and alternative identifiers, title, acquisition method, status, donors, custodial history, dates, scope and content, location, rights, appraisal and processing notes.

## Switching it on

Go to **Admin > AHG Settings > Accession** and turn on **CAAIS profile**. Turning it off later hides the CAAIS fields again but keeps everything recorded while it was on. Saving a record with the profile off never deletes CAAIS data.

## Recording an accession

1. Open the accession and choose **Edit**, then expand **Repository and CAAIS profile**.
2. Pick the repository.
3. Add extent statements. Record at least the extent received. If you later weed the material, add an "Extent retained" or "Extent removed" row rather than changing the first one.
4. Add the transfer events. The physical transfer is the one CAAIS insists on; add the legal transfer too when the deed of gift was signed on a different day.
5. Mark any source that must stay confidential. The list shows donors already linked, so on a brand-new accession link the donor, save, and come back.
6. Save. Every save adds a line to the creation and revision history with your username.

Each repeatable table starts with one empty row. Use **Add** to get more rows and the cross to remove one. An empty row is ignored when you save. Once you fill in anything else on a row, its type becomes required.

If the record is missing something CAAIS makes mandatory, the view page lists it in a yellow box, for example "5.1 - Event: physical transfer". The list clears as you fill the gaps. It never blocks saving.

## Vocabularies

Every drop-down in the profile is a Dropdown Manager taxonomy, so you can add, rename or retire terms at **Admin > Dropdown Manager** without a code change. CAAIS itself asks each repository to maintain its own controlled vocabularies, and the seeded terms are the examples the standard gives.

| Taxonomy | Used for |
| --- | --- |
| caais_extent_type | 3.2.1 Extent type |
| caais_extent_unit | 3.2.2 Unit of measure |
| caais_content_type | 3.2.3 Content type |
| caais_carrier_type | 3.2.4 Carrier type |
| caais_source_confidentiality | 2.1.6 Source confidentiality |
| caais_language | 3.4 Language of material |
| caais_preservation_type | 4.3.1 Preservation requirement type |
| caais_event_type | 5.1.1 Event type |
| caais_revision_type | 7.2.1 Creation or revision type |

: Dropdown Manager taxonomies used by the CAAIS profile

Keep the codes `physical_transfer` and `legal_transfer` in the event type taxonomy. You can relabel them, but the mandatory-element check looks for those two codes.

## Exporting

Administrators see two export links on the view page:

- **Full record (JSON)** downloads the accession as a CAAIS record, donor contact details included. It is meant for your own systems.
- **For sharing** leaves out every source marked confidential. Use it for anything that leaves the institution.

`/accession/caais-export` exports every accession in one file; add `?external=1` for the sharing version.

The Canadian Council of Archives publishes CAAIS as a list of elements, not as an XML schema or a JSON format, so the export is JSON whose keys follow the element names. Each file starts with a `crosswalk` block giving the CAAIS element number for every key and the Heratio field it came from, so the receiving system can map it without reading Heratio's code. When nothing structured has been recorded, the free-text Received extent units and Physical condition fields are exported as the extent note and the preservation requirement.

## Permissions

Viewing and editing follow the normal accession permissions. The exports are for administrators only, because the full record carries donor contact details.
