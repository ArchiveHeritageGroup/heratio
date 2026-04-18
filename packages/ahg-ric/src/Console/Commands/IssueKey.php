<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Approve a self-service API key request and email the key to the requester.
 *
 *   php artisan openric:issue-key <request-id>               # approve
 *   php artisan openric:issue-key <request-id> --deny        # deny
 *   php artisan openric:issue-key <request-id> --note="..."  # admin note on denial / approval
 */

namespace AhgRic\Console\Commands;

use AhgApi\Services\ApiKeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class IssueKey extends Command
{
    protected $signature = 'openric:issue-key
        {id : The openric_key_request row id}
        {--deny : Deny instead of approve}
        {--note= : Admin note stored on the request row (and emailed on denial)}
        {--rate-limit=1000 : Per-hour request cap for the issued key}
        {--expires= : Expiry date (Y-m-d); defaults to +365 days}';

    protected $description = 'Issue (or deny) a self-service API key request.';

    public function handle(): int
    {
        $id = (int) $this->argument('id');
        $req = DB::table('openric_key_request')->where('id', $id)->first();
        if (!$req) {
            $this->error("No key request with id {$id}.");
            return self::FAILURE;
        }
        if ($req->status !== 'pending') {
            $this->warn("Request #{$id} already {$req->status}.");
            if (!$this->confirm('Process again anyway?', false)) {
                return self::FAILURE;
            }
        }

        $note = (string) ($this->option('note') ?? '');

        if ($this->option('deny')) {
            return $this->deny($req, $note);
        }
        return $this->approve($req, $note);
    }

    private function deny(object $req, string $note): int
    {
        DB::table('openric_key_request')->where('id', $req->id)->update([
            'status'       => 'denied',
            'review_notes' => $note ?: null,
            'reviewed_at'  => now(),
        ]);
        $this->info("Request #{$req->id} denied.");

        try {
            $reason = $note ?: 'No reason provided.';
            $body   = "Your OpenRiC API key request has been denied.\n\nReason: {$reason}\n\nIf you think this is an error, reply to this email.";
            Mail::raw($body, function ($m) use ($req) {
                $m->to($req->email)->subject('[OpenRiC] Your API key request was denied');
            });
            $this->line("Emailed denial to {$req->email}.");
        } catch (\Throwable $e) {
            $this->warn('Email failed (denial recorded anyway): ' . $e->getMessage());
        }
        return self::SUCCESS;
    }

    private function approve(object $req, string $note): int
    {
        $scopes = array_values(array_filter(array_map('trim', explode(',', $req->requested_scopes))));
        $rateLimit = (int) $this->option('rate-limit');
        $expires = $this->option('expires')
            ? date('Y-m-d H:i:s', strtotime($this->option('expires')))
            : now()->addDays(365)->toDateTimeString();

        // Ensure there's a user row the key can anchor on. Use a synthetic
        // "openric-external" user id 0-fallback strategy: if user_id is
        // required non-null, we stash the admin user's id.
        $ownerUserId = (int) (DB::table('ahg_api_key')->min('user_id') ?: 1);
        $name = "OpenRiC external · {$req->email}";

        $service = new ApiKeyService();
        $result = $service->createKey(
            userId: $ownerUserId,
            name: $name,
            scopes: $scopes,
            rateLimit: $rateLimit,
            expiresAt: $expires
        );

        DB::table('openric_key_request')->where('id', $req->id)->update([
            'status'       => 'approved',
            'api_key_id'   => $result['id'],
            'review_notes' => $note ?: null,
            'reviewed_at'  => now(),
        ]);

        $this->info("Request #{$req->id} approved. Issued key id {$result['id']} (prefix {$result['prefix']}, scopes: " . implode(',', $scopes) . ", expires {$expires}).");

        try {
            $html = $this->requesterEmailHtml($result['api_key'], $scopes, $expires, $req);
            $text = $this->requesterEmailBody($result['api_key'], $scopes, $expires, $req);
            Mail::html($html, function ($m) use ($req, $text) {
                $m->to($req->email)
                  ->subject('[OpenRiC] Your API key has been issued')
                  ->text($text);  // multipart/alternative plain-text fallback
            });
            $this->line("Emailed key to {$req->email}.");
        } catch (\Throwable $e) {
            $this->warn('Email failed: ' . $e->getMessage());
            $this->warn('Deliver this key out-of-band to ' . $req->email . ':');
            $this->line('');
            $this->line('  ' . $result['api_key']);
            $this->line('');
        }
        return self::SUCCESS;
    }

    private function requesterEmailHtml(string $rawKey, array $scopes, string $expires, object $req): string
    {
        $scopesStr = htmlspecialchars(implode(', ', $scopes), ENT_QUOTES);
        $base = rtrim((string) config('app.url', 'https://ric.theahg.co.za'), '/') . '/api/ric/v1';
        $baseEsc = htmlspecialchars($base, ENT_QUOTES);
        $keyEsc = htmlspecialchars($rawKey, ENT_QUOTES);
        $expiresEsc = htmlspecialchars($expires, ENT_QUOTES);
        $emailEsc = htmlspecialchars($req->email, ENT_QUOTES);
        return <<<HTML
<!doctype html>
<html lang="en">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr><td style="background:#0f172a;padding:18px 24px;">
          <span style="color:#e5e7eb;font-size:18px;font-weight:600;">OpenRiC</span>
          <span style="color:#9ca3af;font-size:14px;margin-left:8px;">reference API</span>
        </td></tr>
        <tr><td style="padding:28px 32px 8px;">
          <h1 style="margin:0 0 12px;font-size:22px;font-weight:600;">Your API key has been issued</h1>
          <p style="margin:0 0 16px;line-height:1.5;color:#374151;">
            Request approved for <strong>{$emailEsc}</strong>. Keep this key safe — treat it like a password.
          </p>
        </td></tr>
        <tr><td style="padding:0 32px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f172a;border-radius:6px;padding:14px 18px;">
            <tr><td style="color:#e5e7eb;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:13px;word-break:break-all;">
              {$keyEsc}
            </td></tr>
          </table>
          <p style="margin:14px 0 0;font-size:13px;color:#6b7280;">
            <strong>Scopes:</strong> {$scopesStr} &nbsp;·&nbsp;
            <strong>Expires:</strong> {$expiresEsc}
          </p>
        </td></tr>
        <tr><td style="padding:28px 32px 8px;">
          <h2 style="margin:0 0 8px;font-size:16px;font-weight:600;">Quick start</h2>
          <p style="margin:0 0 8px;line-height:1.5;color:#374151;">Send the key in the <code style="background:#f3f4f6;padding:1px 5px;border-radius:3px;font-size:13px;">X-API-Key</code> header:</p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;border-radius:6px;padding:12px 14px;margin-top:6px;">
            <tr><td style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;color:#1f2937;white-space:pre;overflow-x:auto;">curl -H "X-API-Key: {$keyEsc}" \\
     -H "Content-Type: application/json" \\
     -d '{"title":"My first record"}' \\
     {$baseEsc}/records</td></tr>
          </table>
        </td></tr>
        <tr><td style="padding:20px 32px;">
          <table role="presentation" cellpadding="0" cellspacing="0"><tr>
            <td style="padding-right:10px;">
              <a href="{$baseEsc}/docs" style="background:#3b82f6;color:#ffffff;text-decoration:none;padding:9px 18px;border-radius:6px;font-weight:600;font-size:14px;display:inline-block;">Open API Explorer</a>
            </td>
            <td style="padding-right:10px;">
              <a href="https://openric.org/guides/getting-started.html" style="color:#3b82f6;text-decoration:none;font-size:14px;padding:9px 0;display:inline-block;">Getting-started guide →</a>
            </td>
          </tr></table>
        </td></tr>
        <tr><td style="padding:20px 32px 28px;border-top:1px solid #e5e7eb;">
          <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.5;">
            If this key leaks, reply to this email and we'll revoke it.
            Documentation at <a href="https://openric.org/" style="color:#3b82f6;text-decoration:none;">openric.org</a>.
            OpenRiC is AGPL-3.0; the spec itself is CC-BY 4.0.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    private function requesterEmailBody(string $rawKey, array $scopes, string $expires, object $req): string
    {
        $scopesStr = implode(', ', $scopes);
        $base = config('app.url', 'https://ric.theahg.co.za') . '/api/ric/v1';
        return <<<TXT
Your OpenRiC API key request has been approved.

Key:     {$rawKey}
Scopes:  {$scopesStr}
Expires: {$expires}

Send it in the `X-API-Key` header. Example:

  curl -H "X-API-Key: {$rawKey}" \\
       -H "Content-Type: application/json" \\
       -d '{"title":"My first record"}' \\
       {$base}/records

Or try it interactively at {$base}/docs — paste this key into the
Authorize dialog.

Treat this key like a password — store it in a secret manager, don't
commit it to a repo. If it leaks, reply and we'll revoke it.

Documentation: https://openric.org/guides/getting-started.html

Thanks for trying OpenRiC.
TXT;
    }
}
