<?php

/**
 * ClassificationRuleService — auto-classification engine (Phase 4.2).
 *
 * Evaluates a stack of classification rules against a document or record's
 * metadata and returns the best-matching file plan node + (optional) disposal
 * class. Rules are evaluated in `priority DESC` order; first match wins. Every
 * positive match is logged to rm_classification_log for audit.
 *
 * Supported rule types (codes from `ahg_dropdown` taxonomy `rm_classification_rule_type`):
 *
 *   folder_path  — match_pattern is a regex (e.g. `^/Projects/`) tested against meta.folder_path
 *   workspace    — exact (case-insensitive) match against meta.workspace
 *   tag          — any of meta.tags[] (comma-sep) appears in match_pattern (comma-sep)
 *   mime_type    — match_pattern is a regex tested against meta.mime_type
 *   metadata     — match_pattern is "key=value" matched against meta.custom[key]
 *   department   — exact (case-insensitive) match against meta.department
 *
 * The same engine runs for either a DM document (when ahg-dm ships) or a raw
 * information_object (today) — both produce the same shape of metadata array
 * via the {@see buildMetaForIO()} helper.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClassificationRuleService
{
    /* ====================================================================
     *  Rule CRUD
     * ==================================================================== */

    public function listRules(array $filters = []): array
    {
        $q = DB::table('rm_classification_rule as r')
            ->leftJoin('rm_fileplan_node as fp', 'fp.id', '=', 'r.fileplan_node_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'r.disposal_class_id')
            ->select(
                'r.*',
                'fp.code as fileplan_code', 'fp.title as fileplan_title',
                'dc.class_ref as disposal_class_ref', 'dc.title as disposal_class_title'
            );
        if (! empty($filters['rule_type'])) {
            $q->where('r.rule_type', $filters['rule_type']);
        }
        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $q->where('r.is_active', (int) $filters['is_active']);
        }
        if (! empty($filters['fileplan_node_id'])) {
            $q->where('r.fileplan_node_id', (int) $filters['fileplan_node_id']);
        }
        return $q->orderByDesc('r.priority')->orderBy('r.id')->limit(500)->get()->all();
    }

    public function getRule(int $id): ?object
    {
        return DB::table('rm_classification_rule as r')
            ->leftJoin('rm_fileplan_node as fp', 'fp.id', '=', 'r.fileplan_node_id')
            ->leftJoin('rm_disposal_class as dc', 'dc.id', '=', 'r.disposal_class_id')
            ->select(
                'r.*',
                'fp.code as fileplan_code', 'fp.title as fileplan_title',
                'dc.class_ref as disposal_class_ref', 'dc.title as disposal_class_title'
            )
            ->where('r.id', $id)
            ->first();
    }

    public function createRule(array $data, int $userId): int
    {
        return DB::table('rm_classification_rule')->insertGetId([
            'name'              => $data['name'],
            'description'       => $data['description'] ?? null,
            'rule_type'         => $data['rule_type'],
            'match_pattern'     => $data['match_pattern'],
            'fileplan_node_id'  => $data['fileplan_node_id'],
            'disposal_class_id' => $data['disposal_class_id'] ?? null,
            'priority'          => $data['priority'] ?? 0,
            'is_active'         => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'apply_on'          => $data['apply_on'] ?? 'declare',
            'created_by'        => $userId,
        ]);
    }

    public function updateRule(int $id, array $data): bool
    {
        $update = array_intersect_key($data, array_flip([
            'name', 'description', 'rule_type', 'match_pattern', 'fileplan_node_id',
            'disposal_class_id', 'priority', 'is_active', 'apply_on',
        ]));
        if (isset($update['is_active'])) {
            $update['is_active'] = (int) (bool) $update['is_active'];
        }
        return DB::table('rm_classification_rule')->where('id', $id)->update($update) > 0;
    }

    public function deleteRule(int $id): bool
    {
        return DB::table('rm_classification_rule')->where('id', $id)->delete() > 0;
    }

    /* ====================================================================
     *  Classification
     * ==================================================================== */

    /**
     * Evaluate all active rules against meta and return the first match.
     *
     * @return array{rule: object, fileplan_node_id: int, disposal_class_id: ?int, match_detail: string}|null
     */
    public function classify(array $meta, ?string $applyContext = null): ?array
    {
        $q = DB::table('rm_classification_rule')->where('is_active', 1);
        if ($applyContext !== null) {
            $q->whereIn('apply_on', [$applyContext, 'both']);
        }
        $rules = $q->orderByDesc('priority')->orderBy('id')->get();

        foreach ($rules as $rule) {
            $hit = $this->evaluateRule($rule, $meta);
            if ($hit !== null) {
                return [
                    'rule'              => $rule,
                    'fileplan_node_id'  => (int) $rule->fileplan_node_id,
                    'disposal_class_id' => $rule->disposal_class_id ? (int) $rule->disposal_class_id : null,
                    'match_detail'      => $hit,
                ];
            }
        }
        return null;
    }

    /**
     * Classify a live information_object. Loads its metadata, runs the engine,
     * persists rm_record_disposal_class (if matched) and logs to rm_classification_log.
     *
     * Idempotent: if the IO is already classified to the same node, returns null
     * without writing a duplicate log entry.
     *
     * @return array{rule_id:int, fileplan_node_id:int, disposal_class_id:?int}|null
     */
    public function classifyIO(int $ioId, string $applyContext = 'declare'): ?array
    {
        $meta = $this->buildMetaForIO($ioId);
        if (! $meta) {
            return null;
        }
        $match = $this->classify($meta, $applyContext);
        if (! $match) {
            return null;
        }

        // Skip if already logged for this IO + rule + node combo (idempotency).
        $alreadyLogged = DB::table('rm_classification_log')
            ->where('information_object_id', $ioId)
            ->where('rule_id', $match['rule']->id)
            ->where('fileplan_node_id', $match['fileplan_node_id'])
            ->exists();

        if (! $alreadyLogged) {
            DB::table('rm_classification_log')->insert([
                'information_object_id' => $ioId,
                'rule_id'               => $match['rule']->id,
                'fileplan_node_id'      => $match['fileplan_node_id'],
                'match_detail'          => $match['match_detail'],
            ]);
        }

        // Apply the disposal class to the record if the rule supplies one and the
        // record doesn't already carry one for this class.
        if ($match['disposal_class_id'] !== null) {
            $exists = DB::table('rm_record_disposal_class')
                ->where('information_object_id', $ioId)
                ->where('disposal_class_id', $match['disposal_class_id'])
                ->exists();
            if (! $exists) {
                DB::table('rm_record_disposal_class')->insert([
                    'information_object_id' => $ioId,
                    'disposal_class_id'     => $match['disposal_class_id'],
                    'assigned_by'           => 0, // system
                    'created_at'            => now(),
                ]);
            }
        }

        Log::info('rm: IO classified', [
            'io_id'   => $ioId,
            'rule_id' => $match['rule']->id,
            'node_id' => $match['fileplan_node_id'],
            'detail'  => $match['match_detail'],
        ]);

        return [
            'rule_id'           => (int) $match['rule']->id,
            'fileplan_node_id'  => $match['fileplan_node_id'],
            'disposal_class_id' => $match['disposal_class_id'],
        ];
    }

    /**
     * Bulk classify every IO that has no rm_classification_log entry yet.
     *
     * @return array{classified:int, skipped:int, failed:int}
     */
    public function classifyBatch(int $limit = 1000): array
    {
        $alreadyClassified = DB::table('rm_classification_log')
            ->whereNotNull('information_object_id')
            ->pluck('information_object_id')
            ->all();

        $unclassified = DB::table('information_object')
            ->whereNotIn('id', $alreadyClassified ?: [0])
            ->limit($limit)
            ->pluck('id');

        $classified = 0;
        $skipped    = 0;
        $failed     = 0;

        foreach ($unclassified as $ioId) {
            try {
                $r = $this->classifyIO($ioId, 'declare');
                if ($r) {
                    $classified++;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                Log::warning('rm: classifyBatch failed for IO ' . $ioId, ['error' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['classified' => $classified, 'skipped' => $skipped, 'failed' => $failed];
    }

    /**
     * Dry-run a single rule against ad-hoc metadata. Used by the rule edit form
     * for "Test this rule" buttons. Does not write anything.
     *
     * @return array{matched:bool, detail:?string}
     */
    public function testRule(int $ruleId, array $meta): array
    {
        $rule = DB::table('rm_classification_rule')->where('id', $ruleId)->first();
        if (! $rule) {
            return ['matched' => false, 'detail' => 'rule not found'];
        }
        $detail = $this->evaluateRule($rule, $meta);
        return ['matched' => $detail !== null, 'detail' => $detail];
    }

    /* ====================================================================
     *  Stats
     * ==================================================================== */

    public function counts(): array
    {
        return [
            'total_rules'        => DB::table('rm_classification_rule')->count(),
            'active_rules'       => DB::table('rm_classification_rule')->where('is_active', 1)->count(),
            'classified_records' => DB::table('rm_classification_log')->count(),
        ];
    }

    /**
     * Per-rule firing stats: how often each rule matched in the audit log.
     *
     * @return array<int,object>
     */
    public function ruleStats(): array
    {
        return DB::table('rm_classification_log as l')
            ->join('rm_classification_rule as r', 'r.id', '=', 'l.rule_id')
            ->selectRaw('r.id, r.name, r.rule_type, COUNT(*) as fires, MAX(l.classified_at) as last_fired')
            ->groupBy('r.id', 'r.name', 'r.rule_type')
            ->orderByDesc('fires')
            ->limit(50)
            ->get()
            ->all();
    }

    /* ====================================================================
     *  Internal: rule evaluation per type
     * ==================================================================== */

    /**
     * Returns a "match_detail" string when the rule fires, null otherwise.
     */
    private function evaluateRule(object $rule, array $meta): ?string
    {
        return match ($rule->rule_type) {
            'folder_path' => $this->matchRegex($meta['folder_path'] ?? '', $rule->match_pattern, 'folder_path'),
            'workspace'   => $this->matchExactCi($meta['workspace'] ?? '', $rule->match_pattern, 'workspace'),
            'tag'         => $this->matchTag($meta['tags'] ?? [], $rule->match_pattern),
            'mime_type'   => $this->matchRegex($meta['mime_type'] ?? '', $rule->match_pattern, 'mime_type'),
            'metadata'    => $this->matchMetadata($meta['custom'] ?? [], $rule->match_pattern),
            'department'  => $this->matchExactCi($meta['department'] ?? '', $rule->match_pattern, 'department'),
            default       => null,
        };
    }

    private function matchRegex(string $haystack, string $pattern, string $field): ?string
    {
        if ($haystack === '' || $pattern === '') {
            return null;
        }
        $delim   = '/';
        $escaped = $delim . str_replace($delim, '\\' . $delim, $pattern) . $delim . 'i';
        $ok      = @preg_match($escaped, $haystack);
        if ($ok === 1) {
            return "{$field}=" . mb_substr($haystack, 0, 200) . " ~~ {$pattern}";
        }
        return null;
    }

    private function matchExactCi(string $haystack, string $pattern, string $field): ?string
    {
        if ($haystack === '' || $pattern === '') {
            return null;
        }
        return strcasecmp(trim($haystack), trim($pattern)) === 0
            ? "{$field}={$haystack}"
            : null;
    }

    private function matchTag(array $docTags, string $pattern): ?string
    {
        if (empty($docTags) || $pattern === '') {
            return null;
        }
        $wanted = array_filter(array_map('trim', explode(',', strtolower($pattern))));
        $haves  = array_map(fn($t) => strtolower(trim((string) $t)), $docTags);
        $hits   = array_intersect($wanted, $haves);
        return $hits ? 'tag~' . implode(',', $hits) : null;
    }

    private function matchMetadata(array $custom, string $pattern): ?string
    {
        if (empty($custom) || $pattern === '') {
            return null;
        }
        // pattern format: key=value
        if (! str_contains($pattern, '=')) {
            return null;
        }
        [$k, $v] = array_map('trim', explode('=', $pattern, 2));
        if ($k === '') {
            return null;
        }
        $actual = $custom[$k] ?? null;
        if ($actual !== null && (string) $actual === $v) {
            return "metadata.{$k}={$v}";
        }
        return null;
    }

    /* ====================================================================
     *  Metadata builder for IOs (used until DM ships)
     * ==================================================================== */

    /**
     * Build the classify() meta array from an information_object id.
     * Captures: folder_path (slug breadcrumb), workspace=repository name,
     * department=repository name, tags=subject access points, mime_type=primary digital_object,
     * custom=identifier and source_culture.
     */
    public function buildMetaForIO(int $ioId): array
    {
        $io = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('actor_i18n as repo_i18n', function ($j) {
                $j->on('repo_i18n.id', '=', 'io.repository_id')->where('repo_i18n.culture', '=', 'en');
            })
            ->leftJoin('digital_object as dobj', 'dobj.object_id', '=', 'io.id')
            ->where('io.id', $ioId)
            ->select(
                'io.id', 'io.identifier', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.repository_id', 'io.source_culture',
                'ioi.title', 'repo_i18n.authorized_form_of_name as repository_name',
                'dobj.mime_type'
            )
            ->first();

        if (! $io) {
            return [];
        }

        $folderPath = $this->buildAncestorPath($ioId);
        $tags       = $this->collectTags($ioId);

        return [
            'folder_path' => $folderPath,
            'workspace'   => $io->repository_name ?? '',
            'department'  => $io->repository_name ?? '',
            'tags'        => $tags,
            'mime_type'   => (string) ($io->mime_type ?? ''),
            'custom'      => [
                'identifier'     => $io->identifier ?? '',
                'source_culture' => $io->source_culture ?? '',
            ],
        ];
    }

    private function buildAncestorPath(int $ioId): string
    {
        $row = DB::table('information_object')->where('id', $ioId)->select('lft', 'rgt')->first();
        if (! $row) {
            return '';
        }
        $ancestors = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'io.id')->where('ioi.culture', '=', 'en');
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.lft', '<=', $row->lft)
            ->where('io.rgt', '>=', $row->rgt)
            ->where('io.id', '!=', 1) // skip root sentinel
            ->orderBy('io.lft')
            ->select('ioi.title', 'slug.slug')
            ->get();

        if ($ancestors->isEmpty()) {
            return '';
        }
        $parts = $ancestors->map(fn($a) => $a->title ?: ($a->slug ?: ''))->filter()->all();
        return '/' . implode('/', $parts);
    }

    private function collectTags(int $ioId): array
    {
        // Subject access points come from object_term_relation (taxonomy_id=35 = Subject).
        return DB::table('object_term_relation as otr')
            ->join('term', 'term.id', '=', 'otr.term_id')
            ->join('term_i18n as ti', function ($j) {
                $j->on('ti.id', '=', 'term.id')->where('ti.culture', '=', 'en');
            })
            ->where('otr.object_id', $ioId)
            ->where('term.taxonomy_id', 35)
            ->whereNotNull('ti.name')
            ->pluck('ti.name')
            ->map(fn($n) => (string) $n)
            ->all();
    }
}
