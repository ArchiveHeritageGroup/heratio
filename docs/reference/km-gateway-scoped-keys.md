# KM web UI auth: rotatable, scope-limited gateway keys

Summary: the KM knowledge base web UI (km.theahg.co.za) authenticates browser
requests with a rotatable API key minted by the AHG AI gateway and restricted to
the `km` scope. The key can be revoked or rotated centrally without redeploying
KM. This replaced a static environment-variable key. Tracked in ahg-ai#1.

## The scope model

The gateway's API keys carry a `scope` (comma-separated). Three recognised values:

- `gateway` - the AI proxy endpoints (NER, summarize, translate, HTR, Ollama).
  This is the default for every pre-existing key.
- `km` - the KM web UI only.
- `km,gateway` - both, for trusted server-to-server callers (MCP servers, CLI,
  internal scripts).

A `gateway`-scoped key is refused by KM, and a `km`-scoped key is refused at the
AI proxy. So a key leaked from one surface cannot be abused on the other.

## How KM validates a key

KM first checks its two static environment keys (an admin key for MCP/server use
and the web key embedded in the page). If the presented key matches neither, KM
falls back to asking the gateway's internal verify endpoint whether the key is
valid and carries the `km` scope. Positive results are cached briefly in-process
so there is no per-request round-trip. The check fails closed: any error or
missing configuration results in rejection, not bypass.

This means the web key embedded in the KM page is a gateway-minted `km` key.
Revoking it at the gateway admin takes effect within seconds even though KM has
its own copy - that is the rotatability the design provides.

## Rotation runbook

1. In the gateway admin, revoke the current KM web key.
2. Mint a new key with scope `km`.
3. Set it as KM's web key in the service environment.
4. Reload and restart the KM service.

A brief positive-result cache means the old key may keep working for up to a
minute after revocation.

## What this design does and does not provide

Provides: revocability, scope restriction, and per-key quotas, all managed
centrally at the gateway.

Does not provide: secrecy of the embedded web key. It is visible in page source
by design; that trade-off is accepted because the key is scope-limited and
revocable.

## Related performance note

KM answer latency (a separate concern from auth) was traced to model-reload
thrash from mismatched context sizes between the warm-up probe and the real
answer call, plus the answer-length parameter being ignored on POST requests.
Both were corrected; cold-start answer time dropped from ~70s to under 10s.
