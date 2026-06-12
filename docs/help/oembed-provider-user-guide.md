# Embedding records on other sites (oEmbed)

Heratio is an **oEmbed provider**. [oEmbed](https://oembed.com/) is the open
standard that lets other websites turn a pasted record link into a rich, embedded
card automatically - the same way pasting a YouTube or Flickr link produces a player
or a photo card instead of a bare URL.

So when a researcher, a blogger, or a partner institution pastes the URL of one of
your **published** records into a CMS, a chat tool, a learning platform, or a
website that supports oEmbed, that site can show a tidy card with the record's
**title**, an optional **thumbnail**, its **creator**, and your **institution
name** - linking straight back to the record on your catalogue.

## Where it lives

The provider endpoint is:

```
GET /oembed?url={recordUrl}
```

A consumer site calls it with the record URL it wants to embed, for example:

```
/oembed?url=https://your-catalogue.example/title-of-object
```

and gets back the card data. Most modern publishing platforms do this for you
automatically once they recognise your catalogue as an oEmbed provider; many also
discover the endpoint on their own (see "Automatic discovery" below).

## What you can embed

Only **published** records can be embedded. The endpoint maps the pasted URL back to
the matching catalogue record and checks it is published before returning anything.
If the URL is not one of your records, or the record is still a draft, the consumer
simply gets a clean "not found" response and no card appears - a draft is never
exposed.

## What the card shows

The returned card is a small, self-contained box that includes:

- the record **title**,
- a **thumbnail** image, when the record has one,
- the **creator** ("Created by ..."), when one is recorded,
- your **institution / site name** (taken from your Site title setting), and
- a link back to the full record.

The card is plain HTML with no scripts and no external stylesheet, so it is safe for
a host site to drop straight into a page. A consumer can pass `maxwidth` (and
`maxheight`) to fit the card to their column, and the card respects it.

## Formats

- **JSON** (the default) - `/oembed?url=...&format=json`. This is what most
  consumer sites use. It is served cross-origin friendly so a site's browser-side
  code can fetch it.
- **XML** - `/oembed?url=...&format=xml`, for consumers that prefer XML. The
  document is a standard, well-formed `<oembed>` document.

## Automatic discovery

Each published record page also carries a hidden discovery tag in its `<head>`:

```html
<link rel="alternate" type="application/json+oembed"
      href="/oembed?url={thisPage}" title="oEmbed">
```

A consumer that follows the standard oEmbed flow can read this tag from the page and
find the endpoint without being told the URL pattern in advance.

## Privacy and safety

- **Published only.** Draft / unpublished records are never embeddable.
- **One record at a time.** Each request returns a single record's card.
- **Read-only.** The endpoint never changes any data.
- **No surprises in the card.** Record titles and creator names are safely escaped,
  so an unusual title can never break or hijack the host page.
- **International.** The endpoint is jurisdiction-neutral and language-neutral; it
  uses the record's own authoritative title.

## Troubleshooting

- **"No card appears when I paste a record link."** Confirm the record is
  published, and that the link is a normal record URL on your catalogue (not an
  admin or search URL). Also confirm the consumer site actually supports oEmbed.
- **"The card has no image."** Only records with a generated thumbnail surrogate
  show an image; records without one still show the title and link.
- **"The card has no creator line."** A creator only appears when one is recorded on
  the description.
