<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Self-service API key request endpoint. Adopters fill a public form; the
 * request is queued in openric_key_request and emailed to the admin. The
 * admin reviews + issues with `php artisan openric:issue-key {id}`.
 *
 * Reads on /api/ric/v1 are public — no key needed. This flow is purely for
 * acquiring write / delete scope without an out-of-band email thread.
 */

namespace AhgRic\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KeyRequestController extends Controller
{
    private const MAX_REQUESTS_PER_IP_PER_DAY = 5;

    /** GET /api/ric/v1/keys/request — HTML form */
    public function form(Request $request): Response
    {
        $status = $request->query('status');        // submitted / error
        $msg    = (string) $request->query('msg', '');
        $serverHost = $request->getHost();
        $adminEmail = env('OPENRIC_ADMIN_EMAIL', 'admin@' . $serverHost);
        $html = self::renderForm($serverHost, $adminEmail, $status, $msg);
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /** POST /api/ric/v1/keys/request — JSON or form-encoded submit */
    public function submit(Request $request): JsonResponse
    {
        $wantsJson = $request->wantsJson() || $request->header('Content-Type') === 'application/json';

        $data = $request->validate([
            'email'        => 'required|email|max:255',
            'organization' => 'nullable|string|max:255',
            'intended_use' => 'required|string|min:20|max:2000',
            'scopes'       => 'nullable|string|max:64',  // "read", "read,write", "read,write,delete"
        ]);

        // Normalise requested scopes to a safe subset.
        $scopesRaw = strtolower($data['scopes'] ?? 'read,write');
        $allowed = ['read', 'write', 'delete'];
        $scopes = array_values(array_intersect($allowed, array_map('trim', explode(',', $scopesRaw))));
        if (empty($scopes)) $scopes = ['read'];
        $scopesString = implode(',', $scopes);

        // Rate-limit by IP.
        $ip = $request->ip();
        $recent = DB::table('openric_key_request')
            ->where('requester_ip', $ip)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($recent >= self::MAX_REQUESTS_PER_IP_PER_DAY) {
            $err = 'Too many requests from your IP in the last 24 hours. Please email the admin directly.';
            if ($wantsJson) {
                return response()->json(['error' => 'rate_limited', 'message' => $err], 429);
            }
            return response()->json(['redirect' => route('openric.keys.form', ['status' => 'error', 'msg' => $err])], 429);
        }

        $id = DB::table('openric_key_request')->insertGetId([
            'email'            => $data['email'],
            'organization'     => $data['organization'] ?? null,
            'intended_use'     => $data['intended_use'],
            'requested_scopes' => $scopesString,
            'status'           => 'pending',
            'requester_ip'     => $ip,
            'user_agent'       => substr((string) $request->userAgent(), 0, 255),
            'created_at'       => now(),
        ]);

        // Notify admin (best-effort — Mail misconfiguration shouldn't 500 the request).
        $adminEmail = env('OPENRIC_ADMIN_EMAIL', 'johan@theahg.co.za');
        try {
            $body = self::adminNotificationBody($id, $data, $scopesString, $ip, $request);
            Mail::raw($body, function ($m) use ($adminEmail, $data, $id) {
                $m->to($adminEmail)
                  ->subject("[OpenRiC] New API key request #{$id} from {$data['email']}");
            });
        } catch (\Throwable $e) {
            Log::warning('[openric] Failed to email admin about key request ' . $id . ': ' . $e->getMessage());
        }

        if ($wantsJson) {
            return response()->json([
                'success' => true,
                'request_id' => $id,
                'message' => 'Request received. You will receive an email once approved.',
                'status_url' => url("/api/ric/v1/keys/request/{$id}"),
            ], 201);
        }

        return response()->json([
            'redirect' => url('/api/ric/v1/keys/request?status=submitted&id=' . $id),
        ]);
    }

    /** GET /api/ric/v1/keys/request/{id} — status check (no secret revealed) */
    public function status(int $id): JsonResponse
    {
        $req = DB::table('openric_key_request')->where('id', $id)->first();
        if (!$req) {
            return response()->json(['error' => 'not_found'], 404);
        }
        return response()->json([
            'id'       => $req->id,
            'status'   => $req->status,
            'email'    => self::maskEmail($req->email),
            'requested_scopes' => $req->requested_scopes,
            'created_at'  => $req->created_at,
            'reviewed_at' => $req->reviewed_at,
        ]);
    }

    private static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) return '***';
        $local = $parts[0];
        $masked = strlen($local) > 2 ? substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2)) : '**';
        return $masked . '@' . $parts[1];
    }

    private static function adminNotificationBody(int $id, array $data, string $scopes, string $ip, Request $req): string
    {
        $host = $req->getHost();
        $org = $data['organization'] ?? '(none)';
        $ua = substr((string) $req->userAgent(), 0, 200);
        return <<<TXT
A new OpenRiC API key request has been submitted.

  ID:            #{$id}
  Email:         {$data['email']}
  Organization:  {$org}
  Requested:     {$scopes}
  IP:            {$ip}
  User-Agent:    {$ua}

Intended use:
{$data['intended_use']}

To approve + issue the key, run:

  php artisan openric:issue-key {$id}

To deny:

  php artisan openric:issue-key {$id} --deny --note="reason"

Host: {$host}
TXT;
    }

    private static function renderForm(string $serverHost, string $adminEmail, ?string $status, string $msg): string
    {
        $host = htmlspecialchars($serverHost, ENT_QUOTES);
        $adminEsc = htmlspecialchars($adminEmail, ENT_QUOTES);
        $banner = '';
        if ($status === 'submitted') {
            $banner = '<div class="banner ok">✓ Request received. An admin will review and email you with the key if approved. Typically within 1 business day.</div>';
        } elseif ($status === 'error') {
            $banner = '<div class="banner err">' . htmlspecialchars($msg, ENT_QUOTES) . '</div>';
        }

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Request an OpenRiC API key · {$host}</title>
  <style>
    :root { --fg: #e5e7eb; --muted: #9ca3af; --bg: #111827; --panel: #1f2937; --border: #334155; --accent: #3b82f6; --ok: #10b981; --err: #ef4444; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--fg); line-height: 1.5; }
    header { background: #0f172a; border-bottom: 1px solid var(--border); padding: 0.7rem 1.2rem; display: flex; align-items: center; gap: 0.8rem; }
    header h1 { margin: 0; font-size: 1rem; font-weight: 600; }
    header a { color: #60a5fa; font-size: 0.85rem; text-decoration: none; }
    header a:hover { text-decoration: underline; }
    header .right { margin-left: auto; display: flex; gap: 1rem; }
    main { max-width: 720px; margin: 0 auto; padding: 2rem 1.2rem 4rem; }
    h2 { margin-top: 0; font-size: 1.4rem; }
    p { color: #d1d5db; }
    code { background: #0f172a; padding: 0.1rem 0.35rem; border-radius: 3px; font-size: 0.88rem; color: #fbbf24; }
    .banner { padding: 0.7rem 1rem; border-radius: 6px; margin-bottom: 1.4rem; font-size: 0.9rem; }
    .banner.ok  { background: rgba(16,185,129,0.15); border: 1px solid rgba(16,185,129,0.4); color: #6ee7b7; }
    .banner.err { background: rgba(239,68,68,0.15);  border: 1px solid rgba(239,68,68,0.4);  color: #fca5a5; }
    form { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 1.5rem; }
    .field { margin-bottom: 1rem; }
    .field label { display: block; font-weight: 600; margin-bottom: 0.3rem; font-size: 0.92rem; }
    .field label .req { color: var(--err); }
    .field label small { color: var(--muted); font-weight: 400; margin-left: 0.5rem; }
    .field input, .field textarea, .field select {
      width: 100%; background: var(--bg); color: var(--fg);
      border: 1px solid var(--border); padding: 0.5rem 0.65rem;
      border-radius: 4px; font-size: 0.92rem; font-family: inherit;
    }
    .field textarea { min-height: 110px; resize: vertical; }
    .field .hint { font-size: 0.8rem; color: var(--muted); margin-top: 0.3rem; }
    .submit {
      background: var(--accent); color: white; border: 1px solid var(--accent);
      padding: 0.6rem 1.4rem; border-radius: 4px; font-weight: 600;
      font-size: 0.95rem; cursor: pointer;
    }
    .submit:hover { background: #2563eb; }
    .faq { margin-top: 2rem; font-size: 0.88rem; color: #cbd5e1; }
    .faq h3 { margin-bottom: 0.3rem; font-size: 0.95rem; color: #f1f5f9; }
    .faq p { margin-top: 0.2rem; }
    .faq a { color: #60a5fa; }
  </style>
</head>
<body>
  <header>
    <h1><a href="https://openric.org" style="color:#e5e7eb;">OpenRiC</a> · Request an API key
      <span style="color:#9ca3af; font-weight: 400; margin-left: 0.5rem; font-size: 0.85rem;">for <code>{$host}</code></span></h1>
    <div class="right">
      <a href="/api/ric/v1/docs" target="_blank" rel="noopener">API Explorer ↗</a>
      <a href="https://openric.org/" target="_blank" rel="noopener">Spec ↗</a>
    </div>
  </header>

  <main>
    {$banner}

    <h2>Request an API key</h2>
    <p>Reads on <code>/api/ric/v1/*</code> are public — no key required. You only need a key to <strong>create</strong>, <strong>update</strong>, or <strong>delete</strong> entities.</p>
    <p>Fill in the form below. An admin will review and email you the key if approved, typically within one business day.</p>

    <form method="post" action="/api/ric/v1/keys/request" id="key-form">
      <div class="field">
        <label>Email <span class="req">*</span> <small>the key will be emailed here</small></label>
        <input name="email" type="email" required placeholder="you@example.org" />
      </div>
      <div class="field">
        <label>Organization <small>optional, institution or project name</small></label>
        <input name="organization" placeholder="National Archives of …" />
      </div>
      <div class="field">
        <label>Intended use <span class="req">*</span> <small>what will you use the key for? 20+ chars</small></label>
        <textarea name="intended_use" required minlength="20" placeholder="e.g. Importing a collection of ~5,000 digitised photos with their ISAD descriptions, one-off migration from a retired system."></textarea>
      </div>
      <div class="field">
        <label>Requested scopes</label>
        <select name="scopes">
          <option value="read,write">read + write (create + update)</option>
          <option value="read,write,delete" selected>read + write + delete</option>
          <option value="read">read only (same as no key — for testing auth)</option>
        </select>
        <div class="hint">Keys can always be revoked later by the admin if abused. Rate limit: 1000 req/hour by default.</div>
      </div>
      <button type="submit" class="submit">Submit request</button>
    </form>

    <section class="faq">
      <h3>What happens next?</h3>
      <p>Your request goes to <code>{$adminEsc}</code>. The admin runs <code>php artisan openric:issue-key &lt;id&gt;</code> which generates a key and emails it to you. You can then test it against any write endpoint in the <a href="/api/ric/v1/docs">API Explorer</a>.</p>

      <h3>I need a key right now for a demo</h3>
      <p>Email the admin directly and mention the submission ID you receive on this page. For one-off evaluations the <a href="https://capture.openric.org">capture tool</a> can be pointed at your own server if you run one — see the <a href="https://openric.org/guides/getting-started.html">getting-started guide</a>.</p>

      <h3>Can I run my own OpenRiC server?</h3>
      <p>Yes — that's the whole point of the spec. Every OpenRiC-conformant server ships this exact page at <code>/api/ric/v1/keys/request</code>. The reference implementation is AGPL-3.0.</p>
    </section>
  </main>

  <script>
    // Submit asynchronously so we handle the JSON redirect from the server.
    document.getElementById('key-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const btn = form.querySelector('.submit');
      btn.disabled = true; btn.textContent = 'Submitting…';
      try {
        const fd = new FormData(form);
        const body = Object.fromEntries(fd.entries());
        const resp = await fetch(form.action, {
          method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(body),
        });
        const json = await resp.json();
        if (resp.ok && json.request_id) {
          window.location = '/api/ric/v1/keys/request?status=submitted&id=' + json.request_id;
        } else if (json.redirect) {
          window.location = json.redirect;
        } else {
          const msg = json.message || json.error || 'Unknown error';
          window.location = '/api/ric/v1/keys/request?status=error&msg=' + encodeURIComponent(msg);
        }
      } catch (err) {
        window.location = '/api/ric/v1/keys/request?status=error&msg=' + encodeURIComponent(err.message);
      }
    });
  </script>
</body>
</html>
HTML;
    }
}
