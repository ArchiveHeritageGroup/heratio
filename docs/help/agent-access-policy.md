---
title: Agent and harvester access policy
slug: agent-access-policy
category: Integration
---

# Agent and harvester access policy

Heratio catalogues are meant to be read by people, by search engines, by other catalogues and by AI assistants. This policy says how each is welcome, through which door, and what is not allowed. It describes Heratio's defaults; the institution that runs an install can set its own limits, and its administrator is the contact for keys and questions.

## Who is welcome

- **People**, on the website.
- **Search engines and preservation crawlers**: Google, Bing, other search engines, and archive.org's Wayback Machine, following robots.txt.
- **Harvesters and aggregators**, over OAI-PMH and ResourceSync, without a key unless the install requires one.
- **AI assistants acting for a person**, through the REST API or the AHG Archives MCP, with a key or through the public MCP endpoint. This covers an assistant that fetches a page because someone asked it to.
- **Developers and integrations**, through the REST API with a key.

## What is not allowed

- **Bulk crawling to train AI models.** GPTBot, ClaudeBot, anthropic-ai, CCBot, Amazonbot, Bytespider and meta-externalagent are refused at the web server. AI search crawlers that send readers back, such as OAI-SearchBot, are allowed.
- **Crawling the browse filters.** Every combination of filters is a separate address and a separate database query. To collect records in bulk, use OAI-PMH, ResourceSync, the sitemap or the API, which are built for it.
- **Getting round the limits**, for example by spreading requests over many addresses or user agents.
- **Reaching content the catalogue withholds.** Drafts, embargoed and classified records, records under an ODRL prohibition and culturally restricted material are not served, and attempts to find them through another door are not permitted.

## The doors

| Door | For | Key | Limit |
| --- | --- | --- | --- |
| Website, with `/robots.txt` and `/sitemap.xml` | People and search engines | No | Browse is capped site-wide |
| REST API v2, `/api/v2/` | Integrations and AI agents | Yes | Per key: 1,000 an hour by default, optional daily limit |
| REST API v1, `/api/v1/` | Simple read-only lookups | No | 60 a minute per address |
| OAI-PMH, `/oai` | Metadata harvesting | Only if OAI authentication is on | |
| ResourceSync, `/.well-known/resourcesync` | Keeping a mirror in step | No | |
| AHG Archives MCP | AI assistants | Public endpoint: no. Staff endpoint: yes | Those of the REST API |
| GraphQL, `/admin/graphql` | Signed-in staff only | Session, not keys | |

: Ways in

On heratio.org the MCP runs at `https://heratio.mcp.theahg.co.za/public/mcp` (published records, no key) and `https://heratio.mcp.theahg.co.za/mcp` (each person signs in with their own key).

Scripts and agents may use any HTTP library on the API, OAI-PMH, ResourceSync, robots.txt and sitemap routes. A descriptive user agent with a contact address, such as `MyHarvester/1.0 (archives@example.org)`, is good manners and helps an administrator reach you before blocking anything.

## Getting a key

Sign in and open **`/api/keys`**. Choose a name and the scopes you need; a key can never hold a scope your own account does not have. Copy the key when it is shown, because it is shown only once.

- **For a service** (an organisation's harvester or assistant), ask an administrator. They issue the key from that service account's user page.
- **Without an account**, a key cannot be issued. The public MCP endpoint, REST v1, OAI-PMH and ResourceSync need none.

A key acts as the person who made it. One made by a user with no groups reads the public catalogue only, exactly what a visitor would see. Revoke a key at `/api/keys` when it is no longer needed.

The scopes and what each allows are listed in [REST API - AI assistants](/help/article/api-ai-assistants#keys).

## What every reader gets, and does not

Every door applies the same rules as the public website:

- **Published records only.** Drafts are never served to public keys or to harvesters.
- **Restrictions hold everywhere.** Culturally restricted (ICIP), ODRL-prohibited, embargoed and classified records are left out of lists, search and harvests, and a direct request answers as if the record did not exist.
- **Personal data stays protected.** Fields under a privacy rule are redacted, and GPS positions in image metadata are withheld while a privacy finding is open.
- **Rights travel with the record.** Each record read over REST v2 carries a `rights` block: the rights statements and licences in force, and its Traditional Knowledge (TK) and Biocultural (BC) labels with their Local Contexts codes. Rights holders are shown to staff keys only.
- **Labels are honoured by assistants.** The AHG Archives MCP withholds the files of records labelled TK Secret/Sacred, TK Community Use Only, TK Women Restricted or TK Men Restricted, and of any record whose rights cannot be read, from the assistants it serves.

## When you reach a limit

A limit answers `429` with `Retry-After` in seconds; a busy browse page answers `503`. Wait and carry on. Neither is a ban, and neither removes anything from search. Repeated abuse can lead to an address being blocked; an administrator can always be asked to lift a block.

## See also

- [API worked examples](/help/article/api-worked-examples): list, read, create, update, delete, search and harvest, step by step.
- [Bots, crawlers and search engines](/help/article/bots-crawlers-and-search): how the protections work and how to set them up.
- [OAI-PMH Metadata Harvesting](/help/article/oai-user-guide) and [Open Data: ResourceSync](/help/article/open-data-resourcesync-user-guide).
