# Outbound mail: two paths, and how to pick the right one

**Summary.** Mail leaves this host by two entirely separate routes, and the right one is chosen by asking who is sending rather than which project you are working in. Correspondence sent on Johan's behalf - documents, decks, anything a person will read or forward - goes through the Workbench's Microsoft 365 Graph sender and arrives as johan@theahg.co.za. The Heratio application's own automated mail is a different path that still relays through a personal Gmail account. Confusing the two is easy, because both are reachable from the same machine and the fallback between them is silent.

## The rule

Anything an agent or an operator composes for a human recipient uses the Graph path. Application-generated mail from Heratio itself continues to use the relay, because that is the Laravel configuration and not something a correspondent chooses per message.

The distinction matters because `theahg.co.za` publishes an SPF record that ends in `-all` and permits only Microsoft 365 to send for the domain. Nothing relayed through the Gmail account can send as an `@theahg.co.za` address; it will arrive from a personal Gmail address instead. For a partner-facing document that reads as a personal note rather than as work from the practice, and for anything genuinely addressed as the domain it fails authentication outright.

## Path 1: Graph, for correspondence

Implemented in the Workbench API as `sendOutboundMail` (`api/src/services/outboundMail.ts`, compiled to `api/dist/services/outboundMail.js`), which calls `sendMailViaGraph` in `outlookGraph.ts` against the Graph `sendMail` endpoint with base64-encoded attachments. It reuses the existing Outlook AAD application with delegated `Mail.Send` permission, and the sending identity comes from `OUTLOOK_SEND_FROM_UPN` in the API's environment file. Established 31 July 2026, alongside the equivalent SMTP OAuth2 change for ERPNext.

Invoke it from a short ES module that imports the compiled service and runs under the API's environment:

```
node --env-file=.env <script>.mjs      # run from the workbench api directory
```

The function takes `to`, `subject`, `text` or `html`, and an optional `attachments` array of `{ filename, path }`. It returns which route actually carried the message: `graph`, `sendmail`, or `failed`.

**Always read that return value.** When the OAuth token is missing or stale, the function does not error. It falls back to the host MTA and returns `sendmail`, which means the message went out over the Gmail relay from the personal address with no warning to the caller. A send is only correct if the result says `graph`.

## Path 2: The relay, for application mail

Heratio's Laravel configuration uses the `sendmail` mailer pointed at the host binary. That binary is a symlink to msmtp, which relays to Gmail's submission service over STARTTLS. There is no local MTA and no local queue, so nothing accumulates on disk waiting to go out.

A confusing detail worth knowing during diagnosis: a naive `systemctl is-active` sweep appears to report postfix or exim as running, because the string "inactive" contains "active". Neither is installed. The msmtp symlink is the real path.

Every send is logged with host, sender, recipients and exit code, which is the first place to look when a message does not arrive. A one-line test message piped to the sendmail binary confirms the path end to end.

Two known weaknesses on this route, neither yet addressed. The sending identity is a personal Gmail account rather than a domain address, which is a branding and deliverability question. And Gmail's daily recipient cap applies, so any bulk path - donor reminders, exhibition notifications - can quietly hit the limit and start being refused; the msmtp log is where that shows up.

## Unrelated to both

The email capture surface in the records management module is inbound. It ingests messages as records and has nothing to do with either sending path, despite the superficially similar name.

## Credentials

No credential values belong in this document or in KM. The Gmail application password lives only in the msmtp configuration file on the host, and the Azure application secret lives only in the identity platform and the consuming application's configuration. Tokens refresh themselves; the application secret does not, and its expiry is the thing that will eventually break the ERPNext side without warning.

## Related

`reference_ahg_m365_email` in the Workbench operator memory carries the tenant and application detail. The php-fpm read-only filesystem note explains why artisan should never be run as root from these directories, which is a common cause of mail-adjacent failures in the Laravel path.
