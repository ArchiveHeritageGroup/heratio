> Heratio Help Center article. Category: Technical.

# Heratio — CSRF Protection Policy

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

Cross-Site Request Forgery (CSRF) attacks trick authenticated users into submitting unintended requests. Heratio uses a per-session token mechanism via `CsrfService` to protect all mutating (POST/PUT/DELETE/PATCH) endpoints.

**Current status:** Rollout in progress. Default mode is `'log'` (warn but don't block).

---

## 2. Architecture

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 438 276" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="437" height="275" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="42.4" y1="26.0" x2="42.4" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="34.0" x2="42.4" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="42.0" x2="42.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="50.0" x2="42.4" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="49.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="58.0" x2="42.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="66.0" x2="42.4" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="82.0" x2="46.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="74.0" x2="42.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="82.0" x2="42.4" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="82.0" x2="49.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="82.0" x2="53.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="90.0" x2="42.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="98.0" x2="42.4" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="146.0" x2="46.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="138.0" x2="42.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="146.0" x2="42.4" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="146.0" x2="49.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="146.0" x2="53.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="162.0" x2="46.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="154.0" x2="42.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="162.0" x2="42.4" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="162.0" x2="49.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="162.0" x2="53.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="178.0" x2="46.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="170.0" x2="42.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="178.0" x2="42.4" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="178.0" x2="49.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="178.0" x2="53.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="194.0" x2="46.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="186.0" x2="42.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="194.0" x2="42.4" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="194.0" x2="49.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="194.0" x2="53.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="202.0" x2="42.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="210.0" x2="42.4" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="210.0" x2="74.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="202.0" x2="71.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="210.0" x2="71.2" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="210.0" x2="78.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="210.0" x2="82.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="218.0" x2="42.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="226.0" x2="42.4" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="226.0" x2="74.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="218.0" x2="71.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="226.0" x2="78.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="226.0" x2="82.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><path d="M38.4 109.0 L42.4 116.0 L46.4 109.0 Z" fill="#10373E"/><path d="M318.2 142.0 L325.2 146.0 L318.2 150.0 Z" fill="#10373E"/><path d="M318.2 158.0 L325.2 162.0 L318.2 166.0 Z" fill="#10373E"/><path d="M159.8 206.0 L166.8 210.0 L159.8 214.0 Z" fill="#10373E"/><path d="M159.8 222.0 L166.8 226.0 L159.8 230.0 Z" fill="#10373E"/><path d="M38.4 237.0 L42.4 244.0 L46.4 237.0 Z" fill="#10373E"/><text x="10.0" y="22.0" font-size="11.5" fill="#10373E">Browser</text><text x="67.6" y="22.0" font-size="11.5" fill="#10373E">Form</text><text x="103.6" y="22.0" font-size="11.5" fill="#10373E">Submit</text><text x="60.4" y="54.0" font-size="11.5" fill="#10373E">POST</text><text x="96.4" y="54.0" font-size="11.5" fill="#10373E">body:</text><text x="139.6" y="54.0" font-size="11.5" fill="#10373E">_csrf_token=&lt;token&gt;</text><text x="60.4" y="70.0" font-size="11.5" fill="#10373E">OR</text><text x="60.4" y="86.0" font-size="11.5" fill="#10373E">Header:</text><text x="118.0" y="86.0" font-size="11.5" fill="#10373E">X-CSRF-TOKEN:</text><text x="218.8" y="86.0" font-size="11.5" fill="#10373E">&lt;token&gt;</text><text x="283.6" y="86.0" font-size="11.5" fill="#10373E">(AJAX)</text><text x="10.0" y="134.0" font-size="11.5" fill="#10373E">CsrfService::enforce()</text><text x="60.4" y="150.0" font-size="11.5" fill="#10373E">Is</text><text x="82.0" y="150.0" font-size="11.5" fill="#10373E">method</text><text x="132.4" y="150.0" font-size="11.5" fill="#10373E">safe</text><text x="168.4" y="150.0" font-size="11.5" fill="#10373E">(GET/HEAD/OPTIONS)?</text><text x="334.0" y="150.0" font-size="11.5" fill="#10373E">PASS</text><text x="60.4" y="166.0" font-size="11.5" fill="#10373E">Has</text><text x="89.2" y="166.0" font-size="11.5" fill="#10373E">Bearer</text><text x="139.6" y="166.0" font-size="11.5" fill="#10373E">token</text><text x="182.8" y="166.0" font-size="11.5" fill="#10373E">or</text><text x="204.4" y="166.0" font-size="11.5" fill="#10373E">API</text><text x="233.2" y="166.0" font-size="11.5" fill="#10373E">key?</text><text x="334.0" y="166.0" font-size="11.5" fill="#10373E">PASS</text><text x="370.0" y="166.0" font-size="11.5" fill="#10373E">(exempt)</text><text x="60.4" y="182.0" font-size="11.5" fill="#10373E">Extract</text><text x="118.0" y="182.0" font-size="11.5" fill="#10373E">token</text><text x="161.2" y="182.0" font-size="11.5" fill="#10373E">from</text><text x="197.2" y="182.0" font-size="11.5" fill="#10373E">POST</text><text x="233.2" y="182.0" font-size="11.5" fill="#10373E">or</text><text x="254.8" y="182.0" font-size="11.5" fill="#10373E">header</text><text x="60.4" y="198.0" font-size="11.5" fill="#10373E">hash_equals()</text><text x="161.2" y="198.0" font-size="11.5" fill="#10373E">against</text><text x="218.8" y="198.0" font-size="11.5" fill="#10373E">session</text><text x="276.4" y="198.0" font-size="11.5" fill="#10373E">token</text><text x="89.2" y="214.0" font-size="11.5" fill="#10373E">Match</text><text x="175.6" y="214.0" font-size="11.5" fill="#10373E">PASS</text><text x="89.2" y="230.0" font-size="11.5" fill="#10373E">Mismatch</text><text x="175.6" y="230.0" font-size="11.5" fill="#10373E">LOG</text><text x="204.4" y="230.0" font-size="11.5" fill="#10373E">or</text><text x="226.0" y="230.0" font-size="11.5" fill="#10373E">BLOCK</text><text x="269.2" y="230.0" font-size="11.5" fill="#10373E">(per</text><text x="305.2" y="230.0" font-size="11.5" fill="#10373E">enforcement</text><text x="391.6" y="230.0" font-size="11.5" fill="#10373E">mode)</text><text x="10.0" y="262.0" font-size="11.5" fill="#10373E">Controller</text><text x="89.2" y="262.0" font-size="11.5" fill="#10373E">action</text><text x="139.6" y="262.0" font-size="11.5" fill="#10373E">proceeds</text></svg></div>

---

## 3. Service API

### CsrfService (AtomFramework\Services\CsrfService)

| Method | Description |
|--------|-------------|
| `generateToken(): string` | Get/create 64-char hex token (rotates after 1 hour) |
| `validateToken(string $token): bool` | Constant-time comparison against session token |
| `getTokenFromRequest(): ?string` | Extract token from `$_POST` or `X-CSRF-TOKEN` header |
| `isExempt(): bool` | Check if request is exempt (safe method, Bearer, API key) |
| `renderHiddenField(): string` | HTML `<input type="hidden" name="_csrf_token" ...>` |
| `getMetaTag(): string` | HTML `<meta name="csrf-token" content="...">` |
| `enforce(): bool` | Full enforcement — returns false to block |
| `getEnforcementMode(): string` | Returns `'log'`, `'enforce'`, or `'off'` |

---

## 4. Template Helpers

Three helper functions are available in both Symfony and standalone templates:

```php
<?php echo csrf_field() ?>
// Output: <input type="hidden" name="_csrf_token" value="abc123..." />

<?php echo csrf_token() ?>
// Output: abc123... (raw token string)

<?php echo csrf_meta() ?>
// Output: <meta name="csrf-token" content="abc123..." />
```

### Usage in HTML Forms

```php
<form method="post" action="<?php echo url_for(...) ?>">
    <?php echo csrf_field() ?>

    <!-- form fields -->

    <button type="submit">Save</button>
</form>
```

### Usage in Blade Templates

```blade
<form method="post" action="{{ url_for(...) }}">
    {!! csrf_field() !!}

    <!-- form fields -->

    <button type="submit">Save</button>
</form>
```

---

## 5. AJAX / JavaScript

### Automatic Injection (csrf.js)

Include `csrf.js` in the page layout. It reads the meta tag and auto-injects the token:

```html
<head>
    <?php echo csrf_meta() ?>
</head>
<body>
    <!-- ... -->
    <script src="/atom-framework/assets/js/csrf.js"></script>
</body>
```

`csrf.js` intercepts:
- `fetch()` — adds `X-CSRF-TOKEN` header on POST/PUT/DELETE/PATCH
- `jQuery.ajax()` — adds header via `ajaxSend` event
- `XMLHttpRequest` — adds header on `send()`

### Manual AJAX Token

If not using `csrf.js`, add the token manually:

```javascript
fetch('/some/endpoint', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
    },
    body: formData
});
```

---

## 6. API Exemptions

These request types are exempt from CSRF validation:

| Authentication | Header | Reason |
|---------------|--------|--------|
| Bearer token | `Authorization: Bearer <token>` | Stateless auth, not cookie-based |
| API key | `X-API-Key: <key>` | Stateless auth, not cookie-based |

Safe HTTP methods (GET, HEAD, OPTIONS) are always exempt.

---

## 7. Enforcement Modes

Configured via `ahg_settings` table, key `csrf_enforcement`:

| Mode | Behavior |
|------|----------|
| `'log'` (default) | Log violations to `error_log`, allow request |
| `'enforce'` | Log violations, return HTTP 403 |
| `'off'` | No CSRF checking |

### Setting the Mode

Via Admin > AHG Settings, or directly:

```sql
-- Check current mode
SELECT * FROM ahg_settings WHERE setting_key = 'csrf_enforcement';

-- Change to enforce mode (requires user permission per CLAUDE.md)
UPDATE ahg_settings SET setting_value = 'enforce' WHERE setting_key = 'csrf_enforcement';
```

---

## 8. Rollout Plan

### Phase 1: Log Mode (Current)
1. Deploy with `csrf_enforcement = 'log'`
2. Monitor `error_log` for CSRF violations
3. Identify forms missing `csrf_field()`

### Phase 2: Add Tokens to Forms
1. Add `<?php echo csrf_field() ?>` to all unlocked plugin POST forms
2. Add `csrf_meta()` to theme layout `<head>`
3. Include `csrf.js` for AJAX protection
4. Re-check logs — violations should drop to zero

### Phase 3: Enforce
1. Set `csrf_enforcement = 'enforce'`
2. Monitor for false positives (legitimate blocked requests)
3. Fix any remaining unprotected forms

### Phase 4: Locked Plugin Remediation
Forms in locked plugins cannot be modified. These will be addressed in future plugin releases. Locked plugins with POST forms:
- ahgThemeB5Plugin (login form, search)
- ahgSecurityClearancePlugin (classification forms)
- ahgBackupPlugin (backup/restore forms)

---

## 9. Controller Integration

### AhgController (automatic)

All controllers extending `AhgController` get CSRF protection automatically via `dispatch()` → `enforceCsrf()`.

To disable for a specific controller (e.g., webhook receiver):

```php
class webhookActions extends AhgController
{
    protected bool $csrfProtection = false;
}
```

### CsrfMiddleware (standalone mode)

In Heratio standalone mode, `CsrfMiddleware` provides framework-level enforcement before routing reaches the controller.

---

## 10. Testing

```bash
# Generate a token
php -r "
    session_start();
    require 'atom-framework/src/Services/CsrfService.php';
    echo AtomFramework\Services\CsrfService::generateToken();
"

# Verify csrf_field() output
php -r "
    session_start();
    require 'atom-framework/src/Services/CsrfService.php';
    echo AtomFramework\Services\CsrfService::renderHiddenField();
"

# Test token validation
php -r "
    session_start();
    require 'atom-framework/src/Services/CsrfService.php';
    \$token = AtomFramework\Services\CsrfService::generateToken();
    var_dump(AtomFramework\Services\CsrfService::validateToken(\$token)); // true
    var_dump(AtomFramework\Services\CsrfService::validateToken('bad'));    // false
"
```
