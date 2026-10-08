> Heratio Help Center article. Category: Integration.

# GraphQL API User Guide

The GraphQL endpoint lets a signed-in user ask for exactly the fields they need in a single query.

> **Read-only, staff session only.** GraphQL in Heratio answers queries; it has no mutations, and it is not reachable with an API key. To create, update or delete records from a program, use the REST API v2, which has full CRUD with API keys (see the API user guide and `docs/api-worked-examples.md`). heratio#1539.

## Overview

| Feature | Description |
|---------|-------------|
| Endpoint | `POST /admin/graphql/execute` |
| Authentication | Signed-in browser session (read permission); no API keys |
| Operations | Queries only (read-only) |
| Format | JSON |
| Playground | `/admin/graphql/playground` |

## Quick Start

### 1. Sign in

Sign in to Heratio in the browser. The playground at `/admin/graphql/playground` uses that session, so it is the easiest place to try queries.

### 2. Make Your First Query

From the playground, or from a script that carries the browser session cookie and CSRF token:

```bash
curl -X POST https://your-instance.com/admin/graphql/execute \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: <token from the page>" \
  -b "laravel_session=<your session cookie>" \
  -d '{"query": "{ items(first: 5) { totalCount edges { node { title slug } } } }"}'
```

### 3. Response

```json
{
  "data": {
    "items": {
      "totalCount": 190,
      "edges": [
        { "node": { "title": "Annual Reports", "slug": "annual-reports" } },
        { "node": { "title": "Correspondence", "slug": "correspondence" } }
      ]
    }
  }
}
```

## Authentication

GraphQL uses the signed-in session only. An API key in `X-API-Key` or `Authorization: Bearer` is **not** accepted here; programs that authenticate with a key should use the REST API.

## Core Queries

### Browse Items (Archival Descriptions)

```graphql
query BrowseItems {
  items(first: 10, repository: "main-archive", level: "fonds") {
    totalCount
    edges {
      node {
        id
        slug
        title
        levelOfDescription { name }
        sector
        repository { name }
      }
      cursor
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

**Arguments:**
- `first` - Number of items (max 100)
- `after` - Cursor for pagination
- `repository` - Filter by repository slug
- `level` - Filter by level (fonds, series, file, item)
- `sector` - Filter by sector (archive, library, museum)

### Get Single Item

```graphql
query GetItem {
  item(slug: "annual-reports") {
    id
    slug
    identifier
    title
    scopeAndContent
    extentAndMedium
    accessConditions

    levelOfDescription { name }
    repository { name slug }

    dates {
      eventType
      dateDisplay
      startDate
      endDate
    }

    subjects { name }
    places { name }
    creators {
      slug
      authorizedFormOfName
    }

    digitalObjects {
      name
      mimeType
      thumbnailUrl
      masterUrl
    }

    parent { title slug }
    children(first: 5) {
      totalCount
      edges { node { title slug } }
    }
    ancestors { title slug }
  }
}
```

### Browse Actors (Authority Records)

```graphql
query BrowseActors {
  actors(first: 10, entityType: "person") {
    totalCount
    edges {
      node {
        id
        slug
        authorizedFormOfName
        entityType { name }
        datesOfExistence
        history
      }
    }
  }
}
```

### Get Single Actor

```graphql
query GetActor {
  actor(slug: "john-smith") {
    id
    slug
    authorizedFormOfName
    entityType { name }
    datesOfExistence
    history
    places
    functions

    relatedItems(first: 10) {
      totalCount
      edges {
        node { title slug }
      }
    }
  }
}
```

### Browse Repositories

```graphql
query BrowseRepositories {
  repositories(first: 10) {
    totalCount
    edges {
      node {
        id
        slug
        name
        identifier
        itemCount

        holdings(first: 5) {
          totalCount
          edges { node { title } }
        }
      }
    }
  }
}
```

### Get Taxonomies

```graphql
query GetTaxonomies {
  taxonomies {
    id
    name
    usage
    terms {
      id
      name
      code
      parent { name }
      children { name }
    }
  }
}
```

### Search

```graphql
query Search {
  search(query: "correspondence 1950", first: 20) {
    totalCount
    edges {
      node {
        title
        slug
        identifier
        levelOfDescription { name }
        repository { name }
      }
    }
  }
}
```

### Current User

```graphql
query Me {
  me {
    id
    username
    email  # Only visible to admins or self
  }
}
```

## Pagination

GraphQL uses cursor-based pagination (Relay-style connections):

```graphql
# First page
query FirstPage {
  items(first: 10) {
    edges { node { title } cursor }
    pageInfo { hasNextPage endCursor }
  }
}

# Next page (use endCursor from previous response)
query NextPage {
  items(first: 10, after: "Y3Vyc29yOjEw") {
    edges { node { title } cursor }
    pageInfo { hasNextPage endCursor }
  }
}
```

## Variables

Use variables for dynamic queries:

```graphql
query GetItem($slug: String!) {
  item(slug: $slug) {
    title
    scopeAndContent
  }
}
```

**Request:**
```json
{
  "query": "query GetItem($slug: String!) { item(slug: $slug) { title } }",
  "variables": { "slug": "annual-reports" }
}
```

## Fragments

Reuse field selections:

```graphql
fragment ItemBasics on Item {
  id
  slug
  title
  levelOfDescription { name }
}

query {
  items(first: 5) {
    edges {
      node {
        ...ItemBasics
        repository { name }
      }
    }
  }
}
```

## Error Handling

Errors are returned in the `errors` array:

```json
{
  "errors": [
    {
      "message": "Query depth of 12 exceeds maximum allowed depth of 10",
      "extensions": { "code": "DEPTH_LIMIT" }
    }
  ]
}
```

Common errors:

| Error | Cause | Solution |
|-------|-------|----------|
| `Unauthorized` | Not signed in, or the session expired | Sign in again in the browser |
| `Depth limit exceeded` | Query too deep (>10 levels) | Reduce nesting |
| `Complexity exceeded` | Query too expensive (>1000) | Reduce fields/pagination |

## Security Limits

| Limit | Value | Purpose |
|-------|-------|---------|
| Max Depth | 10 levels | Prevent deeply nested queries |
| Max Complexity | 1000 | Prevent expensive queries |
| Max Results | 100 per page | Pagination limit |
| Introspection | Dev only | Schema hidden in production |

## GraphQL Playground

In development mode, access the interactive GraphQL Playground at:

```
https://your-instance.com/admin/graphql/playground
```

Features:
- Schema documentation
- Query autocompletion
- Query history
- Response formatting

## Comparison: GraphQL vs REST

| Use Case | REST | GraphQL |
|----------|------|---------|
| Get item with children | 2+ requests | 1 request |
| Get only titles | Returns all fields | Returns only titles |
| Browse with relations | Multiple endpoints | Single query |
| Mobile/bandwidth | More data transfer | Minimal data |

**GraphQL Advantages:**
- Request exactly what you need
- Single request for related data
- Self-documenting schema
- Strongly typed

**REST Advantages:**
- Simpler for basic CRUD
- HTTP caching
- More familiar to most developers

## Example: Complete Item View

```graphql
query CompleteItem($slug: String!) {
  item(slug: $slug) {
    # Identity
    id
    slug
    identifier
    title

    # Classification
    levelOfDescription { name }
    sector

    # Content
    scopeAndContent
    extentAndMedium
    archivalHistory
    acquisition
    arrangement

    # Access
    accessConditions
    reproductionConditions

    # Context
    repository {
      name
      slug
    }

    # Dates
    dates {
      eventType
      dateDisplay
      startDate
      endDate
    }

    # Access Points
    subjects { id name }
    places { id name }
    creators {
      slug
      authorizedFormOfName
      entityType { name }
    }

    # Digital Objects
    digitalObjects {
      id
      name
      mimeType
      byteSize
      thumbnailUrl
      masterUrl
    }

    # Hierarchy
    parent { title slug }
    ancestors { title slug }
    children(first: 20) {
      totalCount
      edges {
        node {
          title
          slug
          levelOfDescription { name }
        }
      }
    }
    childrenCount
  }
}
```

## Support

- **Documentation:** https://github.com/ArchiveHeritageGroup/atom-extensions-catalog
- **Issues:** https://github.com/ArchiveHeritageGroup/atom-framework/issues
- **Email:** support@theahg.co.za
