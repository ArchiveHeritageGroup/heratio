> Heratio Help Center article. Category: Digital Preservation.

# Archivematica Integration

Connect Heratio to an Archivematica (AM) instance in both directions: **receive** access copies (DIPs) from Archivematica, and **send** material to Archivematica for preservation processing. Provided by the `ahg-archivematica` package.

Heratio also has native preservation features (BagIt, fixity, PRONOM, PREMIS) - this integration is for sites that already run Archivematica and want the two systems linked.

---

## 1. Before you start

You need, from your Archivematica administrator:

- **Storage Service** URL + API username + API key (for receiving DIPs).
- **Dashboard** URL + API username + API key (for sending/monitoring transfers).
- A **pipeline UUID** and a **transfer source path** on the AM side (for sending).

## 2. Configure (Admin)

Go to **Admin > Archivematica** (`/admin/archivematica`) and fill in:

| Setting | Purpose |
|---|---|
| Storage Service URL / username / API key | Receiving DIPs (Direction 1) |
| Dashboard URL / username / API key | Sending + monitoring transfers (Direction 2) |
| Default pipeline UUID | Which AM pipeline to send transfers to |
| Transfer source path | Where AM picks up material you send |
| DIP match strategy | How an incoming DIP is matched to a Heratio record: **identifier** (default), **slug**, or **uuid** |

Then verify connectivity:

```
php artisan am:ping
```

It reports OK / FAIL / SKIP for the Storage Service and Dashboard URLs. SKIP means that URL is not configured yet.

## 3. Direction 1 - Receive DIPs from Archivematica

When Archivematica finishes, its **DIP** (access derivatives + METS/Dublin Core) comes into Heratio and is attached to the matching archival description.

**Two ways to bring DIPs in:**

- **Pull (scheduled):** Heratio polls the Storage Service for new DIPs.
  ```
  php artisan am:ingest-dips            # queue new DIPs
  php artisan am:ingest-dips --sync     # process inline
  php artisan am:ingest-dips --limit=5  # cap per run
  ```
  Add `am:ingest-dips` to the scheduler for hands-off ingest.

- **Push:** configure Archivematica (or its Storage Service) to POST the DIP to
  `POST /api/archivematica/dip` (API-key authenticated). Heratio unpacks and ingests it.

**Matching:** each DIP is linked to a record by the configured match strategy (identifier / slug / uuid). If a record was previously *sent* to AM from Heratio (Direction 2), the `uuid` strategy links it back automatically. Ingest is **idempotent** - a DIP already linked is skipped.

What you get: the access files appear as digital objects on the matched record, with PREMIS fixity/format captured in Heratio's preservation metadata, and a link row recording the DIP.

## 4. Direction 2 - Send material to Archivematica

From a record, start and monitor an Archivematica transfer.

- On the record, use **Send to Archivematica**. Heratio starts the transfer, approves it, and tracks it.
- Status (Transfer / SIP / AIP UUIDs) updates automatically while processing.
- Behind the scenes, a scheduled poll advances each job:
  ```
  php artisan am:poll
  ```
- When AM finishes, the resulting DIP can flow back in via Direction 1.

## 5. What gets stored

- **am_link** - the durable link between a Heratio record and its AM package (transfer / SIP / AIP / DIP UUIDs).
- **am_job** - tracks in-flight send-to-AM transfers and their status.

## 6. Security

- Storage Service and Dashboard API keys live in settings only, never in code.
- The inbound DIP endpoint requires an API key.
- Incoming METS is validated before ingest.

## 7. Troubleshooting

- **`am:ping` shows SKIP** - that URL is not configured yet.
- **`am:ping` shows FAIL** - check the URL, network path to the AM instance, and that AM's services are running.
- **DIP not attaching** - confirm the match strategy and that the record's identifier/slug matches what AM sends; check the ingest logs.
- **Transfer stuck** - run `am:poll` and check the AM dashboard for the transfer's microservice status.
