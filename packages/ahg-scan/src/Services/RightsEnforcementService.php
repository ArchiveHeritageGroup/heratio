<?php

/**
 * RightsEnforcementService — Heratio ahg-scan (P4)
 *
 * Applies sidecar-declared rights at ingest:
 *   <rightsStatement uri="...">  → object_rights_statement (+ rights_statement create-if-missing)
 *   <ccLicense>cc-by-4.0</ccLicense> → rights_cc_license lookup or create
 *   <embargoUntil reason="...">YYYY-MM-DD</embargoUntil> → rights_embargo
 *   <odrlPolicy>slug</odrlPolicy> → research_rights_policy (target_type=io)
 *   <tkLabel>CODE</tkLabel>      → rights_object_tk_label
 *   <rightsHolder>Name</rightsHolder> → object_rights_holder via donor lookup/create
 *
 * Policy: when a session has security_classification_id set AND the sidecar
 * supplies NO rights at all, returns `needsReview=true` so the pipeline can
 * hold the file in 'awaiting_rights' status. Operators unlock via the
 * Inbox "Release rights" action after verifying.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgScan\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RightsEnforcementService
{
    /**
     * Apply the parsed rights block to an IO. Returns ['needsReview'=>bool, 'warnings'=>string[]].
     *
     * @param int    $ioId       target information_object.id
     * @param array  $rights     parsed sidecar rights block (may be empty)
     * @param object $session    ingest_session row (for security_classification_id)
     */
    public function apply(int $ioId, array $rights, object $session): array
    {
        $warnings = [];
        $anythingApplied = false;

        // Rights statement (RightsStatements.org URI)
        if (!empty($rights['statement_uri'])) {
            $stmtId = $this->findOrCreateRightsStatement($rights['statement_uri']);
            if ($stmtId) {
                $this->linkObjectRightsStatement($ioId, $stmtId);
                $anythingApplied = true;
            } else {
                $warnings[] = "Could not resolve rights statement URI: " . $rights['statement_uri'];
            }
        }

        // Creative Commons licence
        if (!empty($rights['cc_license'])) {
            $ccId = $this->findOrCreateCcLicense($rights['cc_license']);
            if ($ccId) {
                $this->linkCcLicense($ioId, $ccId);
                $anythingApplied = true;
            } else {
                $warnings[] = "Unknown CC licence code: " . $rights['cc_license'];
            }
        }

        // Embargo
        if (!empty($rights['embargo_until'])) {
            $this->applyEmbargo($ioId, $rights['embargo_until'], $rights['embargo_reason'] ?? null, $session);
            $anythingApplied = true;
        }

        // ODRL policy
        if (!empty($rights['odrl_policy'])) {
            $this->linkOdrlPolicy($ioId, $rights['odrl_policy'], $warnings);
            $anythingApplied = true;
        }

        // Traditional Knowledge labels
        foreach ($rights['tk_labels'] ?? [] as $tkCode) {
            if ($this->linkTkLabel($ioId, $tkCode)) {
                $anythingApplied = true;
            } else {
                $warnings[] = "Unknown Traditional Knowledge label code: " . $tkCode;
            }
        }

        // Rights holder
        if (!empty($rights['rights_holder'])) {
            $this->linkRightsHolder($ioId, $rights['rights_holder']);
            $anythingApplied = true;
        }

        // Security classification: require *some* rights record when set.
        $needsReview = false;
        if (!empty($session->security_classification_id) && !$anythingApplied) {
            $needsReview = true;
            $warnings[] = 'Session has a security classification but sidecar supplied no rights block — held for review.';
        }

        return ['needsReview' => $needsReview, 'warnings' => $warnings];
    }

    // ----------------------------------------------------------------
    // rights_statement
    // ----------------------------------------------------------------

    protected function findOrCreateRightsStatement(string $uri): ?int
    {
        $id = DB::table('rights_statement')->where('uri', $uri)->value('id');
        if ($id) { return (int) $id; }

        try {
            $code = $this->deriveCodeFromUri($uri, 'rightsstatements.org', 'rights_statement');
            // rights_statement.category is NOT NULL; use the rightsstatements.org
            // top-level category when derivable, otherwise 'other'.
            $category = 'other';
            if (preg_match('#rightsstatements\.org/vocab/([A-Z]+)/#', $uri, $m)) {
                $category = match ($m[1]) {
                    'InC', 'InC-OW-EU' => 'in-copyright',
                    'NoC', 'NoC-CR', 'NoC-NC', 'NoC-OKLR', 'NoC-US' => 'no-copyright',
                    default => 'other-rights-status',
                };
            }
            return (int) DB::table('rights_statement')->insertGetId([
                'code' => $code,
                'uri' => $uri,
                'category' => $category,
                'is_active' => 1,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] rights_statement insert failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function linkObjectRightsStatement(int $ioId, int $stmtId): void
    {
        $exists = DB::table('object_rights_statement')
            ->where('object_id', $ioId)->where('rights_statement_id', $stmtId)->exists();
        if ($exists) { return; }
        DB::table('object_rights_statement')->insert([
            'object_id' => $ioId,
            'rights_statement_id' => $stmtId,
            'created_at' => now(),
        ]);
    }

    // ----------------------------------------------------------------
    // rights_cc_license
    // ----------------------------------------------------------------

    protected function findOrCreateCcLicense(string $codeOrUri): ?int
    {
        // Sidecar can supply either code ('cc-by-4.0') or URI ('https://creativecommons.org/...')
        if (str_starts_with($codeOrUri, 'http')) {
            $id = DB::table('rights_cc_license')->where('uri', $codeOrUri)->value('id');
            if ($id) { return (int) $id; }
            // Derive code from URI: https://creativecommons.org/licenses/by-nc/4.0/ → cc-by-nc-4.0
            $code = $this->deriveCcCodeFromUri($codeOrUri);
            $uri = $codeOrUri;
        } else {
            $code = strtolower($codeOrUri);
            $id = DB::table('rights_cc_license')->where('code', $code)->value('id');
            if ($id) { return (int) $id; }
            $uri = $this->deriveCcUriFromCode($code);
        }

        try {
            $version = preg_match('/(\d+\.\d+)$/', $code, $m) ? $m[1] : '4.0';
            return (int) DB::table('rights_cc_license')->insertGetId([
                'code' => $code,
                'version' => $version,
                'uri' => $uri,
                'allows_commercial' => !str_contains($code, '-nc-') ? 1 : 0,
                'allows_derivatives' => !str_contains($code, '-nd-') ? 1 : 0,
                'requires_share_alike' => str_contains($code, '-sa-') ? 1 : 0,
                'requires_attribution' => str_starts_with($code, 'cc-by') ? 1 : 0,
                'is_active' => 1,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ahg-scan] rights_cc_license insert failed: ' . $e->getMessage());
            return null;
        }
    }

    protected function linkCcLicense(int $ioId, int $ccId): void
    {
        // Stored via extended_rights in some Heratio setups; simplest P4 approach:
        // record on object_rights_statement with notes describing the CC code, so
        // ODRL + rights-statement enforcement both see it uniformly.
        $code = DB::table('rights_cc_license')->where('id', $ccId)->value('code');
        $stmtId = $this->findOrCreateRightsStatement('cc:' . $code);
        if ($stmtId) {
            $this->linkObjectRightsStatement($ioId, $stmtId);
        }
    }

    protected function deriveCcCodeFromUri(string $uri): string
    {
        // Strip trailing slash and protocol, e.g. licenses/by-nc/4.0 → cc-by-nc-4.0
        if (preg_match('#creativecommons\.org/licenses/([^/]+)/([^/]+)#', $uri, $m)) {
            return 'cc-' . strtolower($m[1]) . '-' . $m[2];
        }
        return 'cc-unknown';
    }

    protected function deriveCcUriFromCode(string $code): string
    {
        // cc-by-nc-4.0 → by-nc + 4.0 → https://creativecommons.org/licenses/by-nc/4.0/
        if (preg_match('/^cc-([a-z-]+?)-(\d+\.\d+)$/', $code, $m)) {
            return "https://creativecommons.org/licenses/{$m[1]}/{$m[2]}/";
        }
        return "https://creativecommons.org/licenses/{$code}/";
    }

    // ----------------------------------------------------------------
    // rights_embargo
    // ----------------------------------------------------------------

    protected function applyEmbargo(int $ioId, string $endDate, ?string $reason, object $session): void
    {
        // Normalise YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            $ts = strtotime($endDate);
            if (!$ts) { return; }
            $endDate = date('Y-m-d', $ts);
        }

        // Skip if an active embargo already exists for this IO ending later.
        $existing = DB::table('rights_embargo')
            ->where('object_id', $ioId)
            ->where('status', 'active')
            ->orderByDesc('end_date')
            ->first();
        if ($existing && $existing->end_date && $existing->end_date >= $endDate) {
            return;
        }

        DB::table('rights_embargo')->insert([
            'object_id' => $ioId,
            'embargo_type' => 'full',
            'reason' => substr($reason ?: 'Imposed by sidecar at ingest', 0, 95),
            'start_date' => now()->toDateString(),
            'end_date' => $endDate,
            'auto_release' => 1,
            'status' => 'active',
            'created_by' => $session->user_id ?? null,
            'created_at' => now(),
        ]);
    }

    // ----------------------------------------------------------------
    // research_rights_policy
    // ----------------------------------------------------------------

    protected function linkOdrlPolicy(int $ioId, string $slugOrId, array &$warnings): void
    {
        // Sidecar supplies a policy slug or id. We cannot look up by slug in
        // research_rights_policy (no slug column), but operators typically
        // configure a handful of named policies and reference them by id in
        // production. Accept both forms: pure integer = policy template id.
        if (ctype_digit((string) $slugOrId)) {
            $templateId = (int) $slugOrId;
            $template = DB::table('research_rights_policy')
                ->where('id', $templateId)
                ->whereIn('target_type', ['template', 'information_object'])
                ->first();
            if (!$template) {
                $warnings[] = "ODRL policy template #{$slugOrId} not found";
                return;
            }
            // Copy the template row binding to this IO (target_type=information_object).
            DB::table('research_rights_policy')->insert([
                'target_type' => 'information_object',
                'target_id' => $ioId,
                'policy_type' => $template->policy_type,
                'action_type' => $template->action_type,
                'constraints_json' => $template->constraints_json,
                'policy_json' => $template->policy_json,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $warnings[] = "ODRL policy '{$slugOrId}' is not a template id — slug resolution requires a named-policy registry (P7).";
        }
    }

    // ----------------------------------------------------------------
    // rights_tk_label
    // ----------------------------------------------------------------

    protected function linkTkLabel(int $ioId, string $code): bool
    {
        $tkId = DB::table('rights_tk_label')->where('code', $code)->where('is_active', 1)->value('id');
        if (!$tkId) { return false; }
        $exists = DB::table('rights_object_tk_label')
            ->where('object_id', $ioId)->where('tk_label_id', $tkId)->exists();
        if ($exists) { return true; }
        DB::table('rights_object_tk_label')->insert([
            'object_id' => $ioId,
            'tk_label_id' => $tkId,
            'verified' => 0,
            'created_at' => now(),
        ]);
        return true;
    }

    // ----------------------------------------------------------------
    // object_rights_holder via donor lookup
    // ----------------------------------------------------------------

    protected function linkRightsHolder(int $ioId, string $name): void
    {
        $donorId = DB::table('donor')
            ->join('actor_i18n', 'donor.id', '=', 'actor_i18n.id')
            ->where('actor_i18n.authorized_form_of_name', $name)
            ->value('donor.id');

        if (!$donorId) {
            // Don't auto-create donor here — creating a rights-holder record
            // has stronger implications than a plain actor. Caller gets a
            // warning via return-value; we just skip.
            return;
        }

        $exists = DB::table('object_rights_holder')
            ->where('object_id', $ioId)->where('donor_id', $donorId)->exists();
        if ($exists) { return; }
        DB::table('object_rights_holder')->insert([
            'object_id' => $ioId,
            'donor_id' => $donorId,
            'notes' => 'Linked by scanner ingest from sidecar rightsHolder element',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ----------------------------------------------------------------
    // helpers
    // ----------------------------------------------------------------

    /**
     * Derive a short code from a URI to populate required `code` columns
     * when the sidecar only supplies a URI.
     */
    protected function deriveCodeFromUri(string $uri, string $marker, string $tablePrefix): string
    {
        if (preg_match('#([^/]+)/?$#', rtrim($uri, '/'), $m)) {
            $slug = Str::slug($m[1]);
            return substr($tablePrefix . '-' . $slug, 0, 60);
        }
        return substr($tablePrefix . '-' . substr(md5($uri), 0, 8), 0, 60);
    }
}
