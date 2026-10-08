# Machine and agent access

> **Status: draft for approval** (heratio#1544). Wording and limits to be approved before publication.

Heratio is built to be read by software as well as by people: harvesters, aggregators, research tools and AI agents. This page says what machines may do, through which doors, and how fast.

## The doors

| Interface | Path | Key needed | Use it for |
| --- | --- | --- | --- |
| OAI-PMH 2.0 | `/oai` (guide at `/oai/docs`) | No | Harvesting published descriptions in Dublin Core and EAD. |
| ResourceSync | `/.well-known/resourcesync` | No | Keeping a mirror in step: resource lists and change lists. |
| REST API v1 | `/api/v1/...` | For writes | Read access to published records; writes with a key. |
| REST API v2 | `/api/v2/...` | Yes | Full create, read, update and delete over descriptions, authorities and more. |
| Sitemap | `/sitemap.xml` | No | Discovering every published record page. |

Machine clients should use these interfaces, not scrape the HTML pages. The pages change with the design; the interfaces are versioned.

: Machine interfaces

## What is allowed

- Reading and harvesting anything that is published, through the interfaces above.
- Writing through the API with a key whose scope allows it (`write`, `delete`). Every write is logged against the key.
- Agents acting for a person may use that person's key. The key holder is responsible for what is done with it.

What is not allowed: getting round access controls, harvesting unpublished material, sending credentials other than your own key, and deliberately exceeding the limits below.

## Limits

- Public and v1 routes: 120 requests a minute per address.
- v2: 1,000 requests an hour per key by default. An administrator can raise a key's limit.
- A client that goes over the limit gets HTTP 429 and should back off. Retry after the period resets rather than at once.

OAI-PMH clients should use resumption tokens and incremental harvests (`from=`). Harvesting the whole repository every night is not needed.

## Getting a key

- Registered researchers create and revoke their own keys under **Research > API keys** (`/research/apiKeys`).
- Staff and integration keys are issued by an administrator. Keys can also be managed over the API at `/api/v2/keys`.
- Send the key in the `X-API-Key` header or as `Authorization: Bearer <key>`. Never put it in a URL.

## Identification

Set a `User-Agent` that names your tool and gives a contact address, for example `ExampleHarvester/1.2 (+mailto:data@example.org)`. Unidentified heavy clients are the first to be blocked.

See also: worked API examples (`docs/api-worked-examples.md`) and the bot and SEO policy (`docs/bot-and-seo-policy.md`).
