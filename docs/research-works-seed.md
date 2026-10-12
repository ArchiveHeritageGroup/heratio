# Seeding a researcher's own works

`php artisan ahg:research-works-seed <manifest.json> [--dry-run]` adds a researcher's papers, talks, workshops and thesis to their research workspace: a bibliography of works, a lecture record for each talk, and the final files.

It is safe to re-run. Researchers are matched by user, bibliographies by name, entries by title and talks by title, so a second run of the same manifest changes nothing. Files are copied only when they are missing or have changed.

That makes it suitable for a demo site whose database is restored from a snapshot every night. Run it after the restore. The restore does not touch files on disk, so only the database rows need re-applying.

## Where things live

- **The manifest and source files stay outside the repository.** The repository is public, and unpublished work does not belong in it.
- **Files are copied to `{HERATIO_STORAGE_PATH}/uploads/research-works/<folder>/`.** They are served at `/uploads/research-works/...`. Anyone with the link can open them.
- **A work with `"files": false` is listed by title only.** Use this for anything under anonymous review, or not yet published, so the review is not compromised.

## Manifest

```json
{
  "user_email": "researcher@example.org",
  "researcher": {
    "title": "Dr", "first_name": "Ada", "last_name": "Example",
    "institution": "Example Institute", "orcid_id": "0000-0000-0000-0000",
    "research_interests": "..."
  },
  "bibliography": { "name": "Publications and presentations", "description": "..." },
  "works": [
    {
      "title": "A paper that was presented",
      "entry_type": "other",
      "authors": "Example, A.",
      "date": "2026-07-01",
      "container_title": "Example Conference, City (1-3 July 2026)",
      "status": "Presented",
      "abstract": "...",
      "files": [
        { "src": "/path/to/paper.pdf", "label": "Paper (PDF)", "kind": "paper" },
        { "src": "/path/to/slides.pptx", "label": "Slides", "kind": "slides" }
      ],
      "talk": { "event": "Example Conference", "date": "2026-07-01 10:00:00",
                "location": "City", "duration_minutes": 20, "status": "delivered" }
    },
    { "title": "A paper under review", "entry_type": "article",
      "container_title": "Example Journal", "status": "Under review", "files": false }
  ]
}
```

- **`entry_type`** is one of `article`, `book`, `chapter`, `thesis`, `website` or `other`.
- **`talk.type`** is `talk` (the default), `curriculum` or `standalone`.
- **`talk.status`** is one of `draft`, `scheduled`, `delivered` (the default), `published` or `archived`.
- **A relative `src`** is resolved against the manifest's folder.

The command finds the user by email. If there is no user with that email, it stops with an error. A researcher record is created, or set to approved, for that user.
