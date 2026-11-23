# The ghcr.io download count for Heratio is not a usage measure (2026-10-27)

**Summary:** The GitHub package page for `ghcr.io/archiveheritagegroup/heratio` shows **7.41K total downloads**, which looks like thousands of users. The per-version counts say otherwise. Almost every one of the 1,633 tagged versions since May 2026 shows exactly 0 or 2 downloads, whatever its age. That matches our own release pipeline (an amd64 plus arm64 build, then an `imagetools inspect` read-back in `docker-publish.yml`), not client installs. Real installs would show surges on whichever version was `latest` at the time and a long tail on pinned versions; neither appears. The 8,188 untagged and per-architecture version entries add to the total. Do not quote the 7.41K as users or installs.

## Findings

- **Per-version counts.** The REST API does not return download counts. They come from the public package page (`/pkgs/container/heratio/versions?filters[version_type]=tagged`). Samples:
  - v1.142.78-85: 2, 2, 2, 2, 0, 2, 2, 2
  - v1.154.348-356: 0, 2, 2, 2, 0, 2, 0, 2
  - v1.159.1-7: 2, 2, 0, 2, 0, 2, 0
  - v1.164.12-16: 0, 4, 0, 2, 2
- **`ahg-archives-mcp`** shows 26 downloads, one day after it was published. This host holds 4 of its versions (0.4.0, 0.6.0, 0.6.1 and 0.7.1), so most of the 26 are our own.
- **Repo traffic**, 14 days to 27 Oct 2026: 1,676 clones (186 unique) and 34 page views (23 unique). The clones include our own prod and dev fetches and CI, so they don't count installs either.
- **Permissions.** Listing package versions needs the `read:packages` scope; it was added to the host's `gh` login (johanpiet2) on 27 Oct 2026. GitHub never shows who pulled an image.

## Follow-up

heratio#1571 asks for an opt-in, anonymous, one-time install ping in `docker/install-heratio.sh` as the real install count. Our own pipelines (CI, `bin/heratio-vm.sh`, the Docker test stack) are excluded.

Related: heratio#1570 asks for the wrong company name "The Archive and Heritage Group" to be corrected, including the OCI vendor label on the package page.
