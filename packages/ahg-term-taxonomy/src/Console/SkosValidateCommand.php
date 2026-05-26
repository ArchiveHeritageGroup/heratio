<?php

/**
 * Heratio - skos:validate artisan command.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Walks the chosen taxonomy (or every taxonomy when --taxonomy is omitted),
 * builds the same concept structure used by the SKOS exporter, and feeds
 * it to ShaclValidator. Reports each violation with shape id + concept URI
 * + human-readable message. Exit code is non-zero iff any violation is
 * found (CI-friendly).
 *
 * Usage:
 *   php artisan skos:validate --taxonomy=35
 *   php artisan skos:validate                # all taxonomies
 *   php artisan skos:validate --json         # JSON output
 *
 * #661 Phase 3.
 */

namespace AhgTermTaxonomy\Console;

use AhgTermTaxonomy\Validation\ShaclValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SkosValidateCommand extends Command
{
    protected $signature = 'skos:validate {--taxonomy= : Taxonomy id; omit to validate every taxonomy} {--json : Emit a JSON report instead of human-readable lines}';

    protected $description = 'Validate SKOS concepts in one (or all) taxonomies against the vendored SKOS SHACL shapes (#661 Phase 3, minimal subset).';

    public function handle(ShaclValidator $validator): int
    {
        $taxonomyId = $this->option('taxonomy');
        $json = (bool) $this->option('json');

        if ($taxonomyId !== null) {
            $ids = [(int) $taxonomyId];
        } else {
            $ids = DB::table('taxonomy')->orderBy('id')->pluck('id')->map(fn ($v) => (int) $v)->all();
        }

        $allReports = [];

        foreach ($ids as $tid) {
            $concepts = $this->buildConceptList($tid);
            if (empty($concepts) && $taxonomyId === null) {
                continue;
            }
            $reports = $validator->validate($concepts, app()->getLocale());
            foreach ($reports as &$r) {
                $r['taxonomy'] = $tid;
            }
            unset($r);
            $allReports = array_merge($allReports, $reports);
        }

        if ($json) {
            $this->line(json_encode([
                'violations' => count($allReports),
                'reports' => $allReports,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            if (empty($allReports)) {
                $this->info('OK - no SHACL violations found.');
            } else {
                $this->error(sprintf('Found %d SHACL violation(s):', count($allReports)));
                foreach ($allReports as $r) {
                    $this->line(sprintf(
                        '  [%s] taxonomy=%d concept=%s : %s',
                        $r['shape'],
                        $r['taxonomy'] ?? 0,
                        $r['concept'],
                        $r['message']
                    ));
                }
            }
        }

        return empty($allReports) ? self::SUCCESS : 1;
    }

    /**
     * Build the in-memory concept structure for a taxonomy. Mirrors the
     * exporter's data walk, but pulls prefLabels per culture so the
     * uniqueLang check (S2) can see all the language variants.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildConceptList(int $taxonomyId): array
    {
        $terms = DB::table('term')
            ->leftJoin('slug', 'term.id', '=', 'slug.object_id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->select('term.id', 'term.parent_id', 'slug.slug', 'term.code')
            ->get();

        if ($terms->isEmpty()) {
            return [];
        }

        $termIds = $terms->pluck('id')->all();
        $baseUri = url('/term').'/';

        // Multi-culture prefLabels per term.
        $prefLabelsByTerm = [];
        foreach (DB::table('term_i18n')->whereIn('id', $termIds)->get() as $row) {
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $prefLabelsByTerm[(int) $row->id][(string) $row->culture][] = $name;
        }

        // altLabels via other_name + other_name_i18n.
        $altByTerm = [];
        $otherNames = DB::table('other_name')
            ->join('other_name_i18n', 'other_name.id', '=', 'other_name_i18n.id')
            ->whereIn('other_name.object_id', $termIds)
            ->select('other_name.object_id', 'other_name_i18n.culture', 'other_name_i18n.name')
            ->get();
        foreach ($otherNames as $on) {
            $name = trim((string) $on->name);
            if ($name === '') {
                continue;
            }
            $altByTerm[(int) $on->object_id][] = ['lang' => (string) $on->culture, 'name' => $name];
        }

        $baseUri = url('/term').'/';
        $concepts = [];
        foreach ($terms as $t) {
            $uri = $baseUri.($t->slug ?: $t->id);
            $parentUri = null;
            if ($t->parent_id) {
                $parent = $terms->firstWhere('id', $t->parent_id);
                if ($parent) {
                    $parentUri = $baseUri.($parent->slug ?: $parent->id);
                }
            }

            $primary = '';
            foreach ($prefLabelsByTerm[(int) $t->id] ?? [] as $lang => $vals) {
                if (! empty($vals)) {
                    $primary = $vals[0];
                    break;
                }
            }

            $concepts[] = [
                'id' => (int) $t->id,
                'uri' => $uri,
                'prefLabel' => $primary,
                'prefLabels' => $prefLabelsByTerm[(int) $t->id] ?? [],
                'broader' => $parentUri,
                'altLabels' => $altByTerm[(int) $t->id] ?? [],
            ];
        }

        return $concepts;
    }
}
