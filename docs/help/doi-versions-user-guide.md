> Heratio Help Center article. Category: Integration.

# Versioned Releases with DOIs

## What this feature does

Versioned releases turn a Portable Export into a citable, frozen publication. Each version receives its own DOI, and the series as a whole has a concept DOI that always leads to the newest version. A researcher can cite exactly what they used, while anyone following the concept DOI lands on the current release.

Nothing goes public by accident. A version is checked and frozen first, stays private until an administrator approves it, and is checked again before publishing. Only administrators can use this workflow.

## Before you start

You need four things in place.

A working DataCite account must be configured at `/admin/doi/config`, with a real DOI prefix and a password. Set **Environment** to **Test** while you learn the workflow. A registered DOI is permanent, so try the whole process there first and repeat it on Zenodo's sandbox if you plan to deposit there.

The site's configured address must be public, because a DOI registered against a private or local address could never resolve. Heratio refuses to approve a version otherwise.

The background queue worker must be running, since freezing and publishing happen outside the web page.

Finally, you need a completed Portable Export. It must be a read-only export built without master files. Researcher copies and editable packages cannot become public releases and do not appear in the pick lists.

## Add a deposit target

A deposit target says where the published package goes.

1. Open **Portable Export** at `/portable-export` and select **Versioned releases**.
2. Under **Deposit targets**, enter a **Name** and choose a **Type**.
3. For **This institution's repository**, nothing more is needed. The package stays on your server and is served from its public page.
4. For **Zenodo**, enter the **API address**, either `https://sandbox.zenodo.org` or `https://zenodo.org`, and an **API token**. The token is stored encrypted and never shown again. A **Community** is optional.
5. Select **Add target**.

Use **Deactivate** to stop a target being offered for new approvals.

## Create a release series

A series is the long-lived publication that versions belong to. On the **Versioned releases** page, under **New series**:

1. Choose the **Export that defines the scope**.
2. Enter a **Title**, and optionally a **Description**.
3. Enter the **Creator (as cited)** and **Publisher**.
4. Add a **Licence identifier** such as `cc-by-4.0`. It is required for an external deposit.
5. Select **Create series**.

Later versions must cover the same scope as the export you chose here.

## Freeze a version

Open the series and use **Freeze the next version**. Choose a **Completed export**, add **Release notes** if you wish, and select **Check and freeze**.

Heratio copies the package and judges every record against its current state: publication, ICIP and traditional knowledge protocols, rights policies, embargo, security classification and redactions. If anything fails, the reasons appear under **Recent freezes**. A large package can take a while, so reload the page to see the result.

Each export can be used only once per series. For a new version, build a new Portable Export first. The previous version must be released or abandoned before the next can be frozen.

A series built from a clipboard export has no fixed collection behind it, so Heratio compares the records themselves. A new version must keep at least half of the records in the version before it, and may add or withdraw the rest. A selection that keeps fewer is refused: start a new series for it.

## Approve and publish

A frozen version shows its version DOI, record and file counts, size and SHA-256 checksum. To publish it:

1. Choose the **Deposit target**.
2. Tick the confirmation. Registered DOIs and published external deposits cannot be undone.
3. Select **Approve and publish**.

Heratio repeats its checks, creates draft DOIs, makes the deposit, and only then makes the DOIs findable and links the versions together. Once finished, the version shows **Released** and a **Public page** button.

## Log corrections

If an error is found in a released version, record it under **Corrections for the next version** on the series page. Choose a **Type** (corrected figure, corrected description, withdrawn record or other), fill in the record reference, field, old and new values as they apply, and give a **Reason**. Select **Log correction**.

Corrections are published in the next version's changelog, so never enter restricted or personal information in them.

## Abandon a version

If a frozen or approved version must not go public, for example because a record in it has since been restricted, open **Abandon this version**, give a reason, tick the confirmation and select **Abandon version**. Its number is never reused, its corrections return to the pending list, and any unpublished draft deposit is deleted. A version whose DOI is already public cannot be abandoned, because that DOI is permanent. If publishing stopped after that point and a record has since been restricted, the version's public page stays up with its package withheld. To move on, build an export without the restricted record and freeze the next version. The stopped version keeps its number and is never published.

## The public page

Each released version has a public page that needs no login, showing how to cite it, its DOIs, what changed, all versions in the series, and links to download the package, manifest and changelog. An older version says that a newer one exists.

Records can change after release. If a released version no longer passes the disclosure checks, its page stays up so the citation remains valid, but the package and change list are withheld pending review.

A later version's change list names a withdrawn record only while that record may still be disclosed. Once it is restricted, the page shows a count instead, and that version's changelog file is withheld because a frozen file cannot be edited.

## Troubleshooting

If a freeze stays on **Waiting to freeze**, the queue worker is probably not running. Ask your system administrator to check it.

A refusal naming DataCite credentials or the prefix `10.12345` means the DOI configuration is incomplete. That prefix is only a documentation placeholder. Enter your real one at `/admin/doi/config`.

"APP_URL is not a public address" means the site's configured address is local or private. This must be corrected on the server before anything can be approved.

"This export covers a different scope from the series" means you need an export of the same collection or repository, or for a clipboard series, one that keeps at least half of the previous version's records. "This export has already been used" means you should build a fresh one.

If publishing fails partway, the error appears on the version, which stays **Approved**. Fix the cause and select **Retry publishing**. It carries on from the step that failed and does not repeat a completed deposit.

If the **Versioned releases** button is missing, your account is not an administrator. If the pages return "not found", Portable Export has been switched off in settings.
