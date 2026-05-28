<?php

/**
 * OrcidService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgResearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * OrcidService
 *
 * ORCID OAuth + Works push/pull. Heratio acts as an ORCID Member/Public API
 * consumer: researchers connect their ORCID iD, pull their existing
 * publications list into their researcher profile, and push citations from
 * the archive back to their ORCID Works list.
 *
 * Config keys (ENV):
 *   ORCID_CLIENT_ID       - client id issued on orcid.org/developer-tools
 *   ORCID_CLIENT_SECRET   - client secret
 *   ORCID_REDIRECT_URI    - https://your-host/research/orcid/callback
 *   ORCID_BASE            - https://orcid.org (production) or sandbox.orcid.org
 *   ORCID_API_BASE        - https://pub.orcid.org (Public) or https://api.orcid.org (Member)
 *
 * If config is missing every entrypoint returns a clean "ORCID not
 * configured" error - never a 500.
 */
class OrcidService
{
    public function isConfigured(): bool
    {
        return (bool) $this->clientId() && (bool) $this->clientSecret() && (bool) $this->redirectUri();
    }

    public function authorizeUrl(?string $state = null): string
    {
        $state = $state ?: Str::random(40);
        session(['orcid_oauth_state' => $state]);

        return $this->baseUrl() . '/oauth/authorize?' . http_build_query([
            'client_id'     => $this->clientId(),
            'response_type' => 'code',
            'scope'         => '/authenticate /read-limited /activities/update',
            'redirect_uri'  => $this->redirectUri(),
            'state'         => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('ORCID not configured');
        }

        $resp = Http::asForm()->post($this->baseUrl() . '/oauth/token', [
            'client_id'     => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->redirectUri(),
        ]);

        if (!$resp->ok()) {
            throw new RuntimeException('ORCID token exchange failed: HTTP ' . $resp->status() . ' ' . $resp->body());
        }

        return $resp->json();
    }

    public function linkResearcher(int $researcherId, array $tokenResponse): void
    {
        $orcid = $tokenResponse['orcid'] ?? null;
        if (!$orcid) {
            throw new RuntimeException('ORCID response missing orcid id');
        }

        $expiresAt = isset($tokenResponse['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $tokenResponse['expires_in'])
            : null;

        DB::table('researcher_orcid_link')->updateOrInsert(
            ['researcher_id' => $researcherId],
            [
                'orcid_id'                => $orcid,
                'access_token_encrypted'  => $this->encrypt($tokenResponse['access_token']  ?? ''),
                'refresh_token_encrypted' => $this->encrypt($tokenResponse['refresh_token'] ?? ''),
                'scope'                   => $tokenResponse['scope']  ?? null,
                'expires_at'              => $expiresAt,
                'updated_at'              => date('Y-m-d H:i:s'),
                'created_at'              => date('Y-m-d H:i:s'),
            ]
        );

        // Mirror the orcid id onto the researcher profile column the
        // existing UI already shows (so it's visible without an ORCID query).
        try {
            DB::table('research_researcher')->where('id', $researcherId)->update(['orcid_id' => $orcid]);
        } catch (\Throwable $e) {
            // research_researcher.orcid_id may not exist on every install; non-fatal.
        }
    }

    public function unlink(int $researcherId): void
    {
        DB::table('researcher_orcid_link')->where('researcher_id', $researcherId)->delete();
        try {
            DB::table('research_researcher')->where('id', $researcherId)->update(['orcid_id' => null]);
        } catch (\Throwable $e) {}
    }

    public function getLink(int $researcherId): ?object
    {
        return DB::table('researcher_orcid_link')->where('researcher_id', $researcherId)->first();
    }

    /**
     * Pull ORCID Works (publications list). Returns the parsed array, plus
     * stores last_works_count + last_synced_at on the link row.
     */
    public function pullWorks(int $researcherId): array
    {
        $link = $this->getLink($researcherId);
        if (!$link) throw new RuntimeException('No ORCID link');

        $token = $this->decrypt($link->access_token_encrypted ?? '');
        $resp = Http::withToken($token)
            ->withHeaders(['Accept' => 'application/json'])
            ->get($this->apiBase() . '/v3.0/' . $link->orcid_id . '/works');

        if (!$resp->ok()) {
            $err = 'pullWorks HTTP ' . $resp->status();
            DB::table('researcher_orcid_link')->where('id', $link->id)->update([
                'last_error'  => $err,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
            throw new RuntimeException($err);
        }

        $data = $resp->json();
        $count = 0;
        foreach (($data['group'] ?? []) as $group) {
            $count += count($group['work-summary'] ?? []);
        }

        DB::table('researcher_orcid_link')->where('id', $link->id)->update([
            'last_synced_at'   => date('Y-m-d H:i:s'),
            'last_works_count' => $count,
            'last_error'       => null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        return $data;
    }

    /**
     * Push a single citation back to the researcher's ORCID Works list.
     * Returns the put-code (ORCID's id for the new work) on success.
     *
     * $citation expects keys: title, year, type (BOOK/JOURNAL_ARTICLE/etc),
     * external_id (DOI / URL), url, contributors (array of strings).
     */
    public function pushWork(int $researcherId, array $citation): ?string
    {
        $link = $this->getLink($researcherId);
        if (!$link) throw new RuntimeException('No ORCID link');

        $token = $this->decrypt($link->access_token_encrypted ?? '');
        $xml = $this->buildWorkXml($citation);

        $resp = Http::withToken($token)
            ->withHeaders([
                'Accept'       => 'application/vnd.orcid+xml',
                'Content-Type' => 'application/vnd.orcid+xml',
            ])
            ->withBody($xml, 'application/vnd.orcid+xml')
            ->post($this->apiBase() . '/v3.0/' . $link->orcid_id . '/work');

        if (!$resp->successful()) {
            Log::warning('[orcid] pushWork failed: ' . $resp->status() . ' ' . $resp->body());
            return null;
        }

        $location = $resp->header('Location');
        if (!$location) return null;
        return basename($location);
    }

    private function buildWorkXml(array $c): string
    {
        $title    = htmlspecialchars((string) ($c['title'] ?? 'Untitled'));
        $year     = (int) ($c['year'] ?? 0);
        $type     = htmlspecialchars((string) ($c['type'] ?? 'OTHER'));
        $extType  = htmlspecialchars((string) ($c['external_type'] ?? 'doi'));
        $extValue = htmlspecialchars((string) ($c['external_id']   ?? ($c['url'] ?? '')));
        $url      = htmlspecialchars((string) ($c['url'] ?? ''));

        $contribs = '';
        foreach (($c['contributors'] ?? []) as $name) {
            $contribs .= '<work:contributor>'
                . '<work:credit-name>' . htmlspecialchars($name) . '</work:credit-name>'
                . '</work:contributor>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<work:work xmlns:common="http://www.orcid.org/ns/common" xmlns:work="http://www.orcid.org/ns/work">'
            . '<work:title><common:title>' . $title . '</common:title></work:title>'
            . '<work:type>' . $type . '</work:type>'
            . ($year ? '<common:publication-date><common:year>' . $year . '</common:year></common:publication-date>' : '')
            . '<common:external-ids>'
            . '<common:external-id>'
            . '<common:external-id-type>' . $extType . '</common:external-id-type>'
            . '<common:external-id-value>' . $extValue . '</common:external-id-value>'
            . '<common:external-id-relationship>self</common:external-id-relationship>'
            . '</common:external-id>'
            . '</common:external-ids>'
            . ($url ? '<work:url>' . $url . '</work:url>' : '')
            . ($contribs ? '<work:contributors>' . $contribs . '</work:contributors>' : '')
            . '</work:work>';
    }

    // ─── Public-record read (no per-researcher OAuth) ───────────────────

    /**
     * Obtain a 2-legged client-credentials token scoped to /read-public.
     * Lets us read any public ORCID record without the researcher having
     * linked/authorised. Cached for ~19 days (ORCID tokens last 20y but we
     * re-fetch well within that). Returns null if not configured or on error.
     */
    public function publicReadToken(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        return \Illuminate\Support\Facades\Cache::remember('orcid_public_read_token', now()->addDays(19), function () {
            try {
                $resp = Http::asForm()
                    ->withHeaders(['Accept' => 'application/json'])
                    ->post($this->baseUrl() . '/oauth/token', [
                        'client_id'     => $this->clientId(),
                        'client_secret' => $this->clientSecret(),
                        'grant_type'    => 'client_credentials',
                        'scope'         => '/read-public',
                    ]);
                if (!$resp->ok()) {
                    Log::warning('ORCID public-read token failed: HTTP ' . $resp->status() . ' ' . $resp->body());
                    return null;
                }
                return $resp->json()['access_token'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('ORCID public-read token threw: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Normalise an ORCID iD: accept a bare 16-digit iD or a full URL, return
     * the canonical 0000-0000-0000-0000 form, or null if it doesn't validate.
     */
    public function normaliseOrcidId(?string $raw): ?string
    {
        if (!$raw) return null;
        if (preg_match('~(\d{4}-\d{4}-\d{4}-\d{3}[\dX])~i', trim($raw), $m)) {
            return strtoupper($m[1]);
        }
        return null;
    }

    /**
     * Fetch a researcher's PUBLIC ORCID record and parse it into the fields the
     * register / profile forms use. Returns null on bad iD or unreachable API.
     *
     * @return array{orcid_id:string, first_name:?string, last_name:?string, credit_name:?string, institution:?string, department:?string, position:?string, research_interests:?string, emails:array<string>}|null
     */
    public function fetchPublicRecord(string $orcidId): ?array
    {
        $orcidId = $this->normaliseOrcidId($orcidId);
        if (!$orcidId) return null;

        $token = $this->publicReadToken();
        if (!$token) return null;

        try {
            $resp = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($this->apiBase() . '/v3.0/' . $orcidId . '/record');
            if (!$resp->ok()) {
                Log::info("ORCID fetchPublicRecord {$orcidId}: HTTP " . $resp->status());
                return null;
            }
            $rec = $resp->json();
        } catch (\Throwable $e) {
            Log::warning('ORCID fetchPublicRecord threw: ' . $e->getMessage());
            return null;
        }

        $person = $rec['person'] ?? [];
        $name   = $person['name'] ?? [];
        $given  = $name['given-names']['value']  ?? null;
        $family = $name['family-name']['value']  ?? null;
        $credit = $name['credit-name']['value']  ?? null;

        // Keywords -> research interests (comma-joined).
        $keywords = [];
        foreach (($person['keywords']['keyword'] ?? []) as $kw) {
            if (!empty($kw['content'])) $keywords[] = $kw['content'];
        }

        // Public emails (visibility=public only ones appear in the public API).
        $emails = [];
        foreach (($person['emails']['email'] ?? []) as $em) {
            if (!empty($em['email'])) $emails[] = $em['email'];
        }

        // Most-recent employment for institution / department / role.
        $institution = $department = $position = null;
        $employments = $rec['activities-summary']['employments']['affiliation-group'] ?? [];
        foreach ($employments as $group) {
            $summary = $group['summaries'][0]['employment-summary'] ?? null;
            if (!$summary) continue;
            $institution = $summary['organization']['name'] ?? $institution;
            $department  = $summary['department-name'] ?? $department;
            $position    = $summary['role-title'] ?? $position;
            break; // first group is the most recent
        }

        return [
            'orcid_id'           => $orcidId,
            'first_name'         => $given,
            'last_name'          => $family,
            'credit_name'        => $credit,
            'institution'        => $institution,
            'department'         => $department,
            'position'           => $position,
            'research_interests' => $keywords ? implode(', ', $keywords) : null,
            'emails'             => $emails,
        ];
    }

    /**
     * Pull the linked researcher's profile from ORCID and apply the non-empty
     * fields onto their research_researcher row. Uses the public record (works
     * for both Public and Member API clients). Returns the parsed record.
     */
    public function pullProfile(int $researcherId): ?array
    {
        $link = $this->getLink($researcherId);
        $orcidId = $link->orcid_id
            ?? DB::table('research_researcher')->where('id', $researcherId)->value('orcid_id');
        if (!$orcidId) {
            throw new RuntimeException('Researcher has no ORCID iD to pull from');
        }

        $record = $this->fetchPublicRecord($orcidId);
        if (!$record) {
            return null;
        }

        // Only overwrite columns that exist + when ORCID has a value.
        $update = [];
        $map = [
            'first_name'         => $record['first_name'],
            'last_name'          => $record['last_name'],
            'institution'        => $record['institution'],
            'department'         => $record['department'],
            'position'           => $record['position'],
            'research_interests' => $record['research_interests'],
            'orcid_id'           => $record['orcid_id'],
        ];
        foreach ($map as $col => $val) {
            if ($val !== null && $val !== '') $update[$col] = $val;
        }
        if ($update) {
            try {
                DB::table('research_researcher')->where('id', $researcherId)->update($update);
            } catch (\Throwable $e) {
                Log::warning('ORCID pullProfile update failed: ' . $e->getMessage());
            }
        }

        if ($link) {
            $linkUpdate = ['updated_at' => date('Y-m-d H:i:s')];
            if (\Illuminate\Support\Facades\Schema::hasColumn('researcher_orcid_link', 'last_profile_synced_at')) {
                $linkUpdate['last_profile_synced_at'] = date('Y-m-d H:i:s');
            }
            DB::table('researcher_orcid_link')->where('id', $link->id)->update($linkUpdate);
        }

        return $record;
    }

    // ─── Config helpers ────────────────────────────────────────────────

    private function clientId(): ?string     { return env('ORCID_CLIENT_ID'); }
    private function clientSecret(): ?string { return env('ORCID_CLIENT_SECRET'); }
    private function redirectUri(): ?string  { return env('ORCID_REDIRECT_URI', url('/research/orcid/callback')); }
    private function baseUrl(): string       { return rtrim(env('ORCID_BASE', 'https://orcid.org'), '/'); }
    private function apiBase(): string       { return rtrim(env('ORCID_API_BASE', 'https://pub.orcid.org'), '/'); }

    private function encrypt(string $s): string
    {
        $key = $this->key();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $enc = openssl_encrypt($s, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($iv . $enc);
    }

    private function decrypt(string $s): string
    {
        if ($s === '') return '';
        $data = base64_decode($s);
        $ivLen = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($data, 0, $ivLen);
        $enc = substr($data, $ivLen);
        return (string) openssl_decrypt($enc, 'aes-256-cbc', $this->key(), 0, $iv);
    }

    private function key(): string
    {
        return hash('sha256', 'orcid_link_' . config('app.key', 'heratio'));
    }
}
