<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

use AhgCore\Constants\TermId;
use AhgDataMigration\Services\DataMigrationService;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1495: reads an export and produces the data-loss register. Touches
 * only its own run table: nothing in the catalogue is written here.
 */
class SourceMigrationPreflight
{
    public const LEVEL_TAXONOMY = 34;

    /** @var array<string,?int> */
    private array $levelTerms = [];

    public function __construct(
        private readonly ReferenceHierarchyEngine $engine = new ReferenceHierarchyEngine,
        private readonly TransformVocabulary $transforms = new TransformVocabulary,
    ) {}

    /** Runs the analysis and stores the run. Returns the run id. */
    public function run(string $file, string $mappingName, array $options = []): int
    {
        $mapping = MappingFile::load($mappingName);
        $options = $this->cleanOptions($options) + ['mapping' => $mappingName];
        $register = $this->analyse($file, $mapping, $options)['register'];
        $blockers = count($register['blockers']);

        return (int) DB::table('ahg_source_migration_run')->insertGetId([
            'source' => $mapping['source'],
            'source_file' => $file,
            'source_sha256' => $register['source_sha256'],
            'mapping_sha256' => $mapping['_sha256'],
            'register_sha256' => self::hash($register),
            'register_json' => json_encode($register, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'options_json' => json_encode($options),
            'status' => $blockers > 0 ? 'rejected' : 'preflighted',
            'blocker_count' => $blockers,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function hash(array $register): string
    {
        return hash('sha256', (string) json_encode($register, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{register:array,nodes:list<array>,prepared:array<int,array>} */
    public function analyse(string $file, array $mapping, array $options): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new \InvalidArgumentException('Source file cannot be read.');
        }
        $culture = (string) ($options['culture'] ?? app()->getLocale());
        [$headers, $rows] = $this->readTable($file);

        $blockers = [];
        $canonical = $this->canonicalHeaders($headers, $mapping, $blockers);
        $refField = $mapping['reference_field'];
        if (! in_array($refField, $canonical, true)) {
            $blockers[] = ['code' => 'missing_reference_column', 'ref' => $refField, 'detail' => 'The export has no reference column.'];
        }

        // Keep catalogue rows; other Calm record types are named in the register.
        $included = [];
        $excluded = [];
        $typeField = $mapping['record_type']['field'] ?? null;
        $shortRows = 0;
        foreach ($rows as $index => $cells) {
            // Extra cells mean values have shifted columns, so nothing in the row can be trusted.
            if (count($cells) > count($canonical)) {
                $blockers[] = ['code' => 'malformed_row', 'ref' => 'Row '.($index + 2), 'detail' => count($cells).' cells under '.count($canonical).' headings. Quote the value that contains the delimiter.'];

                continue;
            }
            // Exporters omit trailing empty cells: no value is lost, and the count is reported.
            if (count($cells) < count($canonical)) {
                $shortRows++;
            }
            $row = [];
            foreach ($canonical as $i => $name) {
                $row[$name] = trim((string) ($cells[$i] ?? ''));
            }
            if ($typeField !== null && ($row[$typeField] ?? '') !== '') {
                $type = $this->transforms->lookup($mapping['record_type']['taxonomy'], $row[$typeField]);
                if ($type['code'] === null) {
                    $blockers[] = ['code' => 'unknown_record_type', 'ref' => $row[$typeField], 'detail' => 'Row '.($index + 2).': add this record type in the Dropdown Manager.'];

                    continue;
                }
                if (empty($type['metadata']['import'])) {
                    $excluded[$type['label']] = ($excluded[$type['label']] ?? 0) + 1;

                    continue;
                }
            }
            $included[$index] = $row;
        }

        $nodes = [];
        $synthesised = [];
        if (! array_filter($blockers, fn ($b) => in_array($b['code'], ['missing_reference_column', 'duplicate_column'], true))) {
            $records = [];
            foreach ($included as $index => $row) {
                $records[] = ['key' => $index, 'ref' => $row[$refField], 'parent' => $row[$mapping['parent_field'] ?? ''] ?? null];
            }
            try {
                $tree = $this->engine->build($records, $mapping['delimiter']);
                $nodes = $tree['nodes'];
                $synthesised = $tree['synthesised'];
            } catch (HierarchyRejected $e) {
                array_push($blockers, ...$e->problems);
            }
        }

        $prepared = [];
        $dates = ['with_value' => 0, 'normalised' => 0, 'display_only' => 0];
        $levels = ['piece' => 0, 'folded' => 0, 'without_level' => 0];
        foreach ($included as $index => $row) {
            $record = $this->prepare($row, $mapping);
            $prepared[$index] = $record;
            if ($record['date'] !== null) {
                $dates['with_value']++;
                $dates[$record['date']['parsed'] ? 'normalised' : 'display_only']++;
            }
            $level = $record['level'];
            if ($level === null) {
                $levels['without_level']++;
            } elseif ($level['code'] === null) {
                $blockers[] = ['code' => 'unmapped_level', 'ref' => $level['raw'], 'detail' => 'Row '.($index + 2).': add this level in the Dropdown Manager.'];
            } elseif (($level['metadata']['decision'] ?? null) === 'level_piece') {
                $levels['piece']++;
            } else {
                if (! empty($level['metadata']['folds'])) {
                    $levels['folded']++;
                }
                if ($this->levelTermId((string) ($level['metadata']['level'] ?? '')) === null) {
                    $blockers[] = ['code' => 'level_term_missing', 'ref' => $level['raw'], 'detail' => 'No matching term in levels of description.'];
                }
            }
        }

        $authorityTypes = $this->planAuthorities($prepared, $culture, $blockers);
        $this->checkPlacement($options, $blockers);
        $this->checkAlreadyImported($mapping['source'], array_column($nodes, 'ref'), $blockers);

        $register = [
            'source' => $mapping['source'],
            'source_sha256' => hash_file('sha256', $file),
            'mapping_sha256' => $mapping['_sha256'],
            'rows_in_file' => count($rows),
            'short_rows' => $shortRows,
            'catalogue_rows' => count($included),
            'records_to_create' => count($nodes),
            'excluded_record_types' => $excluded,
            'fields' => $this->fieldEntries($canonical, $included, $mapping, $levels),
            'synthesised_levels' => $synthesised,
            'dates' => $dates,
            'levels' => $levels,
            'open_decisions' => $this->openDecisions($levels, $dates),
            'blockers' => $blockers,
        ];

        return ['register' => $register, 'nodes' => $nodes, 'prepared' => $prepared, 'authority_types' => $authorityTypes];
    }

    /**
     * Plans every authority in the file before anything is written, so the
     * order of columns and rows cannot change which actor a name becomes.
     *
     * @return array<string,int> nameKey => the type its typed column establishes
     */
    private function planAuthorities(array $prepared, string $culture, array &$blockers): array
    {
        $spellings = [];
        $typed = [];
        foreach ($prepared as $record) {
            foreach (['actor_creator' => null, 'actor_person' => TermId::ACTOR_ENTITY_PERSON, 'actor_corporate' => TermId::ACTOR_ENTITY_CORPORATE_BODY] as $target => $type) {
                foreach ($record['links'][$target] ?? [] as $name) {
                    $key = AuthorityResolver::nameKey($name);
                    $spellings[$key] ??= $name;
                    if ($type !== null) {
                        $typed[$key][$type] = true;
                    }
                }
            }
        }

        $types = [];
        foreach ($typed as $key => $found) {
            if (count($found) > 1) {
                $blockers[] = ['code' => 'authority_type_conflict', 'ref' => $spellings[$key], 'detail' => 'This name is used as both a person and a corporate body.'];
            } else {
                $types[$key] = array_key_first($found);
            }
        }

        $resolver = new AuthorityResolver($culture, $types);
        $folds = [];
        foreach ($spellings as $key => $name) {
            $folds[$resolver->foldKey($name)][] = $key;
        }
        foreach ($folds as $group) {
            foreach ($group as $key) {
                if (isset($typed[$key]) && count($typed[$key]) > 1) {
                    continue;
                }
                $name = $spellings[$key];
                $type = $types[$key] ?? null;
                $exact = $resolver->candidates($name, $type);
                if (count($exact) > 1) {
                    $blockers[] = ['code' => 'ambiguous_authority', 'ref' => $name, 'detail' => 'More than one authority record has this name. Merge them or rename one first.'];
                } elseif ($exact === [] && $resolver->nearMatches($name, $type) !== []) {
                    $blockers[] = ['code' => 'ambiguous_authority', 'ref' => $name, 'detail' => 'An authority record differs from this name only by accents. Correct the spelling or rename one first.'];
                } elseif ($exact === [] && count($group) > 1) {
                    $blockers[] = ['code' => 'ambiguous_authority', 'ref' => $name, 'detail' => 'The file spells this name in more than one way, differing only by accents: '.implode(', ', array_map(fn ($k) => $spellings[$k], $group)).'.'];
                }
            }
        }

        return $types;
    }

    public function levelTermId(string $name): ?int
    {
        if ($name === '') {
            return null;
        }
        if (! array_key_exists($name, $this->levelTerms)) {
            $id = DB::table('term')->join('term_i18n', 'term.id', '=', 'term_i18n.id')
                ->where('term.taxonomy_id', self::LEVEL_TAXONOMY)
                ->where('term_i18n.culture', 'en')
                ->where('term_i18n.name', $name)
                ->min('term.id');
            $this->levelTerms[$name] = $id === null ? null : (int) $id;
        }

        return $this->levelTerms[$name];
    }

    public function cleanOptions(array $options): array
    {
        return array_filter([
            'parent_id' => isset($options['parent_id']) ? (int) $options['parent_id'] : null,
            'repository_id' => isset($options['repository_id']) ? (int) $options['repository_id'] : null,
            'culture' => $options['culture'] ?? app()->getLocale(),
        ], fn ($v) => $v !== null);
    }

    /** @return list<string> the file's column headings, read the same way preflight reads them */
    public function headers(string $file): array
    {
        return $this->readTable($file)[0];
    }

    /** @return array{0:list<string>,1:list<array>} */
    private function readTable(string $file): array
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $copy = tempnam(sys_get_temp_dir(), 'srcmig');
        $normalised = $copy.'.normalised.csv';
        try {
            copy($file, $copy);
            app(DataMigrationService::class)->normaliseUpload($copy, $ext);
            $fh = fopen($normalised, 'r');
            $headers = array_map(fn ($h) => trim((string) $h), fgetcsv($fh) ?: []);
            $rows = [];
            while (($row = fgetcsv($fh)) !== false) {
                if (array_filter($row, fn ($c) => trim((string) $c) !== '') !== []) {
                    $rows[] = $row;
                }
            }
            fclose($fh);
        } finally {
            @unlink($copy);
            @unlink($normalised);
        }

        return [$headers, $rows];
    }

    /** Spell headings as the mapping does, so case differences do not drop a column. */
    private function canonicalHeaders(array $headers, array $mapping, array &$blockers): array
    {
        $known = [];
        foreach (array_keys($mapping['fields']) as $name) {
            $known[mb_strtolower((string) $name)] = (string) $name;
        }
        $seen = [];
        $out = [];
        foreach ($headers as $i => $header) {
            $name = $known[mb_strtolower($header)] ?? $header;
            if (isset($seen[$name])) {
                $blockers[] = ['code' => 'duplicate_column', 'ref' => $name, 'detail' => 'The column appears more than once.'];
            }
            $seen[$name] = true;
            $out[$i] = $name;
        }

        return $out;
    }

    private function prepare(array $row, array $mapping): array
    {
        $record = ['scalars' => [], 'notes' => [], 'links' => [], 'date' => null, 'level' => null, 'alternative' => null];
        foreach ($mapping['fields'] as $name => $field) {
            $raw = $row[$name] ?? '';
            $target = $field['target'];
            if ($raw === '' || $target === null || in_array($target, ['identifier', 'hierarchy'], true)) {
                continue;
            }
            $value = $this->transforms->apply($field['transforms'] ?? [], $raw);
            if (in_array($target, MappingFile::SCALAR_TARGETS, true)) {
                $record['scalars'][$target][] = [(string) $name, (string) $value];
            } elseif (in_array($target, MappingFile::NOTE_TARGETS, true)) {
                $record['notes'][$target][] = (string) $value;
            } elseif (in_array($target, MappingFile::LINK_TARGETS, true)) {
                $record['links'][$target] = array_values(array_unique(array_merge($record['links'][$target] ?? [], (array) $value)));
            } elseif ($target === 'event_date') {
                $record['date'] = is_array($value) ? $value : $this->transforms->parseDate((string) $value);
            } elseif ($target === 'level') {
                $record['level'] = $value;
            } elseif ($target === 'alternative_identifier') {
                $record['alternative'] = (string) $value;
            }
        }

        return $record;
    }

    private function fieldEntries(array $canonical, array $included, array $mapping, array $levels): array
    {
        $counts = [];
        foreach ($canonical as $name) {
            $counts[$name] = count(array_filter($included, fn ($row) => ($row[$name] ?? '') !== ''));
        }
        $byTarget = [];
        foreach ($canonical as $name) {
            $target = $mapping['fields'][$name]['target'] ?? null;
            if ($target !== null && in_array($target, MappingFile::SCALAR_TARGETS, true) && $counts[$name] > 0) {
                $byTarget[$target][] = $name;
            }
        }

        $entries = [];
        foreach ($canonical as $name) {
            $field = $mapping['fields'][$name] ?? null;
            if ($field === null) {
                $entries[] = ['field' => $name, 'action' => 'dropped', 'target' => null, 'rows_with_value' => $counts[$name], 'reason' => 'No mapping for this column.'];

                continue;
            }
            $target = $field['target'];
            $action = $field['loss'] ?? 'mapped';
            $reason = $field['reason'] ?? null;
            if ($action === 'mapped' && count($byTarget[$target] ?? []) > 1) {
                $action = 'folded';
                $reason = 'Shares '.$target.' with '.implode(', ', array_diff($byTarget[$target], [$name])).'; each value keeps its source label.';
            }
            if ($target === 'level' && ($levels['piece'] + $levels['folded']) > 0) {
                $action = 'flattened';
                $reason = "{$levels['piece']} Piece and {$levels['folded']} other row(s) use a level with no exact equivalent. The source level is kept as a property when folded.";
            }
            $entries[] = ['field' => $name, 'action' => $action, 'target' => $target, 'rows_with_value' => $counts[$name], 'reason' => $reason];
        }

        return $entries;
    }

    private function openDecisions(array $levels, array $dates): array
    {
        $decisions = [];
        if ($levels['piece'] > 0) {
            $decisions[] = [
                'key' => 'level_piece',
                'status' => 'open',
                'affected_rows' => $levels['piece'],
                'question' => 'Calm Piece has no equivalent level of description. Fold into Item or add a Piece term?',
                'options' => $this->optionCodes('source_migration_piece_policy'),
            ];
        }
        if ($dates['with_value'] > 0) {
            $decisions[] = [
                'key' => 'date_policy',
                'status' => 'open',
                'affected_rows' => $dates['with_value'],
                'question' => "{$dates['normalised']} date(s) can be normalised and {$dates['display_only']} cannot. Keep display expressions with normalised dates, or display only?",
                'options' => $this->optionCodes('source_migration_date_policy'),
            ];
        }

        return $decisions;
    }

    private function optionCodes(string $taxonomy): array
    {
        return DB::table('ahg_dropdown')->where('taxonomy', $taxonomy)->where('is_active', 1)
            ->orderBy('sort_order')->pluck('code')->all();
    }

    private function checkPlacement(array $options, array &$blockers): void
    {
        $parent = (int) ($options['parent_id'] ?? 1);
        if (! DB::table('information_object_closure')->where('ancestor', $parent)->where('descendant', $parent)->exists()) {
            $blockers[] = ['code' => 'parent_not_in_tree', 'ref' => (string) $parent, 'detail' => 'The target parent is not in the closure tree.'];
        }
        if (! empty($options['repository_id']) && ! DB::table('repository')->where('id', $options['repository_id'])->exists()) {
            $blockers[] = ['code' => 'repository_not_found', 'ref' => (string) $options['repository_id'], 'detail' => 'No such repository.'];
        }
    }

    private function checkAlreadyImported(string $source, array $refs, array &$blockers): void
    {
        foreach (array_chunk($refs, 500) as $chunk) {
            $found = DB::table('ahg_source_migration_record as r')
                ->join('ahg_source_migration_run as m', 'm.id', '=', 'r.run_id')
                ->join('information_object as io', 'io.id', '=', 'r.information_object_id')
                ->where('m.source', $source)->where('m.status', 'committed')
                ->whereIn('r.ref_hash', array_map(fn ($r) => hash('sha256', $r), $chunk))
                ->pluck('r.ref_no');
            foreach ($found as $ref) {
                $blockers[] = ['code' => 'already_imported', 'ref' => $ref, 'detail' => 'An earlier run imported this reference.'];
            }
        }
    }
}
