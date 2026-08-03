> Heratio Help Center article. Category: Public Access.

# Request to Publish

Lets a visitor ask an archivist to publish (or release for use) a draft or
restricted archival description, then lets a curator approve, reject, or edit
the request and notify the submitter. The submitter tracks the outcome through
an anonymous receipt link, with no account required.

---

## Overview

The Request to Publish module is the editorial-approval workflow that sits
between "a member of the public wants this item published or released" and
"a curator decides." It is built around a single lightweight table,
`ahg_publish_request`, and an opaque receipt token so that anyone holding the
receipt URL can check status without logging in.

The package ships two independent flows that run side by side:

1. **Token-anchored flow (the current flow, Heratio #745).** Anonymous public
   submission, an emailed receipt link, a curator inbox, and a per-request
   review panel. This is the flow most installations use and the one this
   guide focuses on. Data lives in `ahg_publish_request`.

2. **Legacy port flow (retained for in-flight requests).** An older
   authenticated browse-and-edit surface backed by the `request_to_publish`
   and `request_to_publish_i18n` tables, with three numeric status IDs
   (Pending, Approved, Rejected). It is kept so requests created before the
   token flow shipped can still be worked. New submissions should use the
   token flow.

The two flows do not share data and are intentionally decoupled.

---

## Key features

- **Anonymous submission.** No login is needed to ask for an item to be
  published. The submitter supplies an email address and an optional message.
- **Opaque receipt token.** Each submission gets a 40-character hex token. The
  receipt page at `/publish-request/receipt/{token}` is the submitter's only
  status surface and requires no account.
- **Curator inbox.** Admin users see every request, filterable by status, at
  `/admin/publish-requests`.
- **Decision panel.** A curator opens one request, reads the submission, and
  records a decision with optional curator notes.
- **Four statuses.** Pending, Approved, Rejected, and Edited. The status
  vocabulary is stored in the Dropdown Manager (taxonomy
  `publish_request_status`), so labels can be re-styled without code changes.
- **Best-effort email.** A submission confirmation goes to the submitter, and a
  decision email goes out when a curator acts. Email failures never block the
  request itself.
- **Item linkage.** A request can be tied to a specific archival description
  (an information object). When it is, the inbox, panel, and receipt all show
  a link to that item.

---

## Request lifecycle

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 510 292" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="509" height="291" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="100.0" y1="26.0" x2="100.0" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="66.0" x2="164.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="66.0" x2="168.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="66.0" x2="172.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="66.0" x2="175.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="66.0" x2="179.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="66.0" x2="182.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="66.0" x2="186.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="66.0" x2="190.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="66.0" x2="193.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="66.0" x2="197.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="66.0" x2="200.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="66.0" x2="204.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="66.0" x2="208.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="66.0" x2="211.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="66.0" x2="215.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="66.0" x2="218.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="66.0" x2="222.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="66.0" x2="226.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="66.0" x2="229.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="66.0" x2="233.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="66.0" x2="236.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="66.0" x2="240.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="66.0" x2="244.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="66.0" x2="247.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="66.0" x2="251.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="66.0" x2="254.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="66.0" x2="258.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="66.0" x2="262.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="74.0" x2="100.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="122.0" x2="100.0" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="146.0" x2="38.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="146.0" x2="46.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="146.0" x2="53.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="146.0" x2="60.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="146.0" x2="67.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="146.0" x2="74.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="146.0" x2="82.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="146.0" x2="89.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="146.0" x2="96.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="146.0" x2="100.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="146.0" x2="103.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="138.0" x2="100.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="146.0" x2="110.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="146.0" x2="118.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="146.0" x2="125.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="146.0" x2="132.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="146.0" x2="139.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="146.0" x2="146.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="146.0" x2="154.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="146.0" x2="161.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="146.0" x2="168.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="146.0" x2="175.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="146.0" x2="182.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="146.0" x2="190.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="146.0" x2="197.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="146.0" x2="204.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="146.0" x2="208.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="146.0" x2="211.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="146.0" x2="218.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="146.0" x2="226.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="146.0" x2="233.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="146.0" x2="240.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="146.0" x2="247.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="146.0" x2="254.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="146.0" x2="262.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="146.0" x2="269.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="146.0" x2="276.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="146.0" x2="283.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="146.0" x2="290.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="146.0" x2="298.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="146.0" x2="305.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="146.0" x2="312.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="146.0" x2="319.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="146.0" x2="323.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="186.0" x2="35.2" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="186.0" x2="100.0" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="186.0" x2="208.0" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="210.0" x2="38.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="202.0" x2="35.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="210.0" x2="46.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="210.0" x2="53.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="210.0" x2="60.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="210.0" x2="67.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="210.0" x2="74.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="210.0" x2="82.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="210.0" x2="89.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="210.0" x2="96.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="210.0" x2="100.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="210.0" x2="103.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="202.0" x2="100.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="210.0" x2="110.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="210.0" x2="118.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="210.0" x2="125.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="210.0" x2="132.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="210.0" x2="139.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="210.0" x2="146.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="210.0" x2="154.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="210.0" x2="161.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="210.0" x2="168.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="210.0" x2="175.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="210.0" x2="182.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="210.0" x2="190.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="210.0" x2="197.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="210.0" x2="204.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="210.0" x2="208.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="210.0" x2="211.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="202.0" x2="208.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="210.0" x2="218.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="210.0" x2="226.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="210.0" x2="233.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="210.0" x2="240.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="210.0" x2="247.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="210.0" x2="254.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="210.0" x2="262.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="210.0" x2="269.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="210.0" x2="276.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="210.0" x2="283.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="234.0" x2="100.0" y2="250.0" stroke="#10373E" stroke-width="1.3"/><path d="M96.0 253.0 L100.0 260.0 L104.0 253.0 Z" fill="#10373E"/><text x="24.4" y="22.0" font-size="9.5" fill="#10373E">Visitor</text><text x="82.0" y="22.0" font-size="9.5" fill="#10373E">submits</text><text x="139.6" y="22.0" font-size="9.5" fill="#10373E">request</text><text x="96.4" y="54.0" font-size="9.5" fill="#10373E">v</text><text x="31.6" y="70.0" font-size="9.5" fill="#10373E">status</text><text x="82.0" y="70.0" font-size="9.5" fill="#10373E">=</text><text x="96.4" y="70.0" font-size="9.5" fill="#10373E">pending</text><text x="262.0" y="70.0" font-size="9.5" fill="#10373E">►</text><text x="276.4" y="70.0" font-size="9.5" fill="#10373E">confirmation</text><text x="370.0" y="70.0" font-size="9.5" fill="#10373E">email</text><text x="413.2" y="70.0" font-size="9.5" fill="#10373E">to</text><text x="434.8" y="70.0" font-size="9.5" fill="#10373E">submitter</text><text x="283.6" y="86.0" font-size="9.5" fill="#10373E">(carries</text><text x="348.4" y="86.0" font-size="9.5" fill="#10373E">the</text><text x="377.2" y="86.0" font-size="9.5" fill="#10373E">receipt</text><text x="434.8" y="86.0" font-size="9.5" fill="#10373E">link)</text><text x="96.4" y="102.0" font-size="9.5" fill="#10373E">v</text><text x="31.6" y="118.0" font-size="9.5" fill="#10373E">Curator</text><text x="89.2" y="118.0" font-size="9.5" fill="#10373E">opens</text><text x="132.4" y="118.0" font-size="9.5" fill="#10373E">the</text><text x="161.2" y="118.0" font-size="9.5" fill="#10373E">request</text><text x="218.8" y="118.0" font-size="9.5" fill="#10373E">in</text><text x="240.4" y="118.0" font-size="9.5" fill="#10373E">the</text><text x="269.2" y="118.0" font-size="9.5" fill="#10373E">inbox</text><text x="31.6" y="166.0" font-size="9.5" fill="#10373E">v</text><text x="96.4" y="166.0" font-size="9.5" fill="#10373E">v</text><text x="204.4" y="166.0" font-size="9.5" fill="#10373E">v</text><text x="319.6" y="166.0" font-size="9.5" fill="#10373E">v</text><text x="17.2" y="182.0" font-size="9.5" fill="#10373E">approved</text><text x="89.2" y="182.0" font-size="9.5" fill="#10373E">rejected</text><text x="190.0" y="182.0" font-size="9.5" fill="#10373E">edited</text><text x="290.8" y="182.0" font-size="9.5" fill="#10373E">(left</text><text x="334.0" y="182.0" font-size="9.5" fill="#10373E">pending)</text><text x="283.6" y="214.0" font-size="9.5" fill="#10373E">►</text><text x="298.0" y="214.0" font-size="9.5" fill="#10373E">decision</text><text x="362.8" y="214.0" font-size="9.5" fill="#10373E">email</text><text x="406.0" y="214.0" font-size="9.5" fill="#10373E">to</text><text x="427.6" y="214.0" font-size="9.5" fill="#10373E">submitter</text><text x="290.8" y="230.0" font-size="9.5" fill="#10373E">(carries</text><text x="355.6" y="230.0" font-size="9.5" fill="#10373E">curator</text><text x="413.2" y="230.0" font-size="9.5" fill="#10373E">notes)</text><text x="31.6" y="278.0" font-size="9.5" fill="#10373E">Submitter</text><text x="103.6" y="278.0" font-size="9.5" fill="#10373E">re-opens</text><text x="168.4" y="278.0" font-size="9.5" fill="#10373E">the</text><text x="197.2" y="278.0" font-size="9.5" fill="#10373E">receipt</text><text x="254.8" y="278.0" font-size="9.5" fill="#10373E">link</text><text x="290.8" y="278.0" font-size="9.5" fill="#10373E">and</text><text x="319.6" y="278.0" font-size="9.5" fill="#10373E">sees</text><text x="355.6" y="278.0" font-size="9.5" fill="#10373E">the</text><text x="384.4" y="278.0" font-size="9.5" fill="#10373E">outcome</text></svg></div>

- **Pending** is the entry state set on submission.
- **Approved** and **Rejected** are terminal curator decisions.
- **Edited** means the curator rewrote the submission message text (for
  example, to correct or clarify the request) and saved that revised text.
- A curator can also set a request back to Pending.

When a decision is recorded, the module stamps `decided_at` and
`decided_by_user_id`, stores any `curator_notes`, and (for Edited) overwrites
the stored `message_text`.

---

## How to use

### For a visitor: submit a request

1. From an archival description that offers a publish or release option, fill
   in the request form.
2. Provide your **email address** (required) and, optionally, your **name** and
   a **message** explaining what you want published or released.
3. Submit. The request is recorded with status **Pending**.
4. You are taken to your **receipt page** at
   `/publish-request/receipt/{token}`. A confirmation email with the same link
   is sent to the address you supplied.
5. **Bookmark the receipt link.** It is the only way to check status without
   contacting an archivist.

The submission endpoint is `POST /publish-request`. It accepts an optional
`information_object_id` (the item the request is about), `submitter_email`
(required, valid email, up to 190 characters), an optional `submitter_name`
(up to 190 characters), and an optional `message_text` (up to 10,000
characters). Plain form submits redirect to the receipt page; an AJAX submit
gets a JSON response with the `token` and `receipt_url`.

### For a visitor: check status

1. Open your receipt link, `/publish-request/receipt/{token}`.
2. The page shows the current **status badge**, when you submitted, the linked
   archival item (if any), your message, and any **curator notes** once a
   decision is recorded.
3. The token must be a 40-character hex string; any other shape returns a
   not-found page, which deters URL probing.

### For a curator: review the inbox

1. Go to **Admin** and open the **Publish Requests** inbox at
   `/admin/publish-requests`.
2. Use the status tabs to filter: **All**, **Pending**, **Approved**,
   **Rejected**, **Edited**. Each tab shows a count badge.
3. The table lists the status, the linked archival item, the submitter (name
   and email), the submitted timestamp, the decided timestamp, and a review
   action. The inbox shows up to 200 rows, newest first.
4. Click the review (eye) action on a row to open that request.

### For a curator: record a decision

1. From the inbox, open a request. The review panel at
   `/admin/publish-requests/{id}/edit` shows the full submission: submitter,
   submitted time, linked item, message, and the receipt token (with a link to
   open the submitter's receipt view).
2. In the **Decision** form, pick a **Status** (Pending, Approved, Rejected, or
   Edited).
3. Optionally add **Curator notes**. These are visible to the submitter on the
   receipt page.
4. If you choose **Edited**, put the corrected text in the **Edited message**
   field. It is only saved when the status is Edited.
5. Click **Record decision**. The request is stamped with the decision time and
   your user ID, and a best-effort decision email is sent to the submitter.

The decision endpoint is `POST /admin/publish-requests/{id}/decision`. It
validates `status` (one of pending, approved, rejected, edited),
`curator_notes` (optional, up to 10,000 characters), and `message_text`
(optional, up to 10,000 characters, used only for Edited).

### Legacy port flow (in-flight requests only)

Requests created before the token flow are worked from a separate authenticated
surface:

- `GET /requesttopublish/browse` and `GET /admin/request-publish` list legacy
  requests, with status counts for All, In Review, Approved, and Rejected, and
  sorting by name or institution. Non-admin users see only their own rows.
- `GET /admin/request-publish/{id}/edit` opens one legacy request.
- `POST /admin/request-publish/{id}/update` writes the new status (Approved,
  Pending, or Rejected) and admin notes. Approving or rejecting stamps the
  completion time; setting it back to Pending clears it.

Legacy statuses are numeric (Approved, In Review/Pending, Rejected) and carry
their own Bootstrap badge colours (green, yellow, red).

---

## Routes

### Token-anchored flow (current)

| Method | URI | Action | Access |
|---|---|---|---|
| POST | `/publish-request` | `PublishRequestController@submit` | Public (CSRF-exempt) |
| GET | `/publish-request/receipt/{token}` | `PublishRequestController@receipt` | Public (token only) |
| GET | `/admin/publish-requests` | `PublishRequestController@inbox` | Admin |
| GET | `/admin/publish-requests/{id}/edit` | `PublishRequestController@edit` | Admin |
| POST | `/admin/publish-requests/{id}/decision` | `PublishRequestController@decision` | Admin |

The `{token}` segment is constrained to 40 hex characters; `{id}` must be
numeric.

### Legacy port flow

| Method | URI | Action | Access |
|---|---|---|---|
| GET | `/requesttopublish/browse` | `RequestPublishController@browse` | Authenticated |
| GET | `/admin/request-publish` | `RequestPublishController@browse` | Admin |
| GET | `/admin/request-publish/{id}/edit` | `RequestPublishController@edit` | Admin |
| POST | `/admin/request-publish/{id}/update` | `RequestPublishController@update` | Admin |
| POST | `/admin/request-publish/{id}/delete` | `RequestPublishController@destroy` | Admin |
| GET/POST | `/admin/request-publish/{id}/edit-request` | `RequestPublishController@editRequest` | Admin |
| POST | `/request-publish/submit/{slug}` | `RequestPublishController@submit` | Authenticated |

---

## Data model

### `ahg_publish_request` (current flow)

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT, auto | Primary key |
| `information_object_id` | BIGINT, nullable | The archival item the request is about |
| `submitter_email` | VARCHAR(190) | Required |
| `submitter_name` | VARCHAR(190), nullable | |
| `message_text` | TEXT, nullable | The submitter's request; overwritten on an Edited decision |
| `status` | VARCHAR(40) | Defaults to `pending`; see the dropdown below |
| `token` | CHAR(40) | Unique opaque receipt token |
| `created_at` | DATETIME | Submission time |
| `decided_at` | DATETIME, nullable | Set when a curator acts |
| `decided_by_user_id` | BIGINT, nullable | The deciding curator |
| `curator_notes` | TEXT, nullable | Shown to the submitter on the receipt |

`status` is a VARCHAR, never a database ENUM, in line with the project's
Dropdown Manager rule.

### `request_to_publish` and `request_to_publish_i18n` (legacy)

The legacy port stores translatable submitter fields (name, surname, phone,
email, institution, motivation, planned use, needed-by date, admin notes) on
the i18n table, with a numeric `status_id` (default Pending) and an
`object_id` link to the archival description.

---

## Configuration

### Status dropdown

The current flow's statuses live in the Dropdown Manager under taxonomy
`publish_request_status` (Publish Request Status). The four seeded values are:

| Code | Label | Sort |
|---|---|---|
| `pending` | Pending | 10 |
| `approved` | Approved | 20 |
| `rejected` | Rejected | 30 |
| `edited` | Edited | 40 |

These are seeded automatically on first boot via `INSERT IGNORE`, so re-runs
never overwrite labels you have edited in the Dropdown Manager
(`/admin/dropdowns`). If no dropdown rows exist, the review panel falls back to
the four built-in labels above.

### Schema install and auto-seed

The package service provider installs `ahg_publish_request` and seeds the
status dropdown on first boot, idempotently (`CREATE TABLE IF NOT EXISTS` plus
`INSERT IGNORE`). If the table is missing, the submit endpoint returns a 503
and the inbox shows a "table not configured" notice rather than erroring.

### Email

Confirmation and decision emails use the application's configured mail
transport. Sending is best-effort: a failure is logged but never blocks the
submission or the decision. There is no module-specific mail configuration to
set beyond the application's standard mail settings.

### CSRF

The public `POST /publish-request` endpoint is CSRF-exempt (registered in the
application's CSRF exception list as `publish-request`) so anonymous browsers
can submit. The admin decision endpoint uses the standard CSRF token.

---

## Troubleshooting

| Symptom | Likely cause | Resolution |
|---|---|---|
| Submit returns a 503 with a "table missing" message | `ahg_publish_request` not installed | Re-trigger the service-provider boot, or run `database/install_publish_request.sql` |
| Inbox shows "table not configured" | Same as above | As above |
| Receipt link returns not-found | Token is not 40 hex characters, or the request does not exist | Use the exact link from the confirmation email |
| Submitter did not get an email | Mail transport failed (logged) | Share the receipt link directly; check the application mail configuration |
| Status labels look wrong | Dropdown rows edited or missing | Review taxonomy `publish_request_status` in the Dropdown Manager |

---

## References

- **Source package:** `packages/ahg-request-publish/`
- **Controllers:** `src/Controllers/PublishRequestController.php` (current
  token flow), `src/Controllers/RequestPublishController.php` (legacy port)
- **Schema:** `database/install_publish_request.sql` (current),
  `database/install.sql` (legacy), `database/seed_publish_request_status.sql`
- **Routes:** `routes/web.php`
- **GitHub issue:** [#617](https://github.com/ArchiveHeritageGroup/heratio/issues/617);
  the token-anchored flow was delivered under Heratio #745.
