# Password & Login Security - User Guide

**Framework:** Heratio >= 2.8.0
**Services:** PasswordPolicyService, LoginSecurityService, AuthMiddleware

---

## Introduction

Heratio includes built-in password and login security features that protect user accounts from unauthorized access. These features work automatically once the database tables are installed, and can be configured via **Admin > AHG Settings**.

---

## Password Expiry

User passwords expire after a configurable number of days. When a password is nearing expiry or has expired, the system notifies the user automatically.

### How It Works

1. Each time a user changes their password, the change is recorded in the password history
2. On each login, the system checks the age of the user's last password change
3. If nearing expiry, a warning banner appears (e.g., "Your password will expire in 7 days")
4. If expired, an error banner appears prompting immediate password change
5. If **Force Password Change** is enabled, the user is redirected to the password change page and cannot access other pages until the password is updated

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `password_expiry_days` | 90 | Number of days before a password expires. Set to 0 to disable. |
| `security_password_expiry_notify` | true | Whether to show expiry warning banners on login |
| `security_password_expiry_warn_days` | 14 | Show warning when password expires within this many days |
| `security_force_password_change` | false | Force redirect to password change page when expired |

---

## Password Reuse Prevention

The system prevents users from reusing recent passwords. When a user attempts to change their password to one they have used before, the change is rejected with a clear error message.

### How It Works

1. A configurable number of previous password hashes are stored per user
2. When the user submits a new password, it is checked against the stored history
3. If the new password matches any recent password, the form displays: _"This password has been used recently. Please choose a different password."_
4. The user must choose a different password to proceed

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `password_history_count` | 5 | Number of previous passwords to remember. Set to 0 to disable. |

---

## Login Lockout (Brute-Force Protection)

The system automatically locks out login attempts after too many consecutive failures, protecting against brute-force password guessing attacks.

### How It Works

1. Each login attempt (successful or failed) is recorded with the email address and IP
2. After 5 failed attempts within 15 minutes, the account is temporarily locked
3. The login form displays: _"Too many failed login attempts. Please try again in X minute(s)."_
4. After the lockout period (15 minutes), the user can try again
5. A successful login clears all previous failure records for that email
6. Old attempt records (older than 24 hours) are cleaned up automatically

### Policy

| Parameter | Value |
|-----------|-------|
| Maximum failed attempts | 5 |
| Lockout window | 15 minutes |
| Lockout duration | 15 minutes |
| Record retention | 24 hours |

---

## Strong Password Requirements

When **Require Strong Passwords** is enabled in AtoM settings, the password change form enforces minimum strength requirements:

- Minimum 6 characters
- Must contain lowercase letters
- Must contain uppercase letters
- Must contain numbers
- Must contain punctuation
- Must not be the same as the username

A visual password strength meter is displayed on the password change page.

To enable: set `require_strong_passwords` to `true` in `apps/qubit/config/app.yml`.

---

## Settings Configuration

Password security settings are stored in the `ahg_settings` table. Administrators can configure them via **Admin > AHG Settings > Security** (if the settings section is available), or by inserting values directly:

```sql
-- Example: Set password expiry to 60 days
INSERT INTO ahg_settings (setting_key, setting_value, setting_group)
VALUES ('password_expiry_days', '60', 'security')
ON DUPLICATE KEY UPDATE setting_value = '60';

-- Example: Enable forced password change on expiry
INSERT INTO ahg_settings (setting_key, setting_value, setting_group)
VALUES ('security_force_password_change', 'true', 'security')
ON DUPLICATE KEY UPDATE setting_value = 'true';

-- Example: Remember last 10 passwords
INSERT INTO ahg_settings (setting_key, setting_value, setting_group)
VALUES ('password_history_count', '10', 'security')
ON DUPLICATE KEY UPDATE setting_value = '10';
```

---

## Database Tables

The security features require three tables (created during framework installation):

| Table | Purpose |
|-------|---------|
| `password_history` | Stores hashed previous passwords per user |
| `login_attempt` | Records login attempts (email, IP, success/failure, timestamp) |
| `user_totp_secret` | Stores TOTP secrets for two-factor authentication (future) |

These tables are created automatically by the framework installer. If they are missing, the security features gracefully degrade - login and password changes work normally without enforcement.

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Password expiry warnings not showing | Check `security_password_expiry_notify` is not set to `false` in ahg_settings |
| User locked out but needs access | Wait 15 minutes, or manually clear: `DELETE FROM login_attempt WHERE identifier = 'user@email.com'` |
| Password reuse not being checked | Verify `password_history` table exists and `password_history_count` > 0 |
| No password history recorded | Ensure the user changed password AFTER the integration was installed |
| "Force password change" not redirecting | Check `security_force_password_change` is set to `true` |

---

*The Archive and Heritage Group (Pty) Ltd*
