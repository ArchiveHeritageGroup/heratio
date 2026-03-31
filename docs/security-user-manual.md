# Security User Manual

## Overview

Heratio provides enterprise-grade security features aligned with OWASP Top 10, ISO 27001, Bell-LaPadula, and POPIA standards. This guide covers how to use and configure security features.

## Password Policy

### Password Requirements

Strong passwords are enforced by default. Passwords must meet:

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

### Password Expiry

Passwords expire after a configurable period (default: 90 days). When your password is about to expire:

- A warning notification appears on login when your password will expire within 14 days
- When your password expires, you will see a notification and may be redirected to the password change page
- Administrators can configure expiry settings at **Admin > AHG Settings > Security & Access Control**

### Password History

The system remembers your previous passwords (default: 5) and prevents reuse. When changing your password, you cannot use any of your last 5 passwords.

### Changing Your Password

1. Click your username in the top-right menu
2. Select **Change Password**
3. Enter your current password
4. Enter and confirm your new password
5. Click **Save**

## Account Lockout

To protect against brute force attacks, accounts are temporarily locked after repeated failed login attempts.

- **Threshold:** 5 failed attempts within 15 minutes
- **Duration:** 15-minute lockout
- After the lockout period, you can try logging in again
- Successful login clears the failed attempt counter

If you are locked out, wait 15 minutes and try again. Contact your administrator if the problem persists.

## Multi-Factor Authentication (2FA)

Heratio supports Time-based One-Time Password (TOTP) for two-factor authentication.

### Setting Up 2FA

1. Navigate to your user profile
2. Look for the **Two-Factor Authentication** section
3. Click **Enable 2FA**
4. Scan the QR code with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator)
5. Enter the 6-digit code from your app to verify
6. Save your recovery codes in a secure location

### Using 2FA

When 2FA is enabled, after entering your username and password you will be prompted for a 6-digit code from your authenticator app. Enter the current code to complete login.

### Email Fallback

If you don't have your authenticator app available, you can request a code via email. The email code is valid for 10 minutes.

## Security Classification (Bell-LaPadula)

Heratio implements mandatory access control using the Bell-LaPadula model.

### Classification Levels

Records can be classified at four levels (lowest to highest):

1. **PUBLIC** — Visible to all users
2. **CONFIDENTIAL** — Restricted to users with Confidential clearance or higher
3. **SECRET** — Restricted to users with Secret clearance or higher
4. **TOP SECRET** — Restricted to users with Top Secret clearance only

### How It Works

- **No Read-Up (Simple Security):** You can only view records at or below your clearance level
- **No Write-Down (Star Property):** You cannot edit or create records at a classification level lower than your clearance (prevents accidental disclosure)
- Administrators are exempt from the Star Property for operational flexibility

### Your Clearance Level

Your security clearance is assigned by an administrator. To check your clearance level, view your user profile or contact your administrator.

## Session Security

### Automatic Timeout

Sessions expire after 30 minutes of inactivity (configurable). When your session times out:

- You will be redirected to the login page
- Any unsaved work may be lost — save frequently

### Session Protection

- Session IDs are regenerated on login to prevent session fixation attacks
- Cookies are marked HttpOnly (inaccessible to JavaScript)
- CSRF tokens protect all form submissions

## Security Headers

All pages include security headers that protect against common web attacks:

- **HSTS** — Forces secure HTTPS connections
- **X-Frame-Options** — Prevents the site from being embedded in frames (clickjacking protection)
- **X-Content-Type-Options** — Prevents browser MIME sniffing
- **Permissions-Policy** — Restricts browser features
- **Referrer-Policy** — Controls information in the Referer header

## Audit Trail

All significant actions are logged for compliance:

- Login and logout events
- Failed login attempts
- Record creation, editing, and deletion
- Security classification changes
- Access to classified records

Administrators can review audit logs at **Admin > Audit Trail**.

## For Administrators

### Configuring Security Settings

Navigate to **Admin > AHG Settings > Security & Access Control** to configure:

- **Password Expiry** — Days before passwords expire (0 to disable)
- **Password History** — Number of previous passwords to remember
- **Expiry Warning** — Days before expiry to show warnings
- **Force Password Change** — Redirect users to change expired passwords
- **Account Lockout** — Enable/disable and configure thresholds
- **Session Timeout** — Idle timeout duration

### Recommended Cron Jobs

Set up these cron jobs for ongoing security maintenance:

```bash
# Breach notification check (hourly) — POPIA Section 22
0 * * * * cd /usr/share/nginx/archive && php symfony privacy:breach-check --email=dpo@example.com

# Login attempt cleanup (daily)
0 3 * * * cd /usr/share/nginx/archive && php bin/atom tools:cleanup-login-attempts

# Audit log retention (weekly)
0 4 * * 0 cd /usr/share/nginx/archive && php bin/atom tools:audit-retention
```

### Compliance Standards

| Standard | Coverage |
|----------|----------|
| OWASP Top 10 (2021) | All 10 categories addressed |
| ISO 27001:2022 | A.5-A.8, A.9 access control, A.12 operations |
| POPIA (South Africa) | Sections 19, 22, Part A |
| Bell-LaPadula | Simple Security + Star Property |
| NARSSA | Audit trail requirements |
