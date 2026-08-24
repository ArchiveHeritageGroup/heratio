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
}
