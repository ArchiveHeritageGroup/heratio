> Heratio Help Center article. Category: User Guide.

# Heratio - ORCID Integration: User Manual

**Version:** 1.0.0
**Date:** May 2026
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [What ORCID Gives You](#2-what-orcid-gives-you)
3. [Fetch from ORCID (registration)](#3-fetch-from-orcid-registration)
4. [Fetch from ORCID (profile)](#4-fetch-from-orcid-profile)
5. [Connect & Sync (full OAuth)](#5-connect--sync-full-oauth)
6. [The ORCID page](#6-the-orcid-page)
7. [Scheduled sync](#7-scheduled-sync)
8. [Public vs Member API](#8-public-vs-member-api)
9. [Configuration](#9-configuration)
10. [Privacy](#10-privacy)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Introduction

ORCID (Open Researcher and Contributor ID) is a free, persistent digital identifier that distinguishes a researcher from every other researcher with the same or a similar name. An ORCID iD looks like `0000-0002-1670-2416`.

Heratio integrates with ORCID so that researchers can:

- Auto-populate their researcher profile from their public ORCID record (no retyping)
- Link their ORCID iD to their Heratio researcher account
- Pull their publication list ("Works") from ORCID into the archive
- Push citations from the archive back to their ORCID Works

There are two levels of integration, and you can use either or both:

| Surface | Auth | What it does |
|---|---|---|
| **Fetch from ORCID** | None (public) | Reads your public ORCID record by iD and fills in profile fields |
| **Connect & Sync** | OAuth (ORCID password at orcid.org) | Stores an access token so Heratio can pull your Works and push citations |

---

## 2. What ORCID Gives You

Linking saves time and improves data quality:

- **Name disambiguation** - your authorship is recognised correctly even if another researcher shares your name.
- **One-click profile** - first name, last name, institution, department, position and research interests come straight from ORCID.
- **Publication sync** - your Works list is pulled in, so reproduction requests and bibliographies cite you correctly.
- **Citation push** - items you work with in the archive can be added to your ORCID record.

---

## 3. Fetch from ORCID (registration)

When you register as a researcher (`/research/publicRegister` or the staff register form):

1. Type your ORCID iD into the **ORCID iD** field (format `0000-0000-0000-0000`).
2. Click **Fetch from ORCID**.
3. Heratio reads your public ORCID record and fills in the empty fields: first name, last name, institution, department, position, research interests, and email where public.
4. Review the filled values, complete anything still blank, and submit.

The Fetch button only fills **empty** fields - it never overwrites something you have already typed. If a field differs, it tells you which ones it left alone so you can clear and re-fetch to overwrite.

---

## 4. Fetch from ORCID (profile)

The same **Fetch from ORCID** button is on your researcher profile page (`/research/profile`), beside the ORCID iD field. Use it any time to refresh your profile fields from your public ORCID record.

---

## 5. Connect & Sync (full OAuth)

The **Connect & Sync with ORCID** button (on the profile page and the ORCID page) performs the full three-legged OAuth handshake:

1. You are redirected to orcid.org.
2. You sign in **at ORCID** with your ORCID iD and password (Heratio never sees your password).
3. You authorise Heratio to read your record and update your Works.
4. ORCID redirects you back; Heratio stores an encrypted access token.

Once connected, Heratio can pull your Works list and push citations. This is the level you need for publication sync, not just profile auto-fill.

---

## 6. The ORCID page

`/research/orcid` is your ORCID control panel:

- **Linked state** - shows your ORCID iD, scope, token expiry, last works-sync time, last profile-sync time, and works count.
- **Pull profile from ORCID** - refresh your profile fields from the public record (works even without a full OAuth link, using the iD on file).
- **Pull Works from ORCID** - import your publications list (requires Connect & Sync first, for the token).
- **Unlink** - remove the ORCID link.

If you have not linked yet but your profile already has an ORCID iD on file, the page offers a **Pull profile from ORCID** button directly.

---

## 7. Scheduled sync

An Artisan command keeps linked researchers in sync automatically:

```
php artisan ahg:orcid-sync          # all linked researchers
php artisan ahg:orcid-sync --researcher=25
php artisan ahg:orcid-sync --force  # ignore the 24h freshness window
```

It runs daily at **01:30** via the Laravel scheduler. It short-circuits cleanly when ORCID is not configured.

---

## 8. Public vs Member API

ORCID offers two API tiers; Heratio supports both via the `ORCID_API_BASE` setting:

| API | Host | Cost | Capability |
|---|---|---|---|
| **Public** | `pub.orcid.org` | Free | Read public record (Fetch + Pull profile) |
| **Member** | `api.orcid.org` | Paid ORCID membership | Read limited-access data + write Works (push citations) |

The free Public API is enough for Fetch-from-ORCID and profile pull. Pushing citations to a researcher's Works needs the Member API plus the researcher's OAuth authorisation.

---

## 9. Configuration

An administrator sets these in `.env`, then runs `php artisan config:clear`:

| Key | Example | Notes |
|---|---|---|
| `ORCID_CLIENT_ID` | `APP-A1B2C3D4E5F6G7H8` | From orcid.org/developer-tools (not an ORCID iD) |
| `ORCID_CLIENT_SECRET` | `ab12cd34-ef56-...` | A UUID secret (not a password) |
| `ORCID_REDIRECT_URI` | `https://your-host/research/orcid/callback` | Must match the app registration exactly |
| `ORCID_BASE` | `https://orcid.org` | Use `https://sandbox.orcid.org` for testing |
| `ORCID_API_BASE` | `https://pub.orcid.org` | Use `https://api.orcid.org` for the Member API |

Register a free client at https://orcid.org/developer-tools. Until `ORCID_CLIENT_ID` and `ORCID_CLIENT_SECRET` are set, the Fetch and Connect buttons return "ORCID lookup is not configured on this server" - that is the expected, safe state, not an error.

---

## 10. Privacy

- Heratio reads only the **public** portion of an ORCID record unless the researcher authorises more via Connect & Sync.
- The researcher's ORCID password is entered only at orcid.org during OAuth; Heratio never receives or stores it.
- Access tokens are stored encrypted at rest (`researcher_orcid_link.access_token_encrypted`).
- A researcher can unlink at any time, which deletes the stored token.

---

## 11. Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| "ORCID lookup is not configured" | `ORCID_CLIENT_ID`/`SECRET` empty | Add real app credentials + `config:clear` |
| "That does not look like a valid ORCID iD" | Wrong format | Use `0000-0000-0000-0000` (16 digits, last may be X) |
| "No public ORCID record found" | iD has no public data, or typo | Verify the iD resolves at `https://orcid.org/<id>` |
| Pull Works says "no token" | Profile linked by iD only, never OAuth-authorised | Click **Connect & Sync** to authorise |
| Token expired | OAuth token past expiry | Re-run Connect & Sync |
| Too many lookups (429) | Rate limit (20/min per IP) | Wait a minute and retry |

---

For technical operators, see `docs/reference/orcid-integration-implementation.md`.
