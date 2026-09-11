# Outlook mail on this host - read, draft, send, and the standing rules

Summary: all mail for Dr Johan Pieterse and AHG is read and sent through Outlook
Microsoft Graph, using delegated per-user OAuth held by the workbench API. The
default tool is the outlook-km MCP server; edit-first drafts use a small set of
installed scripts. Two hard rules apply: Gmail is off-limits, and email is only
ever sent to Johan himself - never directly to a third party. This document
carries no credentials, keys, tokens, addresses of third parties, or mailbox
contents.

## Where mail lives

Mail does not live in Heratio. It lives in the workbench repo at
`/usr/share/nginx/workbench`, principally `api/src/services/outlookGraph.ts`
(`/me/messages`, `/me/mailFolders/{f}/messages`, `$search`, attachments,
`Mail.Send`) with supporting services `outlookRetrieval`, `emailIngestWatcher`,
`emailRuleWatcher`, `emailTriage` and `outlookExtractToKm`, and routes
`outlook.ts` and `email.ts`. Authentication is delegated OAuth per user with a
stored refresh token, so no tenant-wide admin consent is involved.

A common wrong turn: `packages/ahg-sharepoint` in Heratio also has a Graph
client, but it is SharePoint and Drive federation only (client-credentials,
`.default` scope, `/search/query`, no mail scopes, empty tenant table). It is
not a mail surface.

## Rule 1 - Outlook only, never Gmail

Use Outlook / Microsoft Graph for all mail. Do not authorise the claude.ai
Gmail connector, and do not use the linked Gmail IMAP mailbox even though a row
for it exists alongside the Outlook account. Gmail is off-limits for this work.

## Rule 2 - never send to a third party, only to Johan

Every email send goes to Johan's own address and no one else. When Johan asks
for a reply, a message, or "send it", the send target is always himself; he
reviews and forwards to the actual recipient. This holds even when he has
approved the content and named the recipient. Never place a third party in to,
cc or bcc. Creating a draft addressed to the recipient is fine, because a draft
is never sent by the tool - only Johan can press send. This is stricter than
any "send only on explicit go" phrasing and overrides it.

## The tools, and which to use

Default to the outlook-km MCP server for reading, searching and sending:
`outlook_search`, `outlook_get_message`, `outlook_list_attachments`,
`outlook_list_inboxes`, `outlook_whoami`, and `outlook_send`. It resolves the
mailbox from the OS user, needs no elevation, and `outlook_send` goes via Graph.
Note that its search is oriented to received mail, so it may not return items
from Sent Items.

For edit-first drafts - where the deliverable is an editable item left in the
Drafts folder rather than a sent message - a small set of read-only-to-mailbox
scripts is installed under `/usr/local/share/ahg` and run through a narrow,
password-free grant limited to those scripts. They create a draft via Graph
`createReply` (pre-addressed and threaded), set an HTML body, attach files, and
never send. A companion reader prints a thread's full bodies. These exist
because the MCP can send but cannot create a Drafts item.

The split in one line: MCP for read, search and send-to-self; the draft scripts
only when the deliverable is an editable Drafts item.

## House expectations for mail Johan will send on

Body font Aptos (Body) 12pt, 1.5 line spacing, with a sans-serif fallback.
Plain hyphens, never long dashes. Johan's name carries the doctoral title,
"Dr Johan Pieterse". Attached documents follow the AHG house docx standard and
render on the branded letterhead reference template.
