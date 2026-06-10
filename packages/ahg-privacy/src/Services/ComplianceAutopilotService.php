<?php

/**
 * ComplianceAutopilotService - Heratio ahg-privacy
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgPrivacy\Services;

use Illuminate\Support\Facades\DB;

/**
 * heratio#1199 - compliance autopilot. Scans the catalogue for personal data (via the
 * existing PiiScanService), aggregates the categories found, and auto-drafts a Records of
 * Processing Activities (Article 30 / ROPA) entry pre-filled with those categories, for a
 * data-protection officer to review and save. Jurisdiction-neutral; the scanner is
 * market-pluggable (POPIA / GDPR / etc.) per its own configuration.
 */
class ComplianceAutopilotService
{
    private const LABELS = [
        'email' => 'Email addresses',
        'phone' => 'Phone numbers',
        'ip' => 'IP addresses',
        'national_id' => 'National identifiers',
        'credit_card' => 'Payment card numbers',
        'dob' => 'Dates of birth',
    ];

    /** Scan up to $limit catalogue descriptions for PII and aggregate by category. */
    public function scanCatalogue(int $limit = 300): array
    {
        $scanner = app(PiiScanService::class);
        $rows = DB::table('information_object_i18n')
            ->where('culture', 'en')
            ->where(function ($q) { $q->whereNotNull('title')->orWhereNotNull('scope_and_content'); })
            ->select('id', 'title', 'scope_and_content')
            ->limit($limit)->get();

        $agg = [];
        $withPii = 0;
        $scanned = 0;
        foreach ($rows as $r) {
            $scanned++;
            $text = trim(strip_tags((string) $r->title.' '.(string) $r->scope_and_content));
            if ($text === '') {
                continue;
            }
            $findings = $scanner->scan($text);
            if (! $findings) {
                continue;
            }
            $withPii++;
            $seen = [];
            foreach ($findings as $f) {
                $t = $f['type'] ?? 'other';
                $agg[$t] = $agg[$t] ?? ['type' => $t, 'count' => 0, 'records' => 0, 'samples' => []];
                $agg[$t]['count']++;
                if (! isset($seen[$t])) {
                    $seen[$t] = true;
                    $agg[$t]['records']++;
                    if (count($agg[$t]['samples']) < 5) {
                        $agg[$t]['samples'][] = (int) $r->id;
                    }
                }
            }
        }

        $cats = array_values($agg);
        usort($cats, fn ($a, $b) => $b['count'] <=> $a['count']);
        foreach ($cats as &$c) {
            $c['label'] = self::LABELS[$c['type']] ?? ucfirst(str_replace('_', ' ', $c['type']));
        }

        return ['scanned' => $scanned, 'records_with_pii' => $withPii, 'categories' => $cats];
    }

    /** Build a ROPA (Article 30) draft from a scan result. Not saved - for review. */
    public function draftRopa(array $scan): array
    {
        $cats = array_map(fn ($c) => $c['label'], $scan['categories'] ?? []);

        return [
            'name' => 'Archival description and access (auto-drafted)',
            'purpose' => 'Cataloguing, preservation and provision of access to archival and collection records, some of which contain personal data.',
            'lawful_basis' => 'Public task / archiving in the public interest (review against the applicable regime).',
            'categories_of_data' => $cats ?: ['Personal data within records'],
            'categories_of_subjects' => ['Individuals named or identifiable in the records (creators, correspondents, subjects of records)'],
            'recipients' => ['Researchers and the public, subject to access controls and redaction'],
            'retention_period' => 'Permanent retention as archival records, per the appraisal/retention policy.',
            'security_measures' => 'Access controls, field-level PII redaction on restricted records, audit logging.',
            'transfers_outside_eea' => false,
            'safeguards' => '',
            'is_active' => true,
        ];
    }
}
