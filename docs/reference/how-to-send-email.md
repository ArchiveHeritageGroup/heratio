# How to send email from this box (heratio-dev)

**Summary.** You do NOT need the claude.ai Gmail MCP connector to send email from
this server. This host has a working local mail transport: `/usr/sbin/sendmail`
is msmtp, which relays through Gmail. Send directly with `sendmail -t` and verify
in `/var/log/msmtp.log`. Do not block a task waiting for a Gmail connector
authorisation - just send.

## The transport (already configured)

- `/usr/sbin/sendmail` -> `/usr/bin/msmtp` (msmtp in sendmail-compatible mode).
- msmtp relays to Gmail as the configured account (this session used
  `pieterse.johan3@gmail.com`).
- Delivery log: `/var/log/msmtp.log`. A successful send shows
  `recipients=<address> ... smtpstatus=250 ... exitcode=EX_OK`.
- Note: unrelated `recipients=www-data ... smtpstatus=553` lines in that log come
  from a separate failing cron job - ignore them; look for your recipient address
  and `smtpstatus=250`.

## Plain-text email

Write a message with the headers, then pipe it to sendmail. `-t` reads the
recipients from the To/Cc/Bcc headers.

```bash
cat > /tmp/mail.txt <<'EOF'
From: Dr Johan Pieterse <pieterse.johan3@gmail.com>
To: johan@theahg.co.za
Subject: Your subject here

Body text goes here.
EOF
/usr/sbin/sendmail -t < /tmp/mail.txt
# verify:
tail -n 1 /var/log/msmtp.log        # expect: recipients=johan@theahg.co.za ... smtpstatus=250
```

## Email with an attachment (e.g. an .xlsx)

Build a MIME message in Python and pipe it to sendmail. This is the reliable way
to attach a spreadsheet, docx or pdf.

```python
import subprocess, os
from email.message import EmailMessage

path = "/path/to/file.xlsx"
m = EmailMessage()
m["From"] = "Dr Johan Pieterse <pieterse.johan3@gmail.com>"
m["To"] = "johan@theahg.co.za"
m["Subject"] = "Directory - v1"
m.set_content("Body text. The file is attached.")

with open(path, "rb") as f:
    m.add_attachment(
        f.read(),
        maintype="application",
        subtype="vnd.openxmlformats-officedocument.spreadsheetml.sheet",  # .xlsx
        filename=os.path.basename(path),
    )

subprocess.run(["/usr/sbin/sendmail", "-t"], input=m.as_bytes(), check=True)
```

Common attachment subtypes (maintype `application`):
- `.xlsx` -> `vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- `.docx` -> `vnd.openxmlformats-officedocument.wordprocessingml.document`
- `.pdf`  -> `pdf` (use `maintype="application", subtype="pdf"`)
- `.csv`  -> use `maintype="text", subtype="csv"`

## Verifying delivery

Always confirm the send rather than assuming it worked:

```bash
tail -n 3 /var/log/msmtp.log
```

Look for your recipient with `smtpstatus=250` and `exitcode=EX_OK`. Anything
else (timeout, 5xx) means it did not send - report the log line, do not claim
success.

## Sending AS johan@theahg.co.za (Microsoft 365 via Workbench Graph)

The sendmail path above goes out as the Gmail account (`pieterse.johan3@gmail.com`).
To send as the real AHG address, **johan@theahg.co.za**, reuse the Workbench
Outlook integration: it holds johan's delegated mailbox token and sends via
Microsoft 365 Graph (SPF/DMARC-clean), falling back to sendmail only if Graph
fails. No new credentials, no config change - you call Workbench's own
`sendOutboundMail()` with a one-off runner. Use this for anything that should
look official / external; use the plain sendmail path for internal notes.

```bash
cd /usr/share/nginx/workbench/api

# ---- edit these four ----
TO="johan@theahg.co.za"
SUBJECT="Directory - v1"
BODY="Hi Johan,

The v1 directory is attached.

Regards"
ATTACH="/absolute/path/to/file.xlsx"     # leave as "" for no attachment
# -------------------------

cat > /tmp/send-as-johan.mts <<'TS'
import { sendOutboundMail } from '/usr/share/nginx/workbench/api/src/services/outboundMail.js';

const to = process.env.WB_TO!;
const subject = process.env.WB_SUBJECT!;
const text = process.env.WB_BODY ?? '';
const attach = process.env.WB_ATTACH ?? '';

const mail: any = { to, subject, text };
if (attach) mail.attachments = [{ filename: attach.split('/').pop(), path: attach }];

const via = await sendOutboundMail(mail);
console.log('sent via:', via);
process.exit(via === 'graph' ? 0 : via === 'sendmail' ? 2 : 1);
TS

WB_TO="$TO" WB_SUBJECT="$SUBJECT" WB_BODY="$BODY" WB_ATTACH="$ATTACH" \
  node --env-file=.env --import tsx /tmp/send-as-johan.mts
```

Verified working 2026-08-10 from this host: a test send returned `sent via: graph`
and arrived from johan@theahg.co.za.

Read the result:
- `sent via: graph` (exit 0) - sent as johan@theahg.co.za through Microsoft 365. This is the goal.
- `sent via: sendmail` (exit 2) - Graph failed; it fell back and sent as the Gmail account. Usually johan's Outlook token needs re-auth (sign in again via the Workbench Outlook integration), then re-run.
- `sent via: failed` (exit 1) - neither path worked; check `journalctl -u workbench-api`.

Notes:
- Run from `/usr/share/nginx/workbench/api` so `.env` and `node_modules/tsx` resolve. Use the `.mts` extension and the absolute import path: a `.ts` in `/tmp` is treated as CommonJS and top-level await fails; `.mts` forces ESM. (`--loader tsx/esm` is deprecated and errors on current tsx - use `--import tsx`.)
- This is a one-off runner in `/tmp`; it does not modify or commit anything in the Workbench repo (respects the staging-only rule).
- For HTML, set `mail.html = "<p>...</p>"` instead of `text`.
- Attachments are `{ filename, path }` read from disk (works for .xlsx, .docx, .pdf).

## Do not

- Do not wait on `/mcp` / the claude.ai Gmail connector to send from this box -
  it is not required and is not the path in use here.
- Do not put credentials or the msmtp password in any committed file or in KM.
- Do not send from the app as `root` in a way that writes root-owned files under
  `storage/` (unrelated to sending, but the usual www-data ownership rule still
  applies to any Laravel mail queue artefacts).
