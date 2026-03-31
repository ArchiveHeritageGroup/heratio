# ahgGraphQLPlugin - Technical Documentation

## Overview

The ahgGraphQLPlugin provides a GraphQL API endpoint as an alternative to the REST API, offering flexible querying with built-in security safeguards.

| Property | Value |
|----------|-------|
| Plugin Name | ahgGraphQLPlugin |
| Version | 1.0.0 |
| Category | Integration |
| Dependencies | ahgAPIPlugin, webonyx/graphql-php |
| Endpoint | `/api/graphql` |

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Client Request                           │
│                    POST /api/graphql                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                     indexAction.class.php                        │
│  • CORS handling                                                 │
│  • Authentication (ApiKeyService)                                │
│  • Request parsing                                               │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      GraphQLService.php                          │
│  • Schema building                                               │
│  • Validation rules (depth, complexity)                          │
│  • Query execution                                               │
│  • Context building                                              │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  SchemaBuilder  │ │ Security Rules  │ │    Resolvers    │
│  • Query type   │ │ • DepthLimit    │ │ • ItemResolver  │
│  • Mutation type│ │ • Complexity    │ │ • ActorResolver │
│  • Type defs    │ │ • Introspection │ │ • TaxonomyRes.  │
└─────────────────┘ └─────────────────┘ └─────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                      ApiRepository                               │
│              (Reused from ahgAPIPlugin)                          │
│  • Database queries via Laravel Query Builder                    │
└─────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_97682844.png)
```

## Directory Structure

```
ahgGraphQLPlugin/
├── config/
│   └── ahgGraphQLPluginConfiguration.class.php
├── lib/
│   ├── GraphQL/
│   │   ├── Schema/
│   │   │   ├── SchemaBuilder.php
│   │   │   └── Types/
│   │   │       ├── ItemType.php
│   │   │       ├── ActorType.php
│   │   │       ├── RepositoryType.php
│   │   │       ├── TermType.php
│   │   │       ├── UserType.php
│   │   │       ├── ConnectionTypes.php
│   │   │       └── ScalarTypes.php
│   │   ├── Resolvers/
│   │   │   ├── BaseResolver.php
│   │   │   ├── ItemResolver.php
│   │   │   ├── ActorResolver.php
│   │   │   ├── TaxonomyResolver.php
│   │   │   └── UserResolver.php
│   │   └── Security/
│   │       ├── DepthLimitRule.php
│   │       └── ComplexityAnalyzer.php
│   └── GraphQLService.php
├── modules/
│   └── graphql/
│       ├── config/module.yml
│       ├── actions/
│       │   ├── indexAction.class.php
│       │   └── playgroundAction.class.php
│       └── templates/
│           └── playgroundSuccess.php
├── data/
│   └── install.sql
└── extension.json
```

## Database Schema

### Query Logging Table

```sql
CREATE TABLE ahg_graphql_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT UNSIGNED NULL,
    operation_name VARCHAR(255) NULL,
    complexity_score INT UNSIGNED NULL,
    depth INT UNSIGNED NULL,
    execution_time_ms INT UNSIGNED NULL,
    success TINYINT(1) DEFAULT 1,
    created_at DATETIME NOT NULL,
    INDEX idx_api_key_id (api_key_id),
    INDEX idx_created_at (created_at),
    INDEX idx_operation_name (operation_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## GraphQL Schema

### Query Type

```graphql
type Query {
  # Single lookups
  item(slug: String, id: ID): Item
  actor(slug: String, id: ID): Actor
  repository(slug: String, id: ID): Repository
  taxonomy(id: ID!): Taxonomy

  # Collections (paginated)
  items(first: Int, after: String, repository: String, level: String, sector: String): ItemConnection!
  actors(first: Int, after: String, entityType: String): ActorConnection!
  repositories(first: Int, after: String): RepositoryConnection!

  # Other
  taxonomies: [Taxonomy!]!
  search(query: String!, first: Int, after: String): ItemConnection!
  me: User
}
```

### Core Types

#### Item Type
```graphql
type Item {
  id: ID!
  slug: String!
  identifier: String
  title: String!
  levelOfDescription: Term
  sector: String
  scopeAndContent: String
  extentAndMedium: String
  archivalHistory: String
  acquisition: String
  arrangement: String
  accessConditions: String
  reproductionConditions: String
  repository: Repository
  parent: Item
  children(first: Int, after: String): ItemConnection!
  ancestors: [Item!]!
  dates: [Event!]!
  subjects: [Term!]!
  places: [Term!]!
  creators: [Actor!]!
  digitalObjects: [DigitalObject!]!
  childrenCount: Int!
}
```

#### Actor Type
```graphql
type Actor {
  id: ID!
  slug: String!
  authorizedFormOfName: String!
  entityType: Term
  datesOfExistence: String
  history: String
  places: String
  functions: String
  relatedItems(first: Int, after: String): ItemConnection!
}
```

#### Repository Type
```graphql
type Repository {
  id: ID!
  slug: String!
  name: String!
  identifier: String
  holdings(first: Int, after: String): ItemConnection!
  itemCount: Int!
}
```

#### Connection Types (Relay-style)
```graphql
type ItemConnection {
  edges: [ItemEdge!]!
  pageInfo: PageInfo!
  totalCount: Int!
}

type ItemEdge {
  node: Item!
  cursor: String!
}

type PageInfo {
  hasNextPage: Boolean!
  hasPreviousPage: Boolean!
  startCursor: String
  endCursor: String
}
```

## Security Implementation

### Depth Limiting

**File:** `lib/GraphQL/Security/DepthLimitRule.php`

Prevents deeply nested queries that could cause performance issues:

```php
class DepthLimitRule extends ValidationRule
{
    private int $maxDepth = 10;

    public function getVisitor(QueryValidationContext $context): array
    {
        // Traverses AST and calculates max depth
        // Reports error if depth > maxDepth
    }
}
```

**Example blocked query:**
```graphql
{
  items {
    edges {
      node {
        children {
          edges {
            node {
              children {  # ... continues beyond 10 levels
              }
            }
          }
        }
      }
    }
  }
}
```

### Complexity Analysis

**File:** `lib/GraphQL/Security/ComplexityAnalyzer.php`

Assigns costs to fields and limits total query complexity:

| Field | Cost |
|-------|------|
| Simple fields | 1 |
| subjects, places, dates | 2-3 |
| children, relatedItems, holdings | 10 |
| search | 15 |

Costs are multiplied by pagination `first` argument.

**Max complexity:** 1000

### Introspection Control

- **Development:** Introspection enabled (for GraphQL Playground)
- **Production:** Introspection disabled

Detection via:
- `SF_ENVIRONMENT === 'dev'`
- `sf_debug === true`

## Authentication

Reuses `ApiKeyService` from ahgAPIPlugin:

```php
// In indexAction.class.php
protected function authenticate(): bool
{
    // 1. Check session auth
    if ($this->context->user->isAuthenticated()) {
        $this->apiKeyInfo = [
            'type' => 'session',
            'scopes' => ['read', 'write', 'delete'],
            // ...
        ];
        return true;
    }

    // 2. Check API key
    $this->apiKeyService = new ApiKeyService();
    $this->apiKeyInfo = $this->apiKeyService->authenticate();
    // ...
}
```

**Supported headers:**
- `X-API-Key`
- `X-REST-API-Key`
- `Authorization: Bearer <key>`

## Resolvers

### BaseResolver

```php
abstract class BaseResolver
{
    protected ApiRepository $repository;
    protected string $culture;

    protected function buildConnection(array $items, int $total, int $offset, int $first): array
    {
        return ConnectionTypes::buildConnection($items, $total, $offset, $first);
    }
}
```

### ItemResolver

Key methods:

| Method | Purpose |
|--------|---------|
| `resolveBySlug($slug)` | Get item by URL slug |
| `resolveById($id)` | Get item by database ID |
| `resolveList($first, $offset, ...)` | Paginated item list |
| `resolveChildren($parentId, ...)` | Child items |
| `resolveAncestors($itemId)` | Hierarchy path |
| `resolveDates($itemId)` | Date events |
| `resolveSubjects($itemId)` | Subject access points |
| `resolveCreators($itemId)` | Related actors |
| `resolveDigitalObjects($itemId)` | Attached files |
| `resolveSearch($query, ...)` | Full-text search |

### ActorResolver

| Method | Purpose |
|--------|---------|
| `resolveBySlug($slug)` | Get actor by slug |
| `resolveList($first, $offset, $entityType)` | Paginated actor list |
| `resolveRelatedItems($actorId, ...)` | Items linked to actor |

### TaxonomyResolver

| Method | Purpose |
|--------|---------|
| `resolveAll()` | All taxonomies |
| `resolveById($id)` | Single taxonomy |
| `resolveTerms($taxonomyId)` | Terms in taxonomy |
| `resolveTermChildren($parentId)` | Child terms |

## Routes

Registered in `ahgGraphQLPluginConfiguration.class.php`:

| Method | Route | Action |
|--------|-------|--------|
| POST | `/api/graphql` | graphql/index |
| GET | `/api/graphql` | graphql/index |
| GET | `/api/graphql/playground` | graphql/playground |

## Configuration

### Plugin Configuration

```php
class ahgGraphQLPluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Enable graphql module
        $enabledModules[] = 'graphql';

        // Register routes
        $this->dispatcher->connect('routing.load_configuration', [$this, 'routingLoadConfiguration']);
    }
}
```

### GraphQL Service Options

```php
$service = new GraphQLService([
    'debug' => false,           // Show detailed errors
    'maxDepth' => 10,           // Query depth limit
    'maxComplexity' => 1000,    // Complexity limit
    'introspection' => false,   // Allow schema introspection
    'culture' => 'en',          // Default language
]);
```

## Error Responses

### Validation Errors

```json
{
  "errors": [
    {
      "message": "Query depth of 12 exceeds maximum allowed depth of 10",
      "locations": [{"line": 1, "column": 1}],
      "extensions": {"code": "DEPTH_LIMIT"}
    }
  ]
}
```

### Authentication Errors

```json
{
  "errors": [
    {
      "message": "Unauthorized",
      "extensions": {"code": "Unauthorized"}
    }
  ]
}
```

### Field Errors

```json
{
  "data": {
    "item": null
  },
  "errors": [
    {
      "message": "Item not found",
      "path": ["item"]
    }
  ]
}
```

## Testing

### CLI Test

```php
php -r '
require "config/ProjectConfiguration.class.php";
$config = ProjectConfiguration::getApplicationConfiguration("qubit", "prod", false);
sfContext::createInstance($config);
require_once "atom-framework/bootstrap.php";

// ... autoloader setup ...

$service = new AhgGraphQLPlugin\GraphQLService(["debug" => true]);
$result = $service->execute("{ taxonomies { id name } }");
print_r($result);
'
```

### HTTP Test

```bash
curl -X POST https://your-instance.com/api/graphql \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-key" \
  -d '{"query": "{ items(first: 3) { totalCount } }"}'
```

## Dependencies

### PHP Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| webonyx/graphql-php | ^15.0 | GraphQL implementation |

### Plugin Dependencies

| Plugin | Purpose |
|--------|---------|
| ahgAPIPlugin | ApiKeyService, ApiRepository |

## Performance Considerations

1. **Pagination limits** - Max 100 items per page
2. **Depth limits** - Max 10 levels deep
3. **Complexity limits** - Max 1000 per query
4. **Query logging** - Optional analytics
5. **N+1 prevention** - Batch loading in resolvers

## Future Enhancements

- [ ] Mutations (create/update/delete)
- [ ] Subscriptions (real-time updates)
- [ ] DataLoader for batch loading
- [ ] Persisted queries
- [ ] Rate limiting per operation

## Related Documentation

- [GraphQL User Guide](../graphql-user-guide.md)
- [ahgAPIPlugin](./ahgAPIPlugin.md)
- [API Technical Reference](./api-technical-reference.md)
