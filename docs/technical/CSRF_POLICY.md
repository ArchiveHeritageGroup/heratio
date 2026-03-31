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

```
Browser Form Submit
    │
    ├─ POST body: _csrf_token=<token>
    │  OR
    ├─ Header: X-CSRF-TOKEN: <token>  (AJAX)
    │
    ▼
CsrfService::enforce()
    ├─ Is method safe (GET/HEAD/OPTIONS)?  → PASS
    ├─ Has Bearer token or API key?        → PASS (exempt)
    ├─ Extract token from POST or header
    ├─ hash_equals() against session token
    │   ├─ Match     → PASS
    │   └─ Mismatch  → LOG or BLOCK (per enforcement mode)
    ▼
Controller action proceeds
```

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
