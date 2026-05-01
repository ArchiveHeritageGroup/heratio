# Heratio - Outbound HTTP / SSRF Protection Policy

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

Server-Side Request Forgery (SSRF) attacks trick the server into making HTTP requests to unintended destinations, often internal services or cloud metadata endpoints. Heratio uses `HttpClientService` as the safe HTTP client and `XmlParserService` for XXE-protected XML parsing.

---

## 2. HttpClientService

### API

| Method | Description |
|--------|-------------|
| `get($url, $headers, $options)` | Safe GET request |
| `post($url, $body, $headers, $options)` | Safe POST request |
| `request($method, $url, $body, $headers, $options)` | Generic request |
| `isPrivateIp($ip)` | Check if IP is private/reserved |
| `isBlockedHost($host)` | Check against blocked hostnames |

### Return Value

All methods return an array:

```php
[
    'status'  => 200,           // HTTP status code (0 on connection failure)
    'body'    => '...',         // Response body
    'headers' => ['Key' => 'Value'],
    'error'   => null,          // Error message, or null on success
]
```

### Usage Example

```php
use AtomFramework\Services\HttpClientService;

// Simple GET
$response = HttpClientService::get('https://api.example.com/data');
if ($response['status'] === 200) {
    $data = json_decode($response['body'], true);
}

// POST with custom headers
$response = HttpClientService::post(
    'https://api.example.com/submit',
    json_encode($payload),
    ['Content-Type' => 'application/json'],
    ['timeout' => 30]
);
```

---

## 3. SSRF Protections

### 3.1 URL Scheme Allowlist
Only `http` and `https` schemes are permitted. File, FTP, gopher, etc. are blocked.

### 3.2 Private IP Blocking
All resolved IPs are checked with:
```php
filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
```

This blocks:
- `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16` (RFC 1918)
- `127.0.0.0/8` (loopback)
- `169.254.0.0/16` (link-local)
- `0.0.0.0/8`, `240.0.0.0/4` (reserved)
- IPv6 equivalents

### 3.3 Cloud Metadata Blocking
Hardcoded blocked hosts:
- `169.254.169.254` (AWS/GCP/Azure metadata)
- `metadata.google.internal`
- `metadata.internal`

### 3.4 DNS Pre-Resolution
Before connecting, the hostname is resolved via `gethostbynamel()` and all IPs are checked. This prevents DNS rebinding attacks where a hostname initially resolves to a public IP, then changes to a private IP.

### 3.5 Redirect IP Re-Validation
Redirects are followed manually (not via `CURLOPT_FOLLOWLOCATION`). Each redirect target is subjected to the full SSRF check (scheme, DNS, IP validation).

### 3.6 Connection Pinning
The first resolved IP is pinned via `CURLOPT_RESOLVE` to prevent DNS changes between resolution and connection.

---

## 4. XmlParserService - XXE Protection

### API

| Method | Description |
|--------|-------------|
| `parseString($xml)` | SimpleXML with `LIBXML_NONET | LIBXML_NOCDATA` |
| `parseFile($filepath)` | SimpleXML from file |
| `loadDom($xml)` | DOMDocument with XXE protection |
| `loadDomFile($filepath)` | DOMDocument from file |

### Usage

```php
use AtomFramework\Services\XmlParserService;

// Parse XML string
$xml = XmlParserService::parseString($xmlContent);

// Parse XML file (e.g., EAD import)
$xml = XmlParserService::parseFile('/path/to/finding-aid.xml');

// Load as DOMDocument
$dom = XmlParserService::loadDom($xmlContent);
```

### Flags
- `LIBXML_NONET` - Disables network access during parsing
- `LIBXML_NOCDATA` - Merges CDATA sections into text nodes
- `substituteEntities = false` - Prevents entity expansion on DOMDocument

---

## 5. SSL/TLS Policy

- SSL verification is ON by default (`CURLOPT_SSL_VERIFYPEER = true`)
- To override (development only): pass `'verifySsl' => false` in options
- The system CA bundle is used (OS-managed)

---

## 6. Defaults

| Setting | Value |
|---------|-------|
| Timeout | 15 seconds |
| Connect timeout | 10 seconds |
| Max response size | 10 MB |
| Max redirects | 5 |
| SSL verification | ON |
| User agent | `Heratio-Heratio/2.8 (Archive Management System)` |

---

## 7. Fixed Vulnerabilities (Issue #197)

### 7.1 LibraryCoverService - SSL Disabled (HIGH)
- **File:** `atom-framework/src/Services/LibraryCoverService.php`
- **Problem:** `CURLOPT_SSL_VERIFYPEER => false` on all outbound requests
- **Fix:** Migrated to `HttpClientService::get()` (SSL on by default, SSRF protection)

### 7.2 IsbnLookupService - No SSRF Protection (MEDIUM)
- **File:** `atom-framework/src/Services/IsbnLookupService.php`
- **Problem:** Direct curl with no private IP checking
- **Fix:** Migrated `httpGet()` to use `HttpClientService::get()`

---

## 8. Migration Guide for Locked Plugins

Locked plugins with outbound HTTP that should be migrated in future releases:

| Plugin | File(s) | Priority | Notes |
|--------|---------|----------|-------|
| ahgIiifPlugin | IIIF manifest fetching | Medium | Fetches external IIIF resources |
| ahgDoiPlugin | DataCite API calls | Medium | External API with auth |
| ahgDiscoveryPlugin | Wikidata/authority lookups | Low | Read-only public APIs |
| ahgSemanticSearchPlugin | WordNet/Wikidata sync | Low | Read-only public APIs |
| ahgFederationPlugin | Remote repository queries | High | User-configurable URLs |

### Migration Steps

1. Replace direct `curl_init()` / `curl_exec()` with `HttpClientService::get()` or `::post()`
2. Remove explicit `CURLOPT_SSL_VERIFYPEER => false`
3. Remove manual redirect following (HttpClientService handles it)
4. Handle the return array format: `$response['status']`, `$response['body']`, `$response['error']`

---

## 9. Audit Checklist

- [ ] All outbound HTTP uses `HttpClientService` (no direct `curl_init`)
- [ ] No `CURLOPT_SSL_VERIFYPEER => false` in production code
- [ ] User-provided URLs are never fetched without SSRF protection
- [ ] XML parsing uses `XmlParserService` (no direct `simplexml_load_string`)
- [ ] Response data is validated before use (JSON decoded, size checked)
