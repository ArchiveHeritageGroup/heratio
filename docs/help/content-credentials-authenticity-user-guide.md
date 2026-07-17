> Heratio Help Center article. Category: Collection Mgmt / Provenance.

# Content Credentials and Authenticity User Guide

## Overview

Content Credentials and Authenticity let anyone confirm that a record or file is what it claims to be, and see where it came from. A public **/verify** surface checks an object and shows its provenance chain - the sequence of steps and custodians behind it. For catalogue records, a record-level provenance trace at **/verify/record/{id}/trace** lays out how the description was formed. You can embed a verify badge on another page, and you can download files **with content credentials** so the proof travels with the file. Open it at **/verify**.

---

## What it does

This feature gives records and files a checkable trail of authenticity and origin:

- It provides a **/verify** surface where a record or file can be checked and its status reported.
- It shows a **per-object provenance chain** - the ordered history of where the object came from and what has happened to it.
- It offers a **record-level provenance trace** at **/verify/record/{id}/trace**, explaining how a catalogue description was assembled, including contributing sources or processing steps.
- It supplies an **embeddable verify badge** you can place on an external page so visitors can confirm authenticity without leaving that page.
- It supports **download with content credentials**, so a downloaded file carries its provenance and authenticity information with it rather than losing that context.

The aim is trust: letting researchers, partners, and the public satisfy themselves that what they are looking at is genuine and traceable.

---

## How to use it

1. **Verify an object or file:** go to **/verify** and check the item. The page reports its authenticity status and shows the provenance chain behind it.
2. **Read a record's provenance trace:** open **/verify/record/{id}/trace**, replacing `{id}` with the record's identifier (for example `https://your-site.example/verify/record/1234/trace`), to see how that description was formed.
3. **Embed the verify badge:** add the provided badge snippet to an external page. Visitors can click it to confirm the item's authenticity against the verify surface.
4. **Download with content credentials:** when downloading a file, choose the option to include content credentials so the file carries its provenance and authenticity details with it.
5. Use the chain and trace to cite provenance accurately in research, reports, or rights decisions.

---

## Good to know

- The provenance chain answers "where did this come from and what happened to it?", while the record-level trace answers "how was this catalogue description built?" - they are complementary views.
- Content credentials make a downloaded file self-describing: even away from the platform, the file can be checked against its recorded provenance. Images/video (JPEG, PNG, TIFF, MP4) and now **PDFs** (#1387) carry the credential **inside the file itself**; a PDF's credential rides along as an embedded attachment and the document still opens and renders exactly the same in any viewer. Other formats travel with a small sidecar file instead.
- The verify badge is a convenient way to extend trust to partner sites, exhibitions, and publications without copying data around.
- Verification reflects the provenance the platform holds. A clean result confirms the recorded chain is intact; it does not assert anything beyond what has been documented.
- Verification respects access rules - a trace will not reveal content you are not permitted to see.
