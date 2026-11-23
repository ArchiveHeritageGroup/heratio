<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

/**
 * heratio#1495: turns a flat set of structured reference codes into a write
 * order. Pure: no database, no vendor knowledge, so any source with structured
 * references can use it.
 *
 * Parents come out before children, missing intermediate levels are synthesised,
 * and malformed, duplicate, self-referencing or cyclic sets are rejected whole.
 */
class ReferenceHierarchyEngine
{
    /**
     * @param  list<array{key:mixed,ref:string,parent?:?string}>  $records  an explicit parent overrides the one derived from the path
     * @return array{nodes:list<array{ref:string,parent:?string,key:mixed,synthesised:bool,depth:int}>,synthesised:list<string>}
     *
     * @throws HierarchyRejected
     */
    public function build(array $records, string $delimiter = '/'): array
    {
        if ($delimiter === '') {
            throw new \InvalidArgumentException('Reference delimiter must not be empty.');
        }

        $problems = [];
        $nodes = [];

        foreach ($records as $record) {
            $raw = (string) ($record['ref'] ?? '');
            $ref = $this->normalise($raw, $delimiter);
            if ($ref === null) {
                $problems[] = ['code' => 'malformed_reference', 'ref' => $raw, 'detail' => 'Empty reference or empty path segment.'];

                continue;
            }
            if (isset($nodes[$ref])) {
                $problems[] = ['code' => 'duplicate_reference', 'ref' => $ref, 'detail' => 'The same reference appears on more than one row.'];

                continue;
            }

            $parent = $this->parentOf($ref, $delimiter);
            $explicit = trim((string) ($record['parent'] ?? ''));
            if ($explicit !== '') {
                $parent = $this->normalise($explicit, $delimiter);
                if ($parent === null) {
                    $problems[] = ['code' => 'malformed_reference', 'ref' => $explicit, 'detail' => "Malformed parent reference on {$ref}."];

                    continue;
                }
                if ($parent === $ref) {
                    $problems[] = ['code' => 'self_reference', 'ref' => $ref, 'detail' => 'The record names itself as parent.'];

                    continue;
                }
            }

            $nodes[$ref] = ['ref' => $ref, 'parent' => $parent, 'key' => $record['key'] ?? null, 'synthesised' => false];
        }

        if ($problems !== []) {
            throw new HierarchyRejected($problems);
        }

        // Synthesise missing levels. A synthesised parent is always a strict
        // path prefix, so this terminates.
        $synthesised = [];
        foreach (array_keys($nodes) as $ref) {
            $parent = $nodes[$ref]['parent'];
            while ($parent !== null && ! isset($nodes[$parent])) {
                $next = $this->parentOf($parent, $delimiter);
                $nodes[$parent] = ['ref' => $parent, 'parent' => $next, 'key' => null, 'synthesised' => true];
                $synthesised[] = $parent;
                $parent = $next;
            }
        }

        $children = [];
        $roots = [];
        foreach ($nodes as $ref => $node) {
            if ($node['parent'] === null) {
                $roots[] = (string) $ref;
            } else {
                $children[$node['parent']][] = (string) $ref;
            }
        }

        // Iterative pre-order, siblings in natural order (1, 2, 10).
        $ordered = [];
        $natural = static fn ($a, $b) => strnatcmp($a, $b);
        usort($roots, $natural);
        $stack = array_map(fn ($r) => [$r, 0], array_reverse($roots));
        while ($stack !== []) {
            [$ref, $depth] = array_pop($stack);
            $ordered[] = $nodes[$ref] + ['depth' => $depth];
            $kids = $children[$ref] ?? [];
            usort($kids, $natural);
            foreach (array_reverse($kids) as $kid) {
                $stack[] = [$kid, $depth + 1];
            }
        }

        if (count($ordered) !== count($nodes)) {
            $reached = array_flip(array_column($ordered, 'ref'));
            $unreached = array_diff_key($nodes, $reached);
            throw new HierarchyRejected($this->describeCycles($unreached));
        }

        sort($synthesised, SORT_NATURAL);

        return ['nodes' => $ordered, 'synthesised' => $synthesised];
    }

    /** Canonical form: segments trimmed, inner whitespace collapsed. Null when malformed. */
    public function normalise(string $ref, string $delimiter = '/'): ?string
    {
        $segments = [];
        foreach (explode($delimiter, trim($ref)) as $segment) {
            $segment = trim((string) preg_replace('/\s+/u', ' ', $segment));
            if ($segment === '') {
                return null;
            }
            $segments[] = $segment;
        }

        return implode($delimiter, $segments);
    }

    private function parentOf(string $ref, string $delimiter): ?string
    {
        $pos = strrpos($ref, $delimiter);

        return $pos === false ? null : substr($ref, 0, $pos);
    }

    /** Every unreached node sits on, or hangs below, a cycle. Report each cycle once. */
    private function describeCycles(array $unreached): array
    {
        $problems = [];
        $reported = [];
        foreach (array_keys($unreached) as $start) {
            $path = [];
            $at = (string) $start;
            while (isset($unreached[$at]) && ! isset($path[$at]) && ! isset($reported[$at])) {
                $path[$at] = true;
                $at = (string) $unreached[$at]['parent'];
            }
            if (! isset($path[$at])) {
                continue;
            }
            // PHP turns numeric-string keys into ints, so restore strings before the strict search.
            $members = array_map('strval', array_keys($path));
            $cycle = array_slice($members, (int) array_search($at, $members, true));
            sort($cycle, SORT_NATURAL);
            foreach ($cycle as $member) {
                $reported[$member] = true;
            }
            $problems[] = ['code' => 'cycle', 'ref' => $cycle[0], 'detail' => 'Cycle: '.implode(' > ', $cycle)];
        }

        return $problems;
    }
}
