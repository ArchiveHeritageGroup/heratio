# Security policy

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately instead:

- **On GitHub:** the repository's Security tab, then "Report a vulnerability". The report stays private between you and the maintainers.
- **By email:** johan@theahg.co.za, with "Heratio security" in the subject line.

Tell us which version you tested (it is in the page footer and in `version.json`), the steps to reproduce, and what an attacker could gain. We acknowledge reports within five working days. Once a fix ships we publish a GitHub security advisory and credit you, unless you would rather stay anonymous.

## Supported versions

Heratio ships as one line of releases from `main`. Security fixes go into the next patch release, so the supported version is **the latest release** on <https://github.com/ArchiveHeritageGroup/heratio/releases>. Older releases do not get backported fixes. Moving up is the fix, and [docs/upgrade.md](docs/upgrade.md) covers how.

## What Heratio does for you

This list describes the current release. Each item is in the code, not on a roadmap.

Sign-in is rate limited: five attempts a minute per address and per account, and three password-reset requests a minute per address. Users can turn on time-based one-time passwords (TOTP), and once they do, every page waits until the second factor is entered. Idle sessions time out.

Access control works at three levels. Role-based permissions (ACL) decide what a user may do. Security classification levels decide which records a user may see at all. ODRL rights policies on individual descriptions decide who may view or reproduce them. Files belonging to unpublished or restricted records are refused to anyone who is not staff.

Every response carries a nonce-based Content-Security-Policy and hardening headers: X-Frame-Options, X-Content-Type-Options, Permissions-Policy and the cross-origin opener and resource policies, plus Strict-Transport-Security when the site is served over HTTPS. Forms are protected against cross-site request forgery.

Changes to records are written to an audit trail. Personal information in digital objects can be redacted for public copies, and restricted holdings can be encrypted at rest with a key you generate (`php artisan heratio:generate-encryption-key`). Settings pages never print a stored password or API key back into the page.

## What stays your job

Heratio does not provision TLS, harden the operating system, keep MySQL and Elasticsearch off the public network, or encrypt and rotate your backups. [docs/security.md](docs/security.md) has the operator checklist: a pre-deployment check, routine tasks, incident response and the port inventory.
