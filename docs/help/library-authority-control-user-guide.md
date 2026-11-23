> Heratio Help Center article. Category: Library.

# Authority Control: Hierarchy and Vocabulary Links

Authority records hold the agreed form of a subject, name, title or genre heading. Heratio now lets you type those records, connect them into broader and narrower hierarchies, and let catalogue searchers include narrower terms automatically. A record can also be linked to a Library of Congress Subject Headings (LCSH) identifier.

Please read the section on the LCSH link before relying on it. That connection has been tested only against simulated responses and has not yet been confirmed against the live service.

## Where to find it

Open `/library-manage/authority`. There is no button for it on the Library dashboard at present. From a record in the MARC Editor, the subject section also offers links to an authority record or to create one. Editing needs the update permission.

## Create or edit an authority record

1. On the authority list, choose **New Authority**.
2. Enter the **Heading**, which is required, and choose the **Record Type**, **Subject Type** and **Source**. Record types are Subject, Name, Title and Genre / Form. Add a URI if you have one.
3. Choose **Save**.
4. To add more detail, open the record and choose **Edit**. The edit form adds the **LC Label**, **RDA Label**, **Authorized Form**, **Vocabulary Code**, **Vocabulary URI** and notes.
5. Choose **Save Changes**.

These lists come from the Dropdown Manager, so an administrator can adjust them for your institution. The authority list can be filtered by record type.

## Build a hierarchy

The **Hierarchy** panel on an authority record's page shows its broader and narrower terms.

1. Open the record.
2. Under **Add**, choose **Broader term** or **Narrower term**.
3. Type the exact heading of the other record, or its record ID.
4. Choose **Add**.

The other record must already exist, and the heading must identify a single record. If two records share it, use the ID. Heratio refuses a link from a term to itself and a link that would create a loop.

To remove a link, choose the cross beside the term. A removed link is remembered, so a later vocabulary sync will not put it back.

## Search with narrower terms

The public catalogue search has a checkbox, **Include narrower subject terms**. When it is ticked, a search that matches an authority heading also finds records under terms up to three levels below it, to a limit of 200 terms. A search for a broad subject can then find items catalogued under its more specific headings.

The checkbox is not carried through when a searcher changes the number of results per page. If that happens, tick it again and repeat the search.

## Linking to LCSH

The **External vocabulary** section of the Hierarchy panel holds an LCSH identifier, such as `sh85034652`. Choosing **Sync from vocabulary** is designed to read that concept from the Library of Congress linked data service and then update the record.

A sync is designed to update the heading and URI, add broader and narrower relations, and create records for related terms that do not exist locally. It leaves local relations alone. If staff edit the heading or URI of a linked record, that field becomes a **local override** and later syncs skip it. **Clear overrides** hands those fields back to the sync.

### What needs checking first

The LCSH connection is unproven. Its automated tests use simulated responses, so nobody has yet confirmed that it reads the live service correctly. Treat it as unverified until an administrator has done the following on a test or development system:

1. Confirm the server can reach `id.loc.gov` over HTTPS.
2. Sync one record with a known identifier.
3. Compare the heading, URI and broader and narrower terms with the Library of Congress page for that concept.
4. Check how many new authority records the sync created, because each related term that is missing locally becomes a record.
5. Sync the same record again and confirm nothing is duplicated.

Do not run a bulk sync on a production catalogue until these checks pass.

## Administrator commands

Older records may hold broader and narrower terms as flat lists. This command turns them into real relations, and the dry run reports the result without writing:

```bash
php artisan ahg:library-authority-backfill-hierarchy --dry-run
php artisan ahg:library-authority-backfill-hierarchy
```

It reports how many terms were linked, could not be resolved, or were rejected. A term is rejected when staff previously removed that link or when it would create a loop.

The sync command refreshes linked records, least recently synced first, 100 at a time by default:

```bash
php artisan ahg:library-authority-sync --limit=100
```

This command is not scheduled and is subject to the checks above. Failed records keep their place at the front of the queue, so a set of permanently failing identifiers can stop other records from being reached. Clear or correct failing identifiers before running it repeatedly.

## Troubleshooting

If you see "No single authority record matches that heading or ID", the heading is either not exact or shared by more than one record. Enter the record ID instead.

"Enter a valid LCSH identifier" means the value is not in the form `sh` followed by digits. "Another authority record is already linked to that identifier" means the concept already has a local record, so work with that one.

"The vocabulary service could not be reached" points to a network or firewall problem between your server and the service. Any failure is shown in red under the identifier on the record's page, and the existing record is left as it was.
