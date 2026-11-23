<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDataMigration\SourceMigration;

use AhgCore\Constants\TermId;
use AhgCore\Services\AclService;
use AhgCore\Services\EncryptionService;
use AhgInformationObjectManage\Controllers\InformationObjectController;
use AhgInformationObjectManage\Services\InformationObjectService;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1495: acknowledgement and commit. Fails closed: the commit re-reads the
 * file and refuses unless the register is byte-identical to the one acknowledged.
 * The whole load is one transaction, and every record must be in the closure
 * under the target parent before it commits.
 */
class SourceMigrationRunner
{
    private const NOTE_TYPES = [
        'note_general' => TermId::NOTE_GENERAL,
        'note_publication' => TermId::NOTE_PUBLICATION,
        'note_language' => TermId::NOTE_LANGUAGE,
    ];

    public function __construct(private readonly SourceMigrationPreflight $preflight = new SourceMigrationPreflight) {}

    public function acknowledge(int $runId, int $userId, array $decisions): void
    {
        $run = $this->run($runId);
        if ($run->status !== 'preflighted') {
            throw new \RuntimeException("Run {$runId} is {$run->status} and cannot be acknowledged.");
        }
        $options = json_decode((string) $run->options_json, true) ?: [];
        if (! DB::table('user')->where('id', $userId)->exists() || ! $this->mayImport($userId, $options)) {
            throw new \RuntimeException('This user may not acknowledge a migration.');
        }

        $register = json_decode($run->register_json, true);
        $open = array_column($register['open_decisions'], null, 'key');
        if ($extra = array_diff(array_keys($decisions), array_keys($open))) {
            throw new \RuntimeException('Not an open decision: '.implode(', ', $extra));
        }
        foreach ($open as $key => $decision) {
            if (! isset($decisions[$key])) {
                throw new \RuntimeException("Open decision not answered: {$key}");
            }
            if (! in_array($decisions[$key], $decision['options'], true)) {
                throw new \RuntimeException("Invalid answer for {$key}.");
            }
        }

        $updated = DB::table('ahg_source_migration_run')->where('id', $runId)->where('status', 'preflighted')->update([
            'status' => 'acknowledged',
            'decisions_json' => json_encode($decisions),
            'acknowledged_by' => $userId,
            'acknowledged_at' => now(),
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw new \RuntimeException("Run {$runId} changed while being acknowledged.");
        }
    }

    /** @return int records created */
    public function commit(int $runId): int
    {
        $run = $this->run($runId);
        if ($run->status !== 'acknowledged' || ! $run->acknowledged_by) {
            throw new \RuntimeException("Run {$runId} has not been acknowledged.");
        }
        $options = json_decode((string) $run->options_json, true) ?: [];
        $mapping = MappingFile::load((string) ($options['mapping'] ?? ''));
        $analysis = $this->preflight->analyse($run->source_file, $mapping, $options);
        if (SourceMigrationPreflight::hash($analysis['register']) !== $run->register_sha256) {
            throw new \RuntimeException('The file, mapping or catalogue changed since the register was acknowledged. Run preflight again.');
        }
        if ($analysis['register']['blockers'] !== []) {
            throw new \RuntimeException('The register has blockers.');
        }

        $claimed = DB::table('ahg_source_migration_run')->where('id', $runId)->where('status', 'acknowledged')
            ->update(['status' => 'running', 'updated_at' => now()]);
        if ($claimed !== 1) {
            throw new \RuntimeException("Run {$runId} is already being committed.");
        }

        try {
            // The run state commits with the records: a crash cannot leave a loaded tree that a new preflight would pass.
            $count = DB::transaction(function () use ($runId, $analysis, $mapping, $options, $run) {
                $count = $this->write($runId, $analysis, $mapping, $options, json_decode((string) $run->decisions_json, true) ?: []);
                $done = DB::table('ahg_source_migration_run')->where('id', $runId)->where('status', 'running')->update([
                    'status' => 'committed', 'records_created' => $count, 'committed_at' => now(), 'updated_at' => now(),
                ]);
                if ($done !== 1) {
                    throw new \RuntimeException("Run {$runId} changed during commit.");
                }

                return $count;
            });
        } catch (\Throwable $e) {
            DB::table('ahg_source_migration_run')->where('id', $runId)->update([
                'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now(),
            ]);
            throw $e;
        }

        return $count;
    }

    /** The import right on everything the run files under, as the route middleware reads it. */
    private function mayImport(int $userId, array $options): bool
    {
        $repository = (int) ($options['repository_id'] ?? 0);
        $parent = (int) ($options['parent_id'] ?? 0);
        // The root belongs to no repository, so a named repository decides.
        if ($repository > 0 && $parent === InformationObjectService::ROOT_ID) {
            $parent = 0;
        }
        foreach (array_filter([$parent, $repository]) ?: [null] as $target) {
            if (! AclService::hasPermission($userId, 'import', $target)) {
                return false;
            }
        }

        return true;
    }

    private function write(int $runId, array $analysis, array $mapping, array $options, array $decisions): int
    {
        $culture = (string) ($options['culture'] ?? app()->getLocale());
        $target = (int) ($options['parent_id'] ?? InformationObjectService::ROOT_ID);
        $authorities = new AuthorityResolver($culture, $analysis['authority_types']);
        $ids = [];

        foreach ($analysis['nodes'] as $node) {
            $record = $node['synthesised'] ? null : $analysis['prepared'][$node['key']];
            $levelId = $this->levelId($record['level'] ?? null, $decisions, $culture);
            $data = [
                'parent_id' => $node['parent'] === null ? $target : $ids[$node['parent']],
                'identifier' => $node['ref'],
                'level_of_description_id' => $levelId,
                'repository_id' => $options['repository_id'] ?? null,
                'publication_status_id' => InformationObjectService::STATUS_DRAFT,
                'title' => $node['ref'],
            ];
            foreach ($record['scalars'] ?? [] as $field => $parts) {
                $data[$field] = count($parts) === 1
                    ? $parts[0][1]
                    : implode("\n\n", array_map(fn ($p) => "{$p[0]}: {$p[1]}", $parts));
            }

            $id = InformationObjectService::create($data, $culture);
            $ids[$node['ref']] = $id;
            $this->encryptRestricted($id, $culture, $data);

            DB::table('ahg_source_migration_record')->insert([
                'run_id' => $runId,
                'ref_hash' => hash('sha256', $node['ref']),
                'ref_no' => $node['ref'],
                'information_object_id' => $id,
                'synthesised' => $node['synthesised'] ? 1 : 0,
            ]);

            if ($record === null) {
                $this->note($id, TermId::NOTE_GENERAL, "Level synthesised during migration: the source had no record for {$node['ref']}.", $culture);

                continue;
            }
            $this->attach($id, $record, $decisions, $authorities, $culture);
        }

        $this->assertInClosure($target, array_values($ids));

        return count($ids);
    }

    private function attach(int $id, array $record, array $decisions, AuthorityResolver $authorities, string $culture): void
    {
        foreach ($record['notes'] as $target => $contents) {
            foreach ($contents as $content) {
                $this->note($id, self::NOTE_TYPES[$target], $content, $culture);
            }
        }
        if ($record['alternative'] !== null) {
            $this->property($id, 'alternativeIdentifiers', $record['alternative'], $culture);
        }
        $level = $record['level'];
        if ($level !== null && (! empty($level['metadata']['folds']) || (($level['metadata']['decision'] ?? null) === 'level_piece' && ($decisions['level_piece'] ?? null) === 'fold_to_item'))) {
            $this->property($id, 'sourceLevelOfDescription', $level['raw'], $culture);
        }

        $creators = array_map(fn ($n) => $authorities->resolve($n, null), $record['links']['actor_creator'] ?? []);
        $date = $record['date'];
        if ($date !== null || $creators !== []) {
            $first = true;
            foreach ($creators ?: [null] as $actorId) {
                $this->event($id, $actorId, $first ? $date : null, $decisions['date_policy'] ?? null, $culture);
                $first = false;
            }
        }

        foreach (['actor_person' => TermId::ACTOR_ENTITY_PERSON, 'actor_corporate' => TermId::ACTOR_ENTITY_CORPORATE_BODY] as $target => $type) {
            foreach ($record['links'][$target] ?? [] as $name) {
                $this->relate($id, $authorities->resolve($name, $type), $culture);
            }
        }
        foreach (['term_subject' => InformationObjectService::TAXONOMY_SUBJECT_ACCESS_POINTS, 'term_place' => InformationObjectService::TAXONOMY_PLACE_ACCESS_POINTS] as $target => $taxonomy) {
            foreach ($record['links'][$target] ?? [] as $name) {
                $this->termLink($id, $name, $taxonomy, $culture);
            }
        }
    }

    private function levelId(?array $level, array $decisions, string $culture): ?int
    {
        if ($level === null) {
            return null;
        }
        if (($level['metadata']['decision'] ?? null) === 'level_piece') {
            return match ($decisions['level_piece'] ?? null) {
                'fold_to_item' => $this->preflight->levelTermId('Item'),
                'create_piece_term' => InformationObjectController::findOrCreateTerm('Piece', SourceMigrationPreflight::LEVEL_TAXONOMY, $culture) ?: throw new \RuntimeException('Could not create the Piece term.'),
                default => throw new \RuntimeException('Piece decision missing.'),
            };
        }

        return $this->preflight->levelTermId((string) ($level['metadata']['level'] ?? ''));
    }

    /** create() leaves these two columns to a later sweep; encrypt them now. */
    private function encryptRestricted(int $id, string $culture, array $data): void
    {
        $encryption = new EncryptionService;
        $update = [];
        foreach (['access_conditions', 'reproduction_conditions'] as $column) {
            if (! empty($data[$column])) {
                $update[$column] = $encryption->encrypt(EncryptionService::CATEGORY_ACCESS_RESTRICTIONS, $data[$column], 'information_object_i18n', $column, $id);
            }
        }
        if ($update !== []) {
            DB::table('information_object_i18n')->where('id', $id)->where('culture', $culture)->update($update);
        }
    }

    /** Reads the closure table itself: an lft fallback would hide the failure this guards against. */
    private function assertInClosure(int $target, array $ids): void
    {
        foreach (array_chunk($ids, 500) as $chunk) {
            $inTree = DB::table('information_object_closure')->where('ancestor', $target)->whereIn('descendant', $chunk)->count();
            if ($inTree !== count($chunk)) {
                throw new \RuntimeException('Closure check failed: not every imported record is in the tree.');
            }
        }
    }

    private function newObject(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function note(int $objectId, int $typeId, string $content, string $culture): void
    {
        $id = $this->newObject('QubitNote');
        DB::table('note')->insert(['id' => $id, 'object_id' => $objectId, 'type_id' => $typeId, 'source_culture' => $culture]);
        DB::table('note_i18n')->insert(['id' => $id, 'culture' => $culture, 'content' => $content]);
    }

    private function property(int $objectId, string $name, string $value, string $culture): void
    {
        $id = $this->newObject('QubitProperty');
        DB::table('property')->insert(['id' => $id, 'object_id' => $objectId, 'name' => $name, 'source_culture' => $culture]);
        DB::table('property_i18n')->insert(['id' => $id, 'culture' => $culture, 'value' => $value]);
    }

    private function event(int $objectId, ?int $actorId, ?array $date, ?string $policy, string $culture): void
    {
        $normalise = $date !== null && $policy === 'keep_both';
        $id = $this->newObject('QubitEvent');
        DB::table('event')->insert([
            'id' => $id,
            'object_id' => $objectId,
            'actor_id' => $actorId,
            'type_id' => TermId::EVENT_TYPE_CREATION,
            'start_date' => $normalise ? $date['start'] : null,
            'end_date' => $normalise ? $date['end'] : null,
            'source_culture' => $culture,
        ]);
        DB::table('event_i18n')->insert(['id' => $id, 'culture' => $culture, 'date' => $date['display'] ?? null]);
    }

    private function relate(int $objectId, int $actorId, string $culture): void
    {
        $exists = DB::table('relation')->where('subject_id', $objectId)->where('object_id', $actorId)
            ->where('type_id', TermId::RELATION_NAME_ACCESS_POINT)->exists();
        if ($exists) {
            return;
        }
        DB::table('relation')->insert([
            'id' => $this->newObject('QubitRelation'),
            'subject_id' => $objectId,
            'object_id' => $actorId,
            'type_id' => TermId::RELATION_NAME_ACCESS_POINT,
            'source_culture' => $culture,
        ]);
    }

    private function termLink(int $objectId, string $name, int $taxonomy, string $culture): void
    {
        $termId = InformationObjectController::findOrCreateTerm($name, $taxonomy, $culture);
        if (! $termId) {
            throw new \RuntimeException("Could not resolve term: {$name}");
        }
        if (DB::table('object_term_relation')->where('object_id', $objectId)->where('term_id', $termId)->exists()) {
            return;
        }
        DB::table('object_term_relation')->insert([
            'id' => $this->newObject('QubitObjectTermRelation'),
            'object_id' => $objectId,
            'term_id' => $termId,
        ]);
    }

    private function run(int $runId): object
    {
        return DB::table('ahg_source_migration_run')->where('id', $runId)->first()
            ?? throw new \RuntimeException("No migration run {$runId}.");
    }
}
