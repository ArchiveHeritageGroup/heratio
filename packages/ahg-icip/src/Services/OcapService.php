<?php

/**
 * OcapService - Service for Heratio
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

namespace AhgIcip\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OCAP overlay service.
 *
 * Maps the four OCAP principles to existing ICIP entities:
 *   - Ownership   → community link via icip_consent.community_id / icip_access_restriction.community_id
 *   - Control     → icip_consent (consent_status = granted | conditional | denied)
 *   - Access      → icip_access_restriction
 *   - Possession  → icip_object_summary.possession_assertion (community | repository | shared | null)
 *
 * No data-model changes are required beyond the two columns added by the service provider.
 * OCAP is pluggable per market and disabled until icip_config.ocap_enabled = '1'.
 */
class OcapService
{
    public const STATUS_GREEN  = 'green';
    public const STATUS_AMBER  = 'amber';
    public const STATUS_RED    = 'red';
    public const STATUS_NA     = 'n/a';

    public const PRINCIPLES = [
        'ownership',
        'control',
        'access',
        'possession',
    ];

    public function isEnabled(): bool
    {
        try {
            if (!Schema::hasTable('icip_config')) return false;
            $val = DB::table('icip_config')->where('config_key', 'ocap_enabled')->value('config_value');
            return (string)$val === '1';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Per-IO compliance assessment. Returns one of GREEN/AMBER/RED/NA per principle plus an overall flag.
     *
     * @return array{ownership:string, control:string, access:string, possession:string, overall:string, reasons:array<string,string>}
     */
    public function assessForIO(int $ioId): array
    {
        $reasons = [];

        // Ownership — is there at least one community linked via consent or restriction?
        $ownership = self::STATUS_NA;
        if (Schema::hasTable('icip_consent')) {
            $hasOwnerLink = DB::table('icip_consent')
                ->where('information_object_id', $ioId)
                ->whereNotNull('community_id')
                ->exists();
            $ownership = $hasOwnerLink ? self::STATUS_GREEN : self::STATUS_AMBER;
            if (!$hasOwnerLink) {
                $reasons['ownership'] = 'No community linked to this record.';
            }
        }

        // Control — consent status
        $control = self::STATUS_NA;
        if (Schema::hasTable('icip_consent')) {
            $latest = DB::table('icip_consent')
                ->where('information_object_id', $ioId)
                ->orderByDesc('id')
                ->select('consent_status', 'consent_expiry_date')
                ->first();
            if (!$latest) {
                $control = self::STATUS_RED;
                $reasons['control'] = 'No consent record on file.';
            } elseif ($latest->consent_expiry_date && $latest->consent_expiry_date < now()->toDateString()) {
                $control = self::STATUS_RED;
                $reasons['control'] = 'Consent has expired (' . $latest->consent_expiry_date . ').';
            } else {
                $control = match (strtolower($latest->consent_status ?? '')) {
                    'granted'      => self::STATUS_GREEN,
                    'conditional'  => self::STATUS_AMBER,
                    'denied', 'pending' => self::STATUS_RED,
                    default        => self::STATUS_AMBER,
                };
                if ($control !== self::STATUS_GREEN) {
                    $reasons['control'] = 'Consent status: ' . ($latest->consent_status ?: 'unknown') . '.';
                }
            }
        }

        // Access — at least one restriction defined OR consent granted with no restrictions
        $access = self::STATUS_NA;
        if (Schema::hasTable('icip_access_restriction')) {
            $hasRestrictions = DB::table('icip_access_restriction')
                ->where('information_object_id', $ioId)
                ->exists();
            // Green when access is governed (either via explicit restrictions or via granted consent).
            if ($hasRestrictions || $control === self::STATUS_GREEN) {
                $access = self::STATUS_GREEN;
            } else {
                $access = self::STATUS_AMBER;
                $reasons['access'] = 'Access not explicitly governed (no restriction, no granted consent).';
            }
        }

        // Possession
        $possession = self::STATUS_NA;
        if (Schema::hasTable('icip_object_summary') && Schema::hasColumn('icip_object_summary', 'possession_assertion')) {
            $assertion = DB::table('icip_object_summary')
                ->where('information_object_id', $ioId)
                ->value('possession_assertion');
            if (in_array($assertion, ['community', 'repository', 'shared'], true)) {
                $possession = self::STATUS_GREEN;
            } else {
                $possession = self::STATUS_AMBER;
                $reasons['possession'] = 'Possession not asserted (community / repository / shared).';
            }
        }

        $statuses = compact('ownership', 'control', 'access', 'possession');
        $overall = self::worstOf(array_values($statuses));

        return $statuses + ['overall' => $overall, 'reasons' => $reasons];
    }

    /**
     * Roll-up across all IOs that have any ICIP signal. Caps at $limit rows.
     *
     * @return array<int, array{io_id:int, title:string, slug:?string, ownership:string, control:string, access:string, possession:string, overall:string}>
     */
    public function rollup(int $limit = 250): array
    {
        if (!Schema::hasTable('icip_object_summary')) return [];

        $rows = DB::table('icip_object_summary as s')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 's.information_object_id')->where('i.culture', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 's.information_object_id')
            ->where('s.has_icip_content', 1)
            ->select('s.information_object_id as io_id', 'i.title', 'slug.slug')
            ->limit($limit)
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $a = $this->assessForIO((int)$r->io_id);
            $out[] = [
                'io_id'      => (int)$r->io_id,
                'title'      => $r->title ?: '(untitled)',
                'slug'       => $r->slug,
                'ownership'  => $a['ownership'],
                'control'    => $a['control'],
                'access'     => $a['access'],
                'possession' => $a['possession'],
                'overall'    => $a['overall'],
            ];
        }
        return $out;
    }

    /**
     * Aggregate counts for the dashboard tiles.
     *
     * @return array{green:int, amber:int, red:int, na:int, total:int, by_principle:array<string,array<string,int>>}
     */
    public function aggregate(int $limit = 1000): array
    {
        $rollup = $this->rollup($limit);
        $tally = ['green' => 0, 'amber' => 0, 'red' => 0, 'n/a' => 0];
        $byPrinciple = [];
        foreach (self::PRINCIPLES as $p) {
            $byPrinciple[$p] = ['green' => 0, 'amber' => 0, 'red' => 0, 'n/a' => 0];
        }
        foreach ($rollup as $row) {
            $tally[$row['overall']] = ($tally[$row['overall']] ?? 0) + 1;
            foreach (self::PRINCIPLES as $p) {
                $byPrinciple[$p][$row[$p]] = ($byPrinciple[$p][$row[$p]] ?? 0) + 1;
            }
        }
        return [
            'green' => $tally['green'],
            'amber' => $tally['amber'],
            'red'   => $tally['red'],
            'na'    => $tally['n/a'],
            'total' => count($rollup),
            'by_principle' => $byPrinciple,
        ];
    }

    private static function worstOf(array $statuses): string
    {
        if (in_array(self::STATUS_RED, $statuses, true))   return self::STATUS_RED;
        if (in_array(self::STATUS_AMBER, $statuses, true)) return self::STATUS_AMBER;
        if (in_array(self::STATUS_GREEN, $statuses, true)) return self::STATUS_GREEN;
        return self::STATUS_NA;
    }
}
