# Running Heratio on OpenSearch

Heratio's search runs on **OpenSearch** (Apache-2.0, OSI-approved) and that is the recommended engine from v1.165.12. Elasticsearch still works: Heratio talks to either over the same HTTP API, with no client library, so the choice is made in configuration, not code.

Why OpenSearch: the Elasticsearch builds Heratio used to ship (7.17 and 8.13) are licensed under the Elastic License 2.0 or SSPL, neither of which is OSI-approved; an AGPL option only appeared in Elasticsearch 8.16. OpenSearch is Apache-2.0 throughout, with no paid tier and no subscription.

Tested with OpenSearch 3.4.0 (November 2026): full reindex of descriptions, authority records, terms and repositories; global and advanced search, Boolean, phrase and fuzzy queries, autocomplete, facets and date filters, "description updates", and the services check - all against a server with the security plugin on (HTTPS plus login).

## Docker

The Docker stacks (`docker/docker-compose.yml`, `docker/docker-compose.client.yml`) run `opensearchproject/opensearch:3.4.0` as the `opensearch` service. It is reachable only on the stack's private network, so its security plugin is off there. Upgrading an existing Docker install from the old Elasticsearch service is safe: the search index is derived data and `docker/init.sh` rebuilds it from MySQL on first boot. The old `heratio-es` volume can be removed afterwards.

## Server install

1. Install OpenSearch 3.x from the official packages (artifacts.opensearch.org, the 3.x apt or yum repository) and start it. Give it 2 GB of heap or more for a medium collection.
2. With the security plugin on (the default), set an admin password at first start (`OPENSEARCH_INITIAL_ADMIN_PASSWORD`), and create a dedicated user for Heratio with rights on the `heratio_*` indices.
3. Run the installer with the search options:

```bash
sudo bin/install --es-host=https://localhost:9200 --es-user=heratio --es-pass='...' --es-ca=/etc/opensearch/root-ca.pem
```

`--es-insecure` skips certificate checks; use it only for a local test server with demo certificates.

The installer writes these to `.env`:

| Setting | Meaning |
| --- | --- |
| `ELASTICSEARCH_HOST` | Search server URL with scheme and port, e.g. `https://localhost:9200` |
| `ELASTICSEARCH_PREFIX` | Index name prefix, default `heratio_` |
| `ELASTICSEARCH_USERNAME`, `ELASTICSEARCH_PASSWORD` | Login, when the security plugin is on |
| `ELASTICSEARCH_CA` | Path to the CA certificate for a self-signed server |
| `ELASTICSEARCH_VERIFY_TLS` | `false` only for local testing |

The login and certificate settings are added only to requests aimed at that host and port (`AhgSearch\Support\SearchHostAuth`), so the password is never sent to any other service Heratio calls.

## Moving an existing install from Elasticsearch

1. Install OpenSearch alongside Elasticsearch on another port.
2. Point a test run at it and build the index: `ELASTICSEARCH_HOST=https://localhost:9201 ELASTICSEARCH_USERNAME=heratio ELASTICSEARCH_PASSWORD=... php artisan ahg:es-reindex --drop`
3. Check it: `php artisan ahg:services-check --json` reports `"engine": "opensearch 3.4.0"`, and Admin > Settings > Services shows the engine and cluster health.
4. Change `.env` to the OpenSearch settings and restart php-fpm and the queue worker.
5. Stop Elasticsearch once searches look right.

No data is migrated: the index is rebuilt from the database, which stays the source of truth.

## If something goes wrong

- **Searches return nothing and the log says "Search server returned HTTP 401"**: wrong user or password.
- **cURL error 60 (certificate)**: set `ELASTICSEARCH_CA`, or for a local test server only, `ELASTICSEARCH_VERIFY_TLS=false`.
- **Reindex reports "Cannot connect"**: check `ELASTICSEARCH_HOST` includes the scheme (`https://`) and port.
