<?php

/**
 * HarrisMatrixService - stratigraphic analysis and interchange.
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd
 *
 * This file is part of Heratio. Licensed under the GNU AGPL v3.
 */

namespace AhgHarrisMatrix\Services;

use AhgArchaeology\Services\ArchaeologyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Harris Matrix as an analytical object, rather than as a diagram - #1483.
 *
 * Ported from ahgArchaeologyPlugin in atom-ahg-plugins. The matrix itself
 * (tiers, edges, cycle detection, same_as union-find) already lives in
 * ahg-archaeology's ArchaeologyService and is used from here rather than
 * reimplemented - two implementations of one graph is how they drift.
 */
class HarrisMatrixService
{
    /**
     * Context types that are INTERFACES in Harris's sense, not deposits.
     *
     * Harris divides stratigraphic units into deposits and interfaces, and a cut
     * is an interface - it is the surface left by an act of removal, not a body
     * of material. Everything else recorded here (fill, layer, masonry,
     * skeleton, structure, surface) is a deposit for this purpose.
     *
     * Matched on the term NAME rather than a term id: a taxonomy term created by
     * the seeder gets whatever id the instance hands out, and an id baked in
     * here would be right on exactly one installation.
     */
    public const INTERFACE_TYPES = ['Cut', 'Interface'];

    public function __construct(private readonly ArchaeologyService $archaeology)
    {
    }

    /** Whether the base module's tables are present. */
    public function available(): bool
    {
        return Schema::hasTable('archaeology_context')
            && Schema::hasTable('archaeology_context_relationship');
    }

    /**
     * Is this context an interface (a cut) rather than a deposit?
     *
     * @param  object  $context  a row from archaeology_context, with type_name
     *                           joined in where available
     */
    public function isInterface(object $context): bool
    {
        $type = trim((string) ($context->type_name ?? $context->context_type ?? ''));

        return $type !== '' && in_array($type, self::INTERFACE_TYPES, true);
    }

    /** 'interface' or 'deposit' - the unit-type a Data Package records. */
    public function unitType(object $context): string
    {
        return $this->isInterface($context) ? 'interface' : 'deposit';
    }

    /**
     * Check the recorded stratigraphy for contradictions.
     *
     * Cycle detection alone only catches the one error that makes a matrix
     * impossible to draw. These are the errors that leave a DRAWABLE matrix
     * which happens to be wrong - the kind Le Stratifiant checks.
     *
     * Every check is CONSERVATIVE: it reports only what the recorded data makes
     * unambiguous. A report that cried wolf on ordinary excavation messiness
     * would be turned off within a week, and then it would catch nothing at all.
     * That is why the elevation check looks only at `above`, and why the phase
     * check ignores contexts with no phase.
     *
     * @return array{findings: array<int,array{severity:string,kind:string,message:string}>, checked: array<int,string>}
     */
    public function consistencyReport(int $siteId): array
    {
        $findings = [];
        $checked = [];

        $contexts = $this->archaeology->contextsForSite($siteId);
        if ($contexts->isEmpty()) {
            return ['findings' => [], 'checked' => []];
        }

        $byId = [];
        $numberOf = [];
        foreach ($contexts as $context) {
            $byId[(int) $context->id] = $context;
            $numberOf[(int) $context->id] = (string) $context->context_number;
        }

        $rels = $this->relationshipsForSite($siteId, array_keys($byId));

        // --- loops -----------------------------------------------------------
        $checked[] = 'stratigraphic loops';
        $matrix = $this->archaeology->harrisMatrix($siteId);
        if (! empty($matrix['has_cycle'])) {
            $findings[] = [
                'severity' => 'error',
                'kind' => 'cycle',
                'message' => 'The sequence contains a loop, so it cannot be ordered. '
                    . 'The matrix cannot be drawn until the contradicting relationships are corrected.',
            ];
        }

        // --- contexts with nothing recorded about them -----------------------
        $checked[] = 'contexts with no relationships';
        $related = [];
        foreach ($rels as $rel) {
            $related[(int) $rel->context_id] = true;
            $related[(int) $rel->related_context_id] = true;
        }
        $isolated = [];
        foreach ($byId as $id => $context) {
            if (! isset($related[$id])) {
                $isolated[] = $numberOf[$id];
            }
        }
        if ($isolated) {
            sort($isolated);
            $findings[] = [
                'severity' => 'warning',
                'kind' => 'isolated',
                'message' => sprintf(
                    '%d context%s no recorded relationship, so %s outside the sequence entirely: %s.',
                    count($isolated),
                    count($isolated) === 1 ? ' has' : 's have',
                    count($isolated) === 1 ? 'it sits' : 'they sit',
                    implode(', ', array_slice($isolated, 0, 12)) . (count($isolated) > 12 ? ', ...' : '')
                ),
            ];
        }

        // --- disconnected pieces ---------------------------------------------
        // Treated as UNDIRECTED: the question is whether the record ties the dig
        // together at all, not which way round any one relationship runs.
        $checked[] = 'sequence split into unconnected pieces';
        $adjacency = [];
        foreach ($rels as $rel) {
            $a = (int) $rel->context_id;
            $b = (int) $rel->related_context_id;
            $adjacency[$a][] = $b;
            $adjacency[$b][] = $a;
        }
        $seen = [];
        $components = 0;
        foreach (array_keys($byId) as $id) {
            if (isset($seen[$id]) || ! isset($adjacency[$id])) {
                continue;
            }
            $components++;
            $stack = [$id];
            while ($stack) {
                $node = array_pop($stack);
                if (isset($seen[$node])) {
                    continue;
                }
                $seen[$node] = true;
                foreach ($adjacency[$node] ?? [] as $next) {
                    if (! isset($seen[$next])) {
                        $stack[] = $next;
                    }
                }
            }
        }
        if ($components > 1) {
            $findings[] = [
                'severity' => 'warning',
                'kind' => 'disconnected',
                'message' => sprintf(
                    'The recorded sequence falls into %d unconnected pieces. That is normal for separate '
                        . 'trenches and a problem within one, so it is worth confirming which this is.',
                    $components
                ),
            ];
        }

        // --- correlated AND superposed ---------------------------------------
        // same_as says two contexts are one unit. above/below says one is later
        // than the other. Both at once cannot be true.
        $checked[] = 'contexts both correlated and superposed';
        $sameAs = [];
        $superposed = [];
        foreach ($rels as $rel) {
            $pair = $this->pairKey((int) $rel->context_id, (int) $rel->related_context_id);
            if ($rel->relationship_type === 'same_as') {
                $sameAs[$pair] = true;
            } elseif (in_array($rel->relationship_type, ['above', 'below'], true)) {
                $superposed[$pair] = true;
            }
        }
        $both = array_keys(array_intersect_key($sameAs, $superposed));
        foreach ($both as $pair) {
            [$a, $b] = explode(':', $pair);
            $findings[] = [
                'severity' => 'error',
                'kind' => 'same_as_superposed',
                'message' => sprintf(
                    'Contexts %s and %s are recorded as the same unit AND as one above the other. '
                        . 'Both cannot be true.',
                    $numberOf[(int) $a] ?? $a,
                    $numberOf[(int) $b] ?? $b
                ),
            ];
        }

        // --- elevations against superposition --------------------------------
        // Only `above` is checked, and only where BOTH elevations are recorded.
        // A context that is above another should not start below it. Anything
        // less clear-cut is excavation messiness and is left alone.
        $checked[] = 'elevations against superposition (above only)';
        foreach ($rels as $rel) {
            if ($rel->relationship_type !== 'above') {
                continue;
            }
            $upper = $byId[(int) $rel->context_id] ?? null;
            $lower = $byId[(int) $rel->related_context_id] ?? null;
            if (! $upper || ! $lower) {
                continue;
            }
            if ($upper->top_elevation_m === null || $lower->top_elevation_m === null) {
                continue;
            }
            if ((float) $upper->top_elevation_m < (float) $lower->top_elevation_m) {
                $findings[] = [
                    'severity' => 'warning',
                    'kind' => 'elevation',
                    'message' => sprintf(
                        'Context %s is recorded above %s, but its top elevation (%s) is lower than %s (%s). '
                            . 'One of the two records is wrong.',
                        $upper->context_number, $lower->context_number,
                        $upper->top_elevation_m, $lower->context_number, $lower->top_elevation_m
                    ),
                ];
            }
        }

        // --- phases against superposition ------------------------------------
        // Contexts with no phase are ignored: an unphased context is not a
        // contradiction, it is simply not yet interpreted.
        $checked[] = 'phases against superposition';
        foreach ($rels as $rel) {
            if ($rel->relationship_type !== 'above') {
                continue;
            }
            $upper = $byId[(int) $rel->context_id] ?? null;
            $lower = $byId[(int) $rel->related_context_id] ?? null;
            if (! $upper || ! $lower) {
                continue;
            }
            $up = trim((string) ($upper->phase_name ?? ''));
            $lo = trim((string) ($lower->phase_name ?? ''));
            if ($up === '' || $lo === '' || $up === $lo) {
                continue;
            }
            // Only flag where the phase labels sort the wrong way round AND both
            // are numeric-leading, which is the only case the data makes
            // unambiguous. "Phase 3" above "Phase 5" is worth a look.
            if (preg_match('/^\D*(\d+)/', $up, $mu) && preg_match('/^\D*(\d+)/', $lo, $ml)) {
                if ((int) $mu[1] > (int) $ml[1]) {
                    $findings[] = [
                        'severity' => 'warning',
                        'kind' => 'phase',
                        'message' => sprintf(
                            'Context %s (%s) is recorded above %s (%s). A later context above an earlier one '
                                . 'is expected; the phase numbers run the other way.',
                            $upper->context_number, $up, $lower->context_number, $lo
                        ),
                    ];
                }
            }
        }

        return ['findings' => $findings, 'checked' => $checked];
    }

    /** Relationships between contexts of one site. */
    public function relationshipsForSite(int $siteId, ?array $contextIds = null): \Illuminate\Support\Collection
    {
        if (! $this->available()) {
            return collect();
        }

        $ids = $contextIds ?? $this->archaeology->contextsForSite($siteId)->pluck('id')->all();
        if (! $ids) {
            return collect();
        }

        return DB::table('archaeology_context_relationship')
            ->whereIn('context_id', $ids)
            ->whereIn('related_context_id', $ids)
            ->get(['context_id', 'related_context_id', 'relationship_type']);
    }

    /** Order-independent key for a pair of context ids. */
    private function pairKey(int $a, int $b): string
    {
        return $a <= $b ? "$a:$b" : "$b:$a";
    }

    // ── Interchange ─────────────────────────────────────────────────────

    /**
     * Parse an LST file - the format BASP Harris, Stratify and ArchEd write.
     *
     * Structure: the first three lines are ignored, the first unit name is on
     * line four, and every unit name is followed by exactly FOUR relationship
     * lines, in this order, each a comma-separated list that may be empty:
     *
     *   above, contemporary_with, equal_to, below
     *
     * All four lines are always present, so the parser advances in blocks of
     * five rather than trying to guess which line it is looking at.
     *
     * `contemporary_with` is collected and REPORTED rather than imported.
     * Heratio records same_as (one unit recorded twice) and superposition;
     * "contemporary with" is a chronological claim about two distinct units and
     * is not the same statement. Importing it as same_as would merge contexts
     * that are not the same context.
     *
     * @return array{rows: array<int,array{source:string,type:string,target:string,line:int}>, error: ?string, contemporary: array<int,array{0:string,1:string}>, units: array<int,string>}
     */
    public function parseLst(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $contents) ?: [];
        $rows = [];
        $units = [];
        $contemporary = [];

        if (count($lines) < 4) {
            return ['rows' => [], 'error' => 'The file has fewer than four lines, so it carries no units.',
                'contemporary' => [], 'units' => []];
        }

        for ($i = 3; $i < count($lines); $i += 5) {
            $name = trim($lines[$i] ?? '');
            if ($name === '') {
                break;   // trailing blank lines end the file
            }
            if (! isset($lines[$i + 4])) {
                return ['rows' => [], 'units' => $units, 'contemporary' => $contemporary,
                    'error' => sprintf(
                        'Unit "%s" on line %d is not followed by four relationship lines. '
                            . 'The file is truncated or is not an LST.',
                        $name, $i + 1
                    )];
            }

            $units[] = $name;
            $above = $this->lstList($lines[$i + 1]);
            $contemp = $this->lstList($lines[$i + 2]);
            $equalTo = $this->lstList($lines[$i + 3]);
            $below = $this->lstList($lines[$i + 4]);

            foreach ($above as $other) {
                $rows[] = ['source' => $name, 'type' => 'above', 'target' => $other, 'line' => $i + 2];
            }
            foreach ($equalTo as $other) {
                $rows[] = ['source' => $name, 'type' => 'same_as', 'target' => $other, 'line' => $i + 4];
            }
            foreach ($below as $other) {
                $rows[] = ['source' => $name, 'type' => 'below', 'target' => $other, 'line' => $i + 5];
            }
            foreach ($contemp as $other) {
                $contemporary[] = [$name, $other];
            }
        }

        return ['rows' => $rows, 'error' => null, 'contemporary' => $contemporary, 'units' => $units];
    }

    /** One LST relationship line into a list of unit names. */
    private function lstList(?string $line): array
    {
        $line = trim((string) $line);
        if ($line === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $line)),
            static fn ($v) => $v !== ''
        ));
    }

    /**
     * Export the sequence as GraphViz DOT.
     *
     * The DRAWN edges, so what comes out is the reduced matrix rather than
     * every relationship recorded - the same diagram the Stratigraphy page
     * shows. Exporting the full edge set would produce a correct but unreadable
     * graph, which is the thing a Harris Matrix exists to avoid.
     */
    public function exportDot(int $siteId): string
    {
        $matrix = $this->archaeology->harrisMatrix($siteId);
        $contexts = $this->archaeology->contextsForSite($siteId)->keyBy('id');

        $out = ["digraph HarrisMatrix {", '    rankdir=TB;', '    node [shape=box, fontname="Helvetica"];', ''];

        foreach ($contexts as $c) {
            // Interfaces are drawn differently because they ARE different: a cut
            // is a surface, not a body of material.
            $shape = $this->isInterface($c) ? 'shape=box, style=dashed' : 'shape=box';
            $out[] = sprintf('    "%s" [%s];', $this->dotEscape((string) $c->context_number), $shape);
        }

        $out[] = '';
        // harrisMatrix() returns edges as a MAP keyed "fromId|toId" => type, not
        // a list of pairs. Read the key.
        foreach (array_keys($matrix['edges'] ?? []) as $key) {
            [$fromId, $toId] = array_pad(explode('|', (string) $key), 2, null);
            $from = $contexts[(int) $fromId]->context_number ?? null;
            $to = $contexts[(int) $toId]->context_number ?? null;
            if ($from === null || $to === null) {
                continue;
            }
            $out[] = sprintf('    "%s" -> "%s";', $this->dotEscape((string) $from), $this->dotEscape((string) $to));
        }

        $out[] = '}';

        return implode("\n", $out) . "\n";
    }

    private function dotEscape(string $v): string
    {
        return str_replace('"', '\\"', $v);
    }

    /**
     * Export a site as a Harris Matrix Data Package.
     *
     * Follows the table schema Thomas Dye defined for the `hm` package, which
     * the Harris Matrix Data Package specification builds on:
     *
     *   contexts      label, unit-type, position, period, phase, url
     *   observations  younger, older, url
     *
     * `observations` carries NO relation-type column - it records superposition
     * and nothing else, so the relation types reduce to later-than pairs and the
     * rest is not expressible. That is the FORMAT'S design, not a loss on our
     * side: cuts and fills are both statements about superposition once you are
     * in this schema. Do not "fix" it by adding a column.
     *
     * @return array{contexts: array<int,array<string,mixed>>, observations: array<int,array<string,mixed>>}
     */
    public function exportDataPackage(int $siteId): array
    {
        $contexts = $this->archaeology->contextsForSite($siteId);
        $byId = $contexts->keyBy('id');

        $rows = [];
        foreach ($contexts as $c) {
            $rows[] = [
                'label' => (string) $c->context_number,
                'unit-type' => $this->unitType($c),
                'position' => $c->top_elevation_m,
                'period' => null,          // recorded per context as a term, not a period column
                'phase' => $c->phase_name,
                'url' => null,
            ];
        }

        $observations = [];
        foreach ($this->relationshipsForSite($siteId, $byId->keys()->all()) as $rel) {
            // Reduce to later-than pairs. `above` means the source is younger.
            if ($rel->relationship_type === 'above') {
                $younger = $byId[$rel->context_id] ?? null;
                $older = $byId[$rel->related_context_id] ?? null;
            } elseif ($rel->relationship_type === 'below') {
                $younger = $byId[$rel->related_context_id] ?? null;
                $older = $byId[$rel->context_id] ?? null;
            } else {
                continue;   // same_as and the rest are not expressible here
            }
            if (! $younger || ! $older) {
                continue;
            }
            $observations[] = [
                'younger' => (string) $younger->context_number,
                'older' => (string) $older->context_number,
                'url' => null,
            ];
        }

        // The relationship table stores reciprocal pairs, so every superposition
        // appears twice; the package wants each observation once.
        $observations = array_values(array_map(
            static fn ($k) => ['younger' => explode("\x00", $k)[0], 'older' => explode("\x00", $k)[1], 'url' => null],
            array_unique(array_map(static fn ($o) => $o['younger'] . "\x00" . $o['older'], $observations))
        ));

        return ['contexts' => $rows, 'observations' => $observations];
    }
    // ── Relationship interchange (CSV) ──────────────────────────────────

    /**
     * Spellings an excavator may reasonably write, mapped onto REL_TYPES.
     *
     * Normalised before lookup by folding underscores and hyphens to spaces and
     * collapsing runs of whitespace, so "cut by", "cut_by" and "Cut-By" are one
     * thing and each variant does not need its own entry.
     */
    public const RELATIONSHIP_SYNONYMS = [
        'later' => 'above',
        'later than' => 'above',
        'over' => 'above',
        'overlies' => 'above',
        'earlier' => 'below',
        'earlier than' => 'below',
        'under' => 'below',
        'underlies' => 'below',
        'cut by' => 'cut_by',
        'filled by' => 'filled_by',
        'same as' => 'same_as',
        'equal' => 'same_as',
        'equal to' => 'same_as',
        'equals' => 'same_as',
        'correlates with' => 'same_as',
        'bonds with' => 'bonds_with',
        'butts' => 'abuts',
        'abuts against' => 'abuts',
    ];

    /**
     * Relations we refuse to guess at, with the reason shown to the operator.
     *
     * `contemporary_with` is the same judgement parseLst() already makes: it is a
     * chronological claim about two DISTINCT units, while same_as means one unit
     * recorded twice and bonds_with/abuts both assert physical contact. None of
     * them is what "contemporary" says, so it is reported rather than mapped.
     */
    public const RELATIONSHIP_UNSUPPORTED = [
        'contemporary' => 'contemporary_with has no equivalent here - same_as means one unit recorded twice, and bonds_with and abuts both assert physical contact, which "contemporary" does not claim',
        'contemporary with' => 'contemporary_with has no equivalent here - same_as means one unit recorded twice, and bonds_with and abuts both assert physical contact, which "contemporary" does not claim',
    ];

    /**
     * Parse CSV text into header-keyed rows.
     *
     * Takes the CONTENTS rather than a path, matching parseLst() - the caller
     * has already read the upload and nothing here should care where it came
     * from. Strips a UTF-8 BOM off the first header cell, which Excel writes and
     * which would otherwise make the first column name unmatchable.
     *
     * @return array{rows: array<int,array<string,string>>, error: ?string}
     */
    public function parseCsv(string $contents, string $required = ''): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);
        if ($header === false || $header === null || $header === [null]) {
            fclose($handle);

            return ['rows' => [], 'error' => 'The file is empty.'];
        }

        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);

        if ($required !== '' && ! in_array($required, $header, true)) {
            fclose($handle);

            return ['rows' => [], 'error' => "The file has no {$required} column."];
        }

        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if (count($line) === 1 && ($line[0] === null || trim((string) $line[0]) === '')) {
                continue;   // blank line, not a row
            }
            $row = [];
            foreach ($header as $i => $name) {
                $row[$name] = isset($line[$i]) ? trim((string) $line[$i]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['rows' => $rows, 'error' => null];
    }

    /**
     * Parse PHASER's four-column relationship CSV into import rows.
     *
     * Columns: siteCode, sourceID, stratRelationship, targetID.
     *
     * siteCode is READ but never used to choose the site. The operator already
     * picked the site; silently importing into whatever a file names would be a
     * good way to write another dig's stratigraphy into this one. Rows naming a
     * different site are counted per code and reported, so a file covering
     * several sites says so instead of appearing to import cleanly.
     *
     * @return array{rows: array<int,array{source:string,type:string,target:string,line:int}>, error: ?string, other_sites: array<string,int>}
     */
    public function parsePhaserCsv(string $contents, ?string $expectedSiteCode = null): array
    {
        $parsed = $this->parseCsv($contents, 'sourceid');
        if ($parsed['error'] !== null) {
            return ['rows' => [], 'error' => $parsed['error'], 'other_sites' => []];
        }

        $rows = [];
        $otherSites = [];

        foreach ($parsed['rows'] as $i => $row) {
            $code = trim((string) ($row['sitecode'] ?? ''));

            if ($expectedSiteCode !== null && $code !== '' && strcasecmp($code, $expectedSiteCode) !== 0) {
                $otherSites[$code] = ($otherSites[$code] ?? 0) + 1;

                continue;
            }

            $rows[] = [
                'source' => (string) ($row['sourceid'] ?? ''),
                'type' => (string) ($row['stratrelationship'] ?? ''),
                'target' => (string) ($row['targetid'] ?? ''),
                'line' => $i + 2,   // +2: one for the header, one for 1-based lines
            ];
        }

        return ['rows' => $rows, 'error' => null, 'other_sites' => $otherSites];
    }

    /**
     * A site's stratigraphy as PHASER four-column CSV.
     *
     * ONE ROW PER LOGICAL RELATIONSHIP. archaeology_context_relationship stores
     * both directions of every relationship, so emitting every stored row would
     * hand a consumer twice the statements the excavator recorded - "A above B"
     * and "B below A" are one observation written twice, not two observations.
     *
     * For directional types the later-than direction is kept, because that is the
     * one carrying the sequence; its reciprocal says the same thing backwards.
     * Symmetric types (same_as, bonds_with, abuts) have no direction to prefer,
     * so they are canonicalised on the sorted pair instead.
     */
    public function exportPhaserCsv(int $siteId): string
    {
        $site = $this->archaeology->site($siteId);
        $contexts = $this->archaeology->contextsForSite($siteId);
        $byId = $contexts->keyBy('id')->map(fn ($c) => (string) $c->context_number);

        $siteCode = (string) ($site->site_number ?? '');
        $out = [['siteCode', 'sourceID', 'stratRelationship', 'targetID']];
        $seen = [];

        foreach ($this->relationshipsForSite($siteId, $contexts->pluck('id')->all()) as $rel) {
            $source = $byId->get((int) $rel->context_id);
            $target = $byId->get((int) $rel->related_context_id);
            if ($source === null || $target === null) {
                continue;
            }

            $type = (string) $rel->relationship_type;
            $direction = ArchaeologyService::REL_TYPES[$type]['dir'] ?? 'none';

            if ($direction === 'earlier') {
                continue;   // its reciprocal carries the same observation
            }

            $key = $direction === 'none'
                ? $type.'|'.(strcmp($source, $target) <= 0 ? $source.'|'.$target : $target.'|'.$source)
                : $source.'|'.$type.'|'.$target;

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = [$siteCode, $source, $type, $target];
        }

        return $this->toCsv($out);
    }

    /** A ready-to-fill relationship CSV: the PHASER header and one worked row. */
    public function relationshipCsvTemplate(): string
    {
        return $this->toCsv([
            ['siteCode', 'sourceID', 'stratRelationship', 'targetID'],
            ['BBF-2026', '1002', 'above', '1005'],
        ]);
    }

    /**
     * Resolve a written relationship name to a REL_TYPES key.
     *
     * @return array{0: ?string, 1: ?string} [type, reason-it-was-refused]
     */
    private function relationshipType(string $raw): array
    {
        $key = strtolower(trim($raw));
        if ($key === '') {
            return [null, 'no relationship given'];
        }

        $key = preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $key));

        if (isset(self::RELATIONSHIP_UNSUPPORTED[$key])) {
            return [null, self::RELATIONSHIP_UNSUPPORTED[$key]];
        }

        $type = self::RELATIONSHIP_SYNONYMS[$key] ?? str_replace(' ', '_', $key);

        return isset(ArchaeologyService::REL_TYPES[$type])
            ? [$type, null]
            : [null, "unknown relationship '{$raw}'"];
    }

    /**
     * Apply parsed relationship rows to a site.
     *
     * Every row goes through ArchaeologyService::addRelationship(), so
     * reciprocity, the self-relation check and the CYCLE GUARD apply exactly as
     * they do to typed entry - an import cannot introduce a contradiction the
     * form would have refused.
     *
     * The whole run is wrapped in a transaction that is rolled back unless
     * $commit is true, so a preview reports real counts and real warnings from a
     * real run without writing anything. A relationship that is already recorded
     * counts as a duplicate, not a failure: re-importing the same file is safe
     * and should say so rather than look like an error.
     *
     * @param  array<int,array{source:string,type:string,target:string,line:int}>  $rows
     * @return array{added:int,duplicate:int,skipped:int,warnings:array<int,string>,committed:bool}
     */
    public function importRelationshipRows(int $siteId, array $rows, bool $commit): array
    {
        $result = ['added' => 0, 'duplicate' => 0, 'skipped' => 0, 'warnings' => [], 'committed' => false];

        if (! $this->available()) {
            $result['warnings'][] = 'The archaeology tables are not installed.';

            return $result;
        }

        $idByNumber = $this->archaeology->contextsForSite($siteId)
            ->mapWithKeys(fn ($c) => [(string) $c->context_number => (int) $c->id]);

        if ($idByNumber->isEmpty()) {
            $result['warnings'][] = 'This site has no contexts, so there is nothing to relate. Import the contexts first.';

            return $result;
        }

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $row) {
                $line = (int) ($row['line'] ?? $i + 2);
                $source = trim((string) ($row['source'] ?? ''));
                $target = trim((string) ($row['target'] ?? ''));
                $rawType = trim((string) ($row['type'] ?? ''));

                if ($source === '' || $target === '' || $rawType === '') {
                    $result['skipped']++;
                    $result['warnings'][] = "Line {$line}: source, relationship and target are all required.";

                    continue;
                }

                [$type, $reason] = $this->relationshipType($rawType);
                if ($type === null) {
                    $result['skipped']++;
                    $result['warnings'][] = "Line {$line}: {$reason}.";

                    continue;
                }

                if (! $idByNumber->has($source) || ! $idByNumber->has($target)) {
                    $missing = $idByNumber->has($source) ? $target : $source;
                    $result['skipped']++;
                    $result['warnings'][] = "Line {$line}: context '{$missing}' is not recorded on this site.";

                    continue;
                }

                $before = $this->relationshipExists($idByNumber->get($source), $idByNumber->get($target), $type);
                $outcome = $this->archaeology->addRelationship(
                    $idByNumber->get($source), $idByNumber->get($target), $type, 'Imported from CSV'
                );

                if (! empty($outcome['ok'])) {
                    $before ? $result['duplicate']++ : $result['added']++;

                    continue;
                }

                $result['skipped']++;
                $result['warnings'][] = "Line {$line}: {$source} {$rawType} {$target} - ".($outcome['error'] ?? 'refused');
            }

            if ($commit) {
                DB::commit();
                $result['committed'] = true;
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $result['warnings'][] = 'Import failed: '.$e->getMessage();
        }

        return $result;
    }

    /** Whether this exact directed edge is already recorded. */
    private function relationshipExists(int $contextId, int $relatedId, string $type): bool
    {
        return DB::table('archaeology_context_relationship')
            ->where('context_id', $contextId)
            ->where('related_context_id', $relatedId)
            ->where('relationship_type', $type)
            ->exists();
    }

    /** Rows to RFC 4180 CSV text. */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
