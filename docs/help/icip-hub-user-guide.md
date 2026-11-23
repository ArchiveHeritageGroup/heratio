> Heratio Help Center article. Category: Integration.

# Connecting ICIP Labels to the Local Contexts Hub

## What this feature does

Communities use the Local Contexts Hub to apply Traditional Knowledge and Biocultural Labels and Notices to their projects. This integration lets Heratio fetch those projects, so that a label on a record can carry the community's own name, wording and link in place of the generic catalogue text.

Heratio keeps working without the Hub. Labels always have their local catalogue wording to fall back on, and record pages never wait on a live call to the Hub.

## Current status and limits

**The connection has not yet been tested against a live Hub.** It needs Hub credentials and real project IDs, and so far it has only been exercised against a simulated Hub. Start on the Hub's sandbox and check the results before relying on it.

Several things are also not done yet. Notices are synced and stored, but Hub notices are **not shown on record pages**. Hub images and audio are not displayed either. Hub wording appears only for labels linked to a project whose privacy is **Public**. Labels linked to a Contributor project keep the local catalogue text, and Private projects are never stored.

## Step 1: Get your Hub details

On the Hub, or on `https://sandbox.localcontextshub.org` while testing, the account holder creates or opens a project and applies its labels. Note the project's **Unique ID**, which is a UUID, and generate an **API key** in the account settings. The sandbox and the live Hub have separate accounts and keys.

## Step 2: Configure Heratio

1. Go to **Admin > AHG Settings** and open the **ICIP Settings** tile (`/admin/ahgSettings/icipSettings`).
2. Expand the **Local Contexts** section.
3. Turn on **Enable Local Contexts Hub API**.
4. Paste the key into **Local Contexts API Key**. It is stored encrypted and is never displayed again, so leave the field blank on later saves to keep it.
5. In **Local Contexts Hub URL**, enter `https://sandbox.localcontextshub.org` for testing or `https://localcontextshub.org` for the live Hub. No other address is accepted.
6. In **Local Contexts Project IDs**, enter one or more Unique IDs separated by commas or spaces.
7. Save.

Without a key, Heratio falls back to the Hub's older keyless interface, which can read Public projects only. Use a key for anything beyond a quick trial.

## Step 3: Run a first sync

An administrator with server access runs the sync by hand:

```bash
sudo -u www-data php artisan ahg:icip-hub-sync
```

To sync a single project, add its ID:

```bash
sudo -u www-data php artisan ahg:icip-hub-sync --project=<unique-id>
```

Each project reports how many labels and notices it synced, or why it failed. A project synced with `--project` that is not in your configured list is stored but not shown, and the command warns you.

## Step 4: Link labels on records

Syncing alone changes nothing on a record. A label picks up the Hub's wording only when it is linked to a project.

1. Open the record's ICIP labels page at `/admin/icip/object-labels?slug=<record-slug>`.
2. Choose the label, and the **Community** and **Applied By** values.
3. Enter the project's Unique ID in **Local Contexts Project ID**.
4. Select **Add Label**.

The project ID can only be entered when a label is added. To link a label already on the record, remove it and add it again with the ID.

The record page then shows the community's wording, in the viewer's language where the Hub offers a translation. The label must be of the same type as one applied to that Hub project.

## Step 5: Schedule the sync

Once a manual sync succeeds, go to **Admin > AHG Settings > Cron Jobs**, find **Local Contexts Hub Sync** and enable it. It ships switched off and runs daily at 05:00 when enabled. Its log is `storage/logs/icip-hub-sync.log`.

## Moving from the sandbox to the live Hub

Change the Hub URL, replace the API key and project IDs, and sync again. Sandbox and live projects are kept apart, and the first successful sync against the new Hub clears the rows stored from the old one. Project IDs on existing labels are not changed, so labels linked to sandbox projects must be removed and added again with their live IDs.

## Troubleshooting

If the command says the integration is disabled, turn on **Enable Local Contexts Hub API** and save. "No Hub project ids configured" means the **Local Contexts Project IDs** field is empty.

When a project reports "fetch failed", check the application log in `storage/logs` for the reason. A 401 or 403 means the key is missing, invalid or cannot read that project. A 404 means the ID is wrong or the project is Private. Check too that the key and the project belong to the same Hub, since a sandbox key will not work on the live one.

If a record still shows the generic wording after a successful sync, confirm that the label has the project ID, that the project is Public, that the ID is in your configured list, and that the same label type is applied on the Hub.

If a notice from the Hub is missing on a record, that is the known limit described above. Enter the notice in Heratio's own ICIP notices for now.
