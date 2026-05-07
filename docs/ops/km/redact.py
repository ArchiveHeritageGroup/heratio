"""
redact.py - shared credential / IP / key redaction for every KM ingest
script. Closes #49 (single source of truth so adding a new ingest script
inherits the floor automatically).

The pattern set was first added to ingest_heratio.py on 2026-04-30 after
an audit found the heratio MySQL root password retrievable via /api/ask.
Each ingest script now imports `redact_secrets` from here instead of
re-implementing it.

@copyright  Johan Pieterse / Plain Sailing
@license    AGPL-3.0-or-later
"""
from __future__ import annotations
import re

SECRET_PATTERNS = [
    # passwords on a key-value line
    (re.compile(r'(password\s*[:=]\s*[`\'"]?)([^\s`\'",]{6,})', re.I), r'\1<REDACTED>'),
    # API keys / tokens
    (re.compile(r'((?:api[_-]?key|secret|token|bearer)\s*[:=]\s*[`\'"]?)([A-Za-z0-9_\-./+]{12,})', re.I), r'\1<REDACTED>'),
    # *_PASSWORD=value style env-vars
    (re.compile(r'([A-Z][A-Z0-9_]+(?:PASSWORD|KEY|TOKEN|SECRET)\s*=\s*[`\'"]?)([^\s`\'",]{4,})'), r'\1<REDACTED>'),
    # Hard-coded literals known to appear in the corpus
    (re.compile(r'\b(?:Merlot|AtoM)@\d+\b'), '<REDACTED>'),
    (re.compile(r'\bahg_ai_demo_internal_\d+\b'), '<REDACTED>'),
    # Private IPv4 (RFC1918) - internal infra should not be in the public RAG.
    # Each prefix expects different octet counts to make a full 4-octet address:
    #   10.X.X.X       = 10. + three octets
    #   192.168.X.X    = 192.168. + two octets
    #   172.16-31.X.X  = 172.{16-31}. + two octets
    # Dates like 10.04.2026 are 3-octet so they don't match.
    (re.compile(r'\b(?:10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)\b'), '<INTERNAL_IP>'),
    # SSH / PEM keys
    (re.compile(r'(?:ssh-(?:rsa|ed25519|dss)|-----BEGIN [A-Z ]+ KEY-----)[A-Za-z0-9+/=\s]+'), '<SSH_KEY>'),
    # OpenAI / Anthropic-style sk-* keys
    (re.compile(r'\bsk-[A-Za-z0-9]{20,}\b'), '<REDACTED>'),
    # JWT tokens
    (re.compile(r'\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b'), '<REDACTED>'),
]


def redact_secrets(text: str) -> str:
    """Scrub literal credentials / IPs / keys before chunks go to Qdrant."""
    if not text:
        return text
    for pattern, replacement in SECRET_PATTERNS:
        text = pattern.sub(replacement, text)
    return text
