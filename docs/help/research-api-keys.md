> Heratio Help Center article. Category: Research / API.

## Overview
Generate API keys to access research data programmatically via REST API.

## Generating Keys
1. Go to Profile → API Keys
2. Click Generate Key
3. Enter name, permissions (read/write/search), expiry date
4. Copy the key immediately — it is only shown once

## Authentication
Include the key as X-API-Key header or api_key query parameter.

## Endpoints
- GET /profile, /projects, /collections, /searches, /bookings, /bibliographies, /annotations, /stats
- POST /projects, /collections, /bookings

## Revoking Keys
Click Revoke on an active key. This cannot be undone.
