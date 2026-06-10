<?php

/**
 * GraphKmBridgeService - KM <-> knowledge-graph bridge.
 *
 * A concrete slice of heratio#1197 (the unified G/L/A/M knowledge graph) and
 * heratio#1214 (RiC-native node resolution). It turns each record's
 * cross-collection RiC graph connections into a concise, factual,
 * natural-language summary and writes that summary as a markdown file under
 * docs/reference/graph-connections/ so the KM inotify watcher auto-ingests it
 * (~2-3 min, no curl / no manual trigger). The effect: the cross-collection
 * graph becomes queryable through KM in plain language.
 *
 * Source of truth is AhgRic\Services\RelationshipService::crossCollectionNeighbours(),
 * which is read here only (ahg-ric is a locked package). This service renders -
 * it never invents. Every sentence is a plain catalogue fact derived from the
 * relation table + resolved entity names; there is NO AI generation in this path.
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class GraphKmBridgeService
{
    /**
     * Max neighbours to name inline per domain before collapsing to
     * "... and N more". Keeps a summary readable and the KM chunk bounded.
     */
    protected int $perDomainCap = 8;

    /** Hard cap on total summary characters (safety net against runaway records). */
    protected int $maxChars = 4000;

    /**
     * Build a concise, factual natural-language summary of how one record
     * connects across the collection. Plain catalogue facts only - grouped by
     * domain, capped per domain. Returns null when the record has no resolvable
     * connections (so callers can skip writing an empty doc).
     */
    public function connectionsSummary(int $objectId): ?string
    {
        $title = $this->recordTitle($objectId);
        $neighbours = app(\AhgRic\Services\RelationshipService::class)
            ->crossCollectionNeighbours($objectId);

        $groups = $neighbours['groups'] ?? [];
        $total = (int) ($neighbours['total'] ?? 0);

        if (! $groups || $total === 0) {
            return null;
        }

        $label = $title !== null ? '"'.$title.'"' : 'Record #'.$objectId;

        $sentences = [];
        $sentences[] = sprintf(
            '%s is connected to %d related %s across the collection.',
            $label,
            $total,
            $total === 1 ? 'entity' : 'entities'
        );

        foreach ($groups as $group) {
            $domain = (string) ($group['domain'] ?? 'Other');
            $items = $group['items'] ?? [];
            $count = (int) ($group['count'] ?? count($items));
            if (! $items) {
                continue;
            }

            $names = [];
            foreach ($items as $item) {
                $name = trim((string) ($item['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
                if (count($names) >= $this->perDomainCap) {
                    break;
                }
            }
            if (! $names) {
                continue;
            }

            $shown = $this->joinNames($names);
            $remainder = $count - count($names);
            $tail = $remainder > 0 ? sprintf(' and %d more', $remainder) : '';

            $sentences[] = sprintf(
                'Under %s (%d) it links to %s%s.',
                $this->lcFirstDomain($domain),
                $count,
                $shown,
                $tail
            );
        }

        $summary = implode(' ', $sentences);

        if (mb_strlen($summary) > $this->maxChars) {
            $summary = rtrim(mb_substr($summary, 0, $this->maxChars - 3)).'...';
        }

        return $summary;
    }

    /**
     * Write the connections summary (plus the record title and a per-domain
     * breakdown) as a markdown file under docs/reference/graph-connections/ so
     * the KM watcher ingests it. Returns the absolute path written, or null
     * when the record has no connections to summarise.
     */
    public function writeKmDoc(int $objectId): ?string
    {
        $summary = $this->connectionsSummary($objectId);
        if ($summary === null) {
            return null;
        }

        $title = $this->recordTitle($objectId);
        $neighbours = app(\AhgRic\Services\RelationshipService::class)
            ->crossCollectionNeighbours($objectId);
        $groups = $neighbours['groups'] ?? [];
        $total = (int) ($neighbours['total'] ?? 0);

        $dir = base_path('docs/reference/graph-connections');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $slug = $this->recordSlug($objectId);
        $fileBase = $slug !== null ? Str::slug($slug) : (string) $objectId;
        if ($fileBase === '') {
            $fileBase = (string) $objectId;
        }
        // Always suffix the id so two records that slugify the same never collide.
        $path = $dir.'/'.$fileBase.'-'.$objectId.'.md';

        $heading = $title !== null ? $title : ('Record #'.$objectId);

        $lines = [];
        $lines[] = '# Graph connections: '.$heading;
        $lines[] = '';
        $lines[] = '> Cross-collection knowledge-graph summary for information object #'
            .$objectId.'. Auto-generated from the RiC relation graph (heratio#1197 / #1214). '
            .'Plain catalogue facts only - no AI-generated content.';
        $lines[] = '';
        $lines[] = '## Summary';
        $lines[] = '';
        $lines[] = $summary;
        $lines[] = '';
        $lines[] = '## Connections by domain';
        $lines[] = '';

        if ($groups) {
            foreach ($groups as $group) {
                $domain = (string) ($group['domain'] ?? 'Other');
                $items = $group['items'] ?? [];
                $count = (int) ($group['count'] ?? count($items));
                if (! $items) {
                    continue;
                }
                $lines[] = '### '.$domain.' ('.$count.')';
                $lines[] = '';
                $listed = 0;
                foreach ($items as $item) {
                    $name = trim((string) ($item['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $lines[] = '- '.$name;
                    $listed++;
                    if ($listed >= $this->perDomainCap) {
                        $remainder = $count - $listed;
                        if ($remainder > 0) {
                            $lines[] = '- _... and '.$remainder.' more_';
                        }
                        break;
                    }
                }
                $lines[] = '';
            }
        }

        $lines[] = '## Provenance';
        $lines[] = '';
        $lines[] = '- Record id: '.$objectId;
        if ($slug !== null) {
            $lines[] = '- Record slug: '.$slug;
        }
        $lines[] = '- Total related entities: '.$total;
        $lines[] = '- Generated by: ahg:km-graph-sync (GraphKmBridgeService)';
        $lines[] = '';

        $content = implode("\n", $lines);
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Resolve the record's own English title from information_object_i18n.
     * Returns null when no title is found.
     */
    public function recordTitle(int $objectId): ?string
    {
        if (! Schema::hasTable('information_object_i18n')) {
            return null;
        }
        $title = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', 'en')
            ->value('title');
        $title = is_string($title) ? trim($title) : null;

        return ($title !== null && $title !== '') ? $title : null;
    }

    /**
     * Resolve the record's slug from the slug table. Used for a stable,
     * human-readable doc filename. Returns null when none exists.
     */
    public function recordSlug(int $objectId): ?string
    {
        if (! Schema::hasTable('slug')) {
            return null;
        }
        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        $slug = is_string($slug) ? trim($slug) : null;

        return ($slug !== null && $slug !== '') ? $slug : null;
    }

    /**
     * Join a list of names into an Oxford-comma English phrase:
     * ["A"] -> "A"; ["A","B"] -> "A and B"; ["A","B","C"] -> "A, B, and C".
     *
     * @param  array<int,string>  $names
     */
    protected function joinNames(array $names): string
    {
        $names = array_values(array_map(fn ($n) => "'".$n."'", $names));
        $n = count($names);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $names[0];
        }
        if ($n === 2) {
            return $names[0].' and '.$names[1];
        }
        $last = array_pop($names);

        return implode(', ', $names).', and '.$last;
    }

    /**
     * Lower-case the first letter of a domain label for mid-sentence use,
     * leaving acronyms / parenthetical "(RiC)" suffixes intact.
     */
    protected function lcFirstDomain(string $domain): string
    {
        return mb_strtolower(mb_substr($domain, 0, 1)).mb_substr($domain, 1);
    }
}
