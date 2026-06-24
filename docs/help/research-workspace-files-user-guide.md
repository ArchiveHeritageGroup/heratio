# Research Workspace Files

Researchers can attach files to a workspace - working documents, datasets,
images - directly in the research portal. Uploads count toward the researcher's
storage quota.

## Where to find it

Open a workspace and click **Files** in the header
(`/research/workspaces/{id}/files`). Workspace owners and accepted members can
see the files; only the owner can delete them.

## Uploading

1. Choose a file in the upload box and submit.
2. The file is stored against the workspace and recorded with its size and a
   SHA-256 checksum.
3. If the upload would exceed your storage quota it is blocked with a message;
   if it brings you near the limit you get a warning but the upload still
   completes.

The storage usage bar at the top of the page shows how much of your quota is
used.

## Downloading and deleting

- **Download** - available to the owner and members. Each download counts
  toward your download quota and is recorded in your activity log.
- **Delete** - owner only; removes the file from storage and the workspace.

## Large files

For a single very large file (over the normal upload limit), use the
**resumable upload** option on the ingest screen instead - see the *Resumable
(chunked) uploads* guide.

See also: *Researcher Download & Storage Quotas*.
