# Heratio — PR Review Checklist

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Overview

Use this checklist when reviewing pull requests for `atom-framework` and `atom-ahg-plugins`. Every PR must pass all applicable sections before merge.

---

## 1. General

- [ ] PR title is concise and descriptive (under 70 characters)
- [ ] PR description explains **what** changed and **why**
- [ ] Changes are limited to the stated scope (no unrelated modifications)
- [ ] No base AtoM files modified (`apps/`, `lib/`, `plugins/` core, `vendor/`)
- [ ] No locked plugin files modified without explicit approval
- [ ] Code follows PHP CS Fixer standards
- [ ] No `TODO` or `FIXME` comments left unresolved

---

## 2. Security — Shell Execution

- [ ] All `exec()`, `shell_exec()`, `system()`, `passthru()` calls use `escapeshellarg()` on external input
- [ ] No `addslashes()` used in shell contexts
- [ ] Directory paths validated with `realpath()` before `cd`
- [ ] Service names validated against allowlist for `systemctl` operations
- [ ] Complex shell commands use `ShellCommandService` builders
- [ ] No user input directly interpolated into shell command strings

**Reference:** [SHELL_EXECUTION_POLICY.md](docs/technical/SHELL_EXECUTION_POLICY.md)

---

## 3. Security — Outbound HTTP

- [ ] All outbound HTTP uses `HttpClientService` (no direct `curl_init()`)
- [ ] No `CURLOPT_SSL_VERIFYPEER => false` in production code
- [ ] User-provided URLs are never fetched without SSRF protection
- [ ] XML parsing uses `XmlParserService` (no direct `simplexml_load_string()`)
- [ ] Response bodies are size-limited and validated before processing

**Reference:** [OUTBOUND_HTTP_POLICY.md](docs/technical/OUTBOUND_HTTP_POLICY.md)

---

## 4. Security — CSRF

- [ ] All POST/PUT/DELETE forms include `<?php echo csrf_field() ?>`
- [ ] AJAX handlers either use `csrf.js` or manually send `X-CSRF-TOKEN` header
- [ ] API endpoints using Bearer/API-Key auth are correctly exempt
- [ ] Controllers that disable CSRF (`$csrfProtection = false`) have documented justification
- [ ] No CSRF tokens exposed in URLs or GET parameters

**Reference:** [CSRF_POLICY.md](docs/technical/CSRF_POLICY.md)

---

## 5. Security — Content Security Policy

- [ ] All `<script>` tags include the CSP nonce snippet
- [ ] All `<style>` tags include the CSP nonce snippet
- [ ] No `'unsafe-inline'` added to CSP directives
- [ ] New external CDN domains added to `config/app.yml` allowlists (documented in PR)
- [ ] Inline event handlers (`onclick`, `onload`, etc.) are avoided — use event listeners

**Reference:** [SECURITY_MODEL.md](docs/technical/SECURITY_MODEL.md)

---

## 6. Security — Input Validation

- [ ] User input is validated before use (type, length, format)
- [ ] SQL queries use Laravel Query Builder parameterized methods (no raw string concatenation)
- [ ] HTML output uses `htmlspecialchars()` or Blade `{{ }}` escaping
- [ ] File uploads validated via `FileValidationService` (MIME, extension, magic bytes)
- [ ] No `unserialize()` on user-controlled data (use `json_decode()` instead)

---

## 7. Security — Database

- [ ] No `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `DROP` without documented justification
- [ ] Plugin Manager code uses PDO/Propel (not Laravel Query Builder)
- [ ] Everything else uses Laravel Query Builder (not raw PDO)
- [ ] No core AtoM table schema modifications
- [ ] `install.sql` does NOT contain `INSERT INTO atom_plugin`

---

## 8. Architecture

- [ ] New services follow namespace convention (`AtomFramework\Services\*` or `AtomExtensions\*`)
- [ ] Plugin code stays within its own plugin directory (no cross-plugin imports)
- [ ] Templates use direct property access, not `render_title()` or `__toString()`
- [ ] URL generation uses named routes or `url_for()` helper
- [ ] Form fields avoid `name="action"` (Symfony reserved parameter)

---

## 9. Testing

- [ ] Changes tested on development instance (192.168.0.112)
- [ ] Functionality verified in browser
- [ ] No PHP syntax errors (`php -l` on changed files)
- [ ] No JavaScript console errors
- [ ] Cache cleared after testing (`php symfony cc`)

---

## 10. Documentation

- [ ] User-facing changes have updated User Guide (`.md` + `.docx`)
- [ ] New plugins have Feature Overview document
- [ ] New CLI commands are documented with `--help` text
- [ ] New settings are documented in the relevant section guide
- [ ] CLAUDE.md updated if new locked plugins or architectural changes

**Reference:** [DOCUMENTATION_STANDARD.md](docs/DOCUMENTATION_STANDARD.md)

---

## 11. Git / Release

- [ ] Commit messages are descriptive and follow conventions
- [ ] Version bumped via `./bin/release` (patch/minor/major as appropriate)
- [ ] No secrets committed (credentials, API keys, tokens)
- [ ] No large binary files committed
- [ ] `.gitignore` updated if new generated/cached file types introduced
