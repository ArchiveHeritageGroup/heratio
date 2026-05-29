# Chatbot and AI Discovery - User Guide

The Heratio assistant helps you find records, understand archival descriptions,
and complete common library tasks in plain language. It works in three ways:

- A floating chat widget on every page (when enabled by your institution).
- A full-page chat at `/chatbot`.
- An optional WhatsApp number, if your institution has turned that channel on.

This guide covers what the assistant can do for you as a researcher or library
patron. Administrators should read the companion guide,
`docs/admin/chatbot-admin.md`.

## What the assistant can do

### Answer questions about the catalogue

Ask anything about the holdings - "What records do you have about the 1922
miners' strike?", "Who created the railway fonds?", "Summarise the scope of
collection AB/12". The assistant retrieves matching catalogue records and
answers using only what those records say, citing each source as `[1]`, `[2]`
and so on. If nothing in the catalogue matches, it tells you so rather than
guessing.

### Library task skills

For library patrons, the assistant can complete three tasks directly. To use
them you must be signed in with the account linked to your library membership
(matched by your email address). If you are not signed in, the assistant will
ask you to do so.

- **Renew a loan.** "Please renew my loan", or name the item to target a
  specific one ("renew The Lighthouse Keepers"). If you have several loans and
  do not name one, the assistant lists them so you can choose. Renewal is
  refused (with an explanation) when an item has hit its renewal limit or
  another patron is waiting for it.
- **Request an interlibrary loan.** "I need an interlibrary loan for
  'Quiet Streets'". The assistant lodges the request against your account; the
  library follows up on availability and any fees.
- **Check item availability.** "Is 'Mapungubwe Gold' available?" or give an
  ISBN or call number. No sign-in is required for this - it is public catalogue
  information. The assistant reports how many copies are available to borrow.

### Talk to a librarian

If the assistant cannot help, ask to "talk to a librarian". This raises a
tracked escalation to library staff and gives you a reference number.

## Discovery search and recommendations

Behind the search box, two JSON endpoints power richer discovery experiences:

- **Discovery search** ranks full-text results and returns the facet counts
  (repository, level, subjects, places, and more) used to refine a search.
- **Recommendations** suggest records similar to one you are viewing
  ("more like this"), ranked by meaning rather than keyword overlap.

When your institution enables the optional enhancements, discovery search can
also broaden your query with related terms (so "trains" also finds
"locomotive" and "railway") and gently re-order results to favour topics you
have searched for recently. Both are opt-in and off by default.

## Privacy

The assistant answers only from published catalogue descriptions and follows
the applicable privacy regime (for example POPIA or GDPR). It does not reveal
personal information beyond what is already public in the catalogue. Read the
full notice at `/chatbot/policy` before you start a conversation.

## Tips

- Be specific: a title, name, date range, or reference code gives better
  answers than a broad topic.
- Put exact titles in quotes when asking about availability or interlibrary
  loans - the assistant uses the quoted text as the title.
- The assistant remembers the current conversation. Use "reset" (or the reset
  control in the widget) to start fresh.
