<?php

/**
 * IptcFallbackResolver - resolve creator / rights / subject metadata for an
 * information object, falling back to extracted IPTC values from
 * `dam_iptc_metadata` when the canonical ISAD(G) source fields are empty.
 *
 * Background: the EXIF audit (issue #752) found that extracted IPTC data
 * (creator / copyright_notice / keywords) is written to `dam_iptc_metadata`
 * by the metadata-extraction pipeline but never read back by the OAI-PMH
 * endpoint, the Dublin Core export, or the EAD crosswalk. That means a
 * record with rich IPTC headers but an empty Author / Rights / Keywords
 * field in the ISAD(G) form harvests as a thin description even though
 * the underlying digital object carries the data.
 *
 * Field mapping (IPTC label - dam_iptc_metadata column - DC predicate):
 *   By-line             -> dam_iptc_metadata.creator          -> dc:creator
 *   Copyright Notice    -> dam_iptc_metadata.copyright_notice -> dc:rights
 *   Keywords            -> dam_iptc_metadata.keywords         -> dc:subject (one per term)
 *
 * Precedence: canonical ISAD(G) wins. The resolver only emits IPTC values
 * when the corresponding ISAD field is empty / whitespace. When the
 * fallback fires it writes an info-level row to `ahg_error_log` keyed by
 * object_id + field name so an operator can audit which descriptions
 * relied on IPTC and decide whether to promote those values into the
 * canonical fields permanently.
 *
 * The resolver is consumer-agnostic: the OAI controller, the Dublin Core
 * Qualified serializer, and the EAD 2002 / EAD 3 serializers all call
 * the same helpers so the fallback policy is defined in one place.
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
 */

namespace AhgMetadataExport\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class IptcFallbackResolver
{
    /**
     * Per-request cache so the dam_iptc_metadata table isn't re-fetched
     * for every predicate in the same record. Cleared automatically when
     * the worker dies, which is the normal request-lifecycle boundary.
     *
     * @var array<int, object|null>
     */
    private static array $iptcCache = [];

    /**
     * Per-request audit-dedup. We log only once per (object_id, field)
     * pair so a single ListRecords response doesn't fill ahg_error_log
     * with thousands of duplicate "fallback fired" rows when the same
     * record is re-rendered (e.g. ListRecords + a follow-up GetRecord
     * inside the same worker).
     *
     * @var array<string, true>
     */
    private static array $auditCache = [];

    /**
     * Whether ahg_error_log is reachable. Cached so we don't probe it on
     * every fallback hit. null = not yet probed, bool = result.
     */
    private static ?bool $errorLogAvailable = null;

    /**
     * Lookup (and cache) the dam_iptc_metadata row for an information
     * object. Returns null when the table is missing, no row exists, or
     * the lookup fails for any reason. Callers must defensive-null-check.
     */
    public function getIptc(int $objectId): ?object
    {
        if (array_key_exists($objectId, self::$iptcCache)) {
            return self::$iptcCache[$objectId];
        }

        try {
            if (! Schema::hasTable('dam_iptc_metadata')) {
                return self::$iptcCache[$objectId] = null;
            }
            $row = DB::table('dam_iptc_metadata')
                ->where('object_id', $objectId)
                ->select('creator', 'copyright_notice', 'keywords')
                ->first();

            return self::$iptcCache[$objectId] = ($row ?: null);
        } catch (Throwable $e) {
            return self::$iptcCache[$objectId] = null;
        }
    }

    /**
     * Return the list of creators that should be emitted for an IO.
     *
     * $canonical is the list of authoritative creator names already
     * resolved from the events table (ISAD(G) 3.2.1 name_access_points
     * author equivalent). When that list is empty AND dam_iptc_metadata
     * has a non-blank `creator` value, return a single-element list
     * containing the IPTC By-line. Otherwise return $canonical unchanged.
     */
    public function resolveCreatorsWithCanonical(int $objectId, array $canonical): array
    {
        $canonical = array_values(array_filter($canonical, static fn ($v) => is_string($v) && trim($v) !== ''));
        if (! empty($canonical)) {
            return $canonical;
        }
        $iptc = $this->getIptc($objectId);
        if ($iptc === null) {
            return [];
        }
        $byline = trim((string) ($iptc->creator ?? ''));
        if ($byline === '') {
            return [];
        }
        $this->audit($objectId, 'creator', $byline);

        return [$byline];
    }

    /**
     * Return the rights statement to emit for an IO. Falls back to the
     * IPTC Copyright Notice when $canonical (typically access_conditions
     * or a populated rights_statement) is blank.
     */
    public function resolveRightsWithCanonical(int $objectId, ?string $canonical): ?string
    {
        $canonical = trim((string) $canonical);
        if ($canonical !== '') {
            return $canonical;
        }
        $iptc = $this->getIptc($objectId);
        if ($iptc === null) {
            return null;
        }
        $rights = trim((string) ($iptc->copyright_notice ?? ''));
        if ($rights === '') {
            return null;
        }
        $this->audit($objectId, 'rights', $rights);

        return $rights;
    }

    /**
     * Return the subject keyword list to emit for an IO. Falls back to
     * the IPTC Keywords field, which may be either a JSON-encoded array
     * or a delimited string (comma / semicolon / newline). Malformed
     * input is silently skipped so a corrupt IPTC payload can't poison
     * the harvest.
     */
    public function resolveSubjectsWithCanonical(int $objectId, array $canonical): array
    {
        $canonical = array_values(array_filter(
            array_map(static fn ($v) => is_string($v) ? trim($v) : '', $canonical),
            static fn ($v) => $v !== ''
        ));
        if (! empty($canonical)) {
            return $canonical;
        }
        $iptc = $this->getIptc($objectId);
        if ($iptc === null) {
            return [];
        }
        $raw = (string) ($iptc->keywords ?? '');
        $keywords = $this->parseKeywords($raw);
        if (empty($keywords)) {
            return [];
        }
        $this->audit($objectId, 'subject', implode('; ', $keywords));

        return $keywords;
    }

    /**
     * Parse the IPTC keywords payload. The extraction pipeline writes
     * keywords as either a JSON array (preferred) or a delimited string
     * (legacy / hand-edited). We accept both and degrade to "ignored"
     * rather than emitting garbage if the value is malformed.
     *
     * @return array<int, string>
     */
    private function parseKeywords(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Try JSON first - ExifTool emits a real JSON array for
        // multi-valued IPTC Keywords.
        if ($raw[0] === '[' || $raw[0] === '"') {
            try {
                $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $flat = [];
                    array_walk_recursive($decoded, static function ($v) use (&$flat) {
                        if (is_string($v) && trim($v) !== '') {
                            $flat[] = trim($v);
                        }
                    });

                    return array_values(array_unique($flat));
                }
                if (is_string($decoded) && trim($decoded) !== '') {
                    return [trim($decoded)];
                }
            } catch (Throwable $e) {
                // Fall through to delimited parsing.
            }
        }

        // Delimited fallback: split on common separators IPTC tooling
        // emits (semicolon, comma, newline, pipe). Trim each, drop empty.
        $parts = preg_split('/[\r\n;,|]+/', $raw) ?: [];
        $clean = [];
        foreach ($parts as $part) {
            // Also strip stray JSON structural chars so a malformed
            // JSON-looking payload (e.g. "[broken json,,,;") that fell through
            // here doesn't emit tokens containing brackets/quotes.
            $part = trim($part, " \t\n\r\0\x0B[]{}\"");
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return array_values(array_unique($clean));
    }

    /**
     * Write an info-level audit row to ahg_error_log noting that an IPTC
     * value patched a missing ISAD field. Best-effort - if the audit
     * write fails (no table, no DB), the fallback still succeeds because
     * the audit is observability, not a correctness gate.
     */
    private function audit(int $objectId, string $field, string $value): void
    {
        $key = $objectId.':'.$field;
        if (isset(self::$auditCache[$key])) {
            return;
        }
        self::$auditCache[$key] = true;

        if (self::$errorLogAvailable === null) {
            try {
                self::$errorLogAvailable = Schema::hasTable('ahg_error_log');
            } catch (Throwable $e) {
                self::$errorLogAvailable = false;
            }
        }
        if (! self::$errorLogAvailable) {
            return;
        }

        $short = mb_substr($value, 0, 200);
        $message = sprintf(
            'IPTC fallback fired for information_object.id=%d field=%s value="%s"',
            $objectId,
            $field,
            $short
        );

        try {
            DB::table('ahg_error_log')->insert([
                'level' => 'info',
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable $e) {
            // Audit is best-effort; the export must not fail because the
            // log table is missing a column or is read-only mid-deploy.
        }
    }

    /**
     * Clear the per-request caches. Useful for tests that re-use the
     * resolver across multiple IOs in the same PHP process.
     */
    public static function resetCaches(): void
    {
        self::$iptcCache = [];
        self::$auditCache = [];
        self::$errorLogAvailable = null;
    }

    // -----------------------------------------------------------------------
    // Single-argument convenience methods (issue #752 brief surface).
    //
    // The plural / canonical-aware methods above are the workhorses called
    // from the OAI controller and the per-format serializers, which already
    // have the canonical ISAD(G) values in hand. The methods below fetch
    // the canonical field themselves so a consumer that only knows the IO
    // id can call a single helper.
    //
    // Precedence per the brief:
    //   resolveCreator  - information_object_i18n author equivalent? No.
    //                     Heratio stores creators in the `event` table
    //                     (type_id=111 = Creation). We query that, then
    //                     fall through to IPTC By-line.
    //   resolveRights   - information_object_i18n.reproduction_conditions
    //                     (ISAD 3.4.2), then IPTC Copyright Notice.
    //   resolveSubjects - subject access points (taxonomy 35), then IPTC
    //                     Keywords.
    // -----------------------------------------------------------------------

    /**
     * Resolve a single creator string for an IO. Returns the first ISAD
     * author (event type_id=111) when present, else the IPTC By-line, else
     * null. Single-argument shape per the issue #752 brief - the resolver
     * looks up the canonical value itself rather than having the caller
     * pass it in.
     */
    public function resolveCreator(int $objectId): ?string
    {
        $canonical = [];
        try {
            if (Schema::hasTable('event') && Schema::hasTable('actor_i18n')) {
                $rows = DB::table('event as e')
                    ->leftJoin('actor_i18n as ai', function ($join) {
                        $join->on('e.actor_id', '=', 'ai.id')
                            ->where('ai.culture', '=', 'en');
                    })
                    ->where('e.object_id', '=', $objectId)
                    ->where('e.type_id', '=', 111)
                    ->whereNotNull('e.actor_id')
                    ->select('ai.authorized_form_of_name')
                    ->get();
                foreach ($rows as $r) {
                    if (! empty($r->authorized_form_of_name)) {
                        $canonical[] = (string) $r->authorized_form_of_name;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall through to the IPTC fallback path.
        }
        $resolved = $this->resolveCreatorsWithCanonical($objectId, $canonical);

        return $resolved[0] ?? null;
    }

    /**
     * Resolve a single rights statement for an IO. Returns ISAD(G) 3.4.2
     * reproduction_conditions when set, else the IPTC Copyright Notice,
     * else null. Single-argument shape per the issue #752 brief.
     */
    public function resolveRights(int $objectId): ?string
    {
        $canonical = null;
        try {
            if (Schema::hasTable('information_object_i18n')) {
                $val = DB::table('information_object_i18n')
                    ->where('id', $objectId)
                    ->where('culture', 'en')
                    ->value('reproduction_conditions');
                if (! empty($val)) {
                    $canonical = strip_tags((string) $val);
                }
            }
        } catch (Throwable $e) {
            // Fall through to the IPTC fallback path.
        }

        return $this->resolveRightsWithCanonical($objectId, $canonical);
    }

    /**
     * Resolve the subject-keyword list for an IO. Returns ISAD(G) subject
     * access points (taxonomy 35) when any exist, else the IPTC Keywords
     * list, else []. Single-argument shape per the issue #752 brief.
     *
     * @return array<int, string>
     */
    public function resolveSubjects(int $objectId): array
    {
        $canonical = [];
        try {
            if (Schema::hasTable('object_term_relation') && Schema::hasTable('term_i18n')) {
                $rows = DB::table('object_term_relation as otr')
                    ->join('term as t', 'otr.term_id', '=', 't.id')
                    ->join('term_i18n as ti', function ($join) {
                        $join->on('t.id', '=', 'ti.id')
                            ->where('ti.culture', '=', 'en');
                    })
                    ->where('otr.object_id', '=', $objectId)
                    ->where('t.taxonomy_id', '=', 35)
                    ->select('ti.name')
                    ->get();
                foreach ($rows as $r) {
                    if (! empty($r->name)) {
                        $canonical[] = (string) $r->name;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall through to the IPTC fallback path.
        }

        return $this->resolveSubjectsWithCanonical($objectId, $canonical);
    }
}
