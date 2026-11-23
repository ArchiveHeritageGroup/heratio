<?php

/**
 * AuthorityVocabularySyncService - applies an external concept to a local
 * authority record under the local-override model (heratio#1475 item 3):
 * overridden fields, local relations and relations staff removed are never
 * changed by a sync.
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class AuthorityVocabularySyncService
{
    /** ponytail: other-side checks per sync; edges past this wait for the next sync. */
    private const MAX_DROP_CHECKS = 25;

    public function __construct(
        private LcshVocabularyClient $client,
        private AuthorityControlService $authorities,
        private AuthorityRecordService $records,
        private AuthorityHierarchyService $hierarchy,
    ) {
    }

    /**
     * @param string|null $candidateId identifier to link; written only once its concept has been read
     * @return array{ok:bool,error:?string,heading_updated:bool,relations:int,created:int,skipped:int}
     */
    public function sync(int $authorityId, ?string $candidateId = null): array
    {
        $result = ['ok' => false, 'error' => null, 'heading_updated' => false, 'relations' => 0, 'created' => 0, 'skipped' => 0];

        $record = $this->records->get($authorityId);
        $authority = $this->authorities->find($authorityId);
        $scheme = LcshVocabularyClient::SCHEME;
        $stored = $record && $record->scheme === $scheme ? $record->external_id : null;
        $otherScheme = $record && $record->external_id && $record->scheme !== $scheme;
        $externalId = $candidateId ?: ($stored ?: ($authority && ! $otherScheme ? AuthorityRecordService::lcshIdFrom($authority) : null));
        if (! $authority || ! $externalId) {
            $result['error'] = 'This record is not linked to an external vocabulary.';

            return $result;
        }
        if ($externalId !== $stored && $this->records->externalIdTaken($scheme, $externalId, $authorityId)) {
            $result['error'] = 'Another authority record is already linked to that identifier.';

            return $result;
        }

        // A failure while trying a new identifier must not mark the working link as failed.
        $stampFailure = fn (string $error) => $externalId === $stored ? $this->stamp($authorityId, $error) : null;

        try {
            $concept = $this->client->fetch($externalId);
            $droppable = $this->droppableEdges($authorityId, $concept);
            DB::transaction(function () use ($authorityId, $authority, $record, $concept, $droppable, $scheme, $externalId, $stored, &$result) {
                if ($externalId !== $stored) {
                    $this->records->linkExternal($authorityId, $scheme, $externalId);
                }
                $this->apply($authorityId, $authority, $record, $concept, $droppable, $result);
            });
        } catch (QueryException $e) {
            // SQL text must not reach the screen or the stored error.
            Log::error('Authority sync failed for '.$authorityId.': '.$e->getMessage());
            $result['error'] = 'The sync could not be saved.';
            $stampFailure($result['error']);

            return $result;
        } catch (RuntimeException $e) {
            $result['error'] = $e->getMessage();
            $stampFailure($result['error']);

            return $result;
        }

        $result['ok'] = true;
        $this->stamp($authorityId, null);

        return $result;
    }

    private function apply(int $id, object $authority, ?object $record, array $concept, array $droppable, array &$result): void
    {
        $overridden = $this->records->overriddenFields($record);
        $update = [];

        if (! in_array('uri', $overridden, true) && $authority->uri !== $concept['uri']) {
            $update['uri'] = $concept['uri'];
        }
        if (! in_array('heading', $overridden, true) && $authority->heading !== $concept['label']) {
            if ($this->headingTaken($id, $authority, $concept['label'])) {
                $result['skipped']++;
            } else {
                $update['heading'] = $concept['label'];
                $result['heading_updated'] = true;
            }
        }
        if ($update) {
            $this->authorities->update($id, $update);
        }

        $keep = [];
        foreach (['broader', 'narrower'] as $direction) {
            foreach ($concept[$direction] as $reference) {
                $otherId = $this->resolveNeighbour($reference, $authority, $result);
                if (! $otherId || $otherId === $id) {
                    $result['skipped']++;
                    continue;
                }
                [$broader, $narrower] = $direction === 'broader' ? [$otherId, $id] : [$id, $otherId];
                $keep[$broader.':'.$narrower] = true;
                try {
                    $this->hierarchy->addRelation($broader, $narrower, AuthorityHierarchyService::ORIGIN_SYNC)
                        ? $result['relations']++
                        : $result['skipped']++;
                } catch (InvalidArgumentException) {
                    $result['skipped']++;
                }
            }
        }

        foreach ($droppable as $edgeId => $key) {
            if (! isset($keep[$key])) {
                DB::table('library_authority_relation')->where('id', $edgeId)->delete();
            }
        }
    }

    /**
     * Synced edges neither side still asserts. Read before the transaction
     * because it may fetch the other concept. A truncated list, or another side
     * that cannot be read, proves nothing, so the edge stays. Local relations
     * are never candidates.
     *
     * @return array<int,string> edge id => "broader:narrower"
     */
    private function droppableEdges(int $id, array $concept): array
    {
        $edges = DB::table('library_authority_relation')
            ->where('origin', AuthorityHierarchyService::ORIGIN_SYNC)->where('suppressed', 0)
            ->where(fn ($q) => $q->where('broader_id', $id)->orWhere('narrower_id', $id))
            ->get(['id', 'broader_id', 'narrower_id']);
        $otherIds = $edges->map(fn ($e) => (int) $e->broader_id === $id ? $e->narrower_id : $e->broader_id)->all();
        $externalIds = DB::table('library_authority_record')->whereIn('authority_id', $otherIds)
            ->where('scheme', LcshVocabularyClient::SCHEME)->pluck('external_id', 'authority_id');

        $droppable = [];
        $checks = 0;
        foreach ($edges as $edge) {
            $otherIsBroader = (int) $edge->narrower_id === $id;
            [$direction, $reverse] = $otherIsBroader ? ['broader', 'narrower'] : ['narrower', 'broader'];
            if (! $concept[$direction.'_complete']) {
                continue;
            }
            $otherExternal = $externalIds[$otherIsBroader ? $edge->broader_id : $edge->narrower_id] ?? null;
            if ($otherExternal) {
                if (in_array($otherExternal, array_column($concept[$direction], 'external_id'), true)
                    || ++$checks > self::MAX_DROP_CHECKS) {
                    continue;
                }
                try {
                    $other = $this->client->fetch($otherExternal);
                } catch (RuntimeException) {
                    continue;
                }
                if (! $other[$reverse.'_complete']
                    || in_array($concept['external_id'], array_column($other[$reverse], 'external_id'), true)) {
                    continue;
                }
            }
            $droppable[(int) $edge->id] = $edge->broader_id.':'.$edge->narrower_id;
        }

        return $droppable;
    }

    private function headingTaken(int $id, object $authority, string $heading): bool
    {
        $column = $this->authorities->typeColumn();
        $query = DB::table('library_subject_authority')->where('id', '!=', $id)
            ->where($column, $authority->{$column} ?? null)->where('source', $authority->source);

        return $this->authorities->hasColumn('heading_normalized')
            ? $query->where('heading_normalized', AuthorityControlService::normalizeHeading($heading))->exists()
            : $query->where('heading', $heading)->exists();
    }

    /** Finds the neighbour by external id, then stored LCSH id, then unlinked heading, else creates it. */
    private function resolveNeighbour(array $reference, object $authority, array &$result): ?int
    {
        $scheme = LcshVocabularyClient::SCHEME;
        $known = DB::table('library_authority_record')
            ->where('scheme', $scheme)->where('external_id', $reference['external_id'])->value('authority_id');
        if ($known) {
            return (int) $known;
        }
        if ($this->authorities->hasColumn('lcsh_id')) {
            $legacy = DB::table('library_subject_authority')
                ->where(fn ($q) => $q->where('lcsh_id', $reference['external_id'])
                    ->orWhereIn('lcsh_uri', [$reference['uri'], preg_replace('#^http:#', 'https:', $reference['uri'])]))
                ->orderBy('id')->value('id');
            if ($legacy) {
                if ($this->records->get((int) $legacy)?->external_id) {
                    return null; // already another concept's record
                }
                $this->records->linkExternal((int) $legacy, $scheme, $reference['external_id']);

                return (int) $legacy;
            }
        }
        if (! $reference['label']) {
            return null;
        }

        $column = $this->authorities->typeColumn();
        $type = $authority->{$column} ?? 'topic';
        $query = DB::table('library_subject_authority')->where('source', $scheme)->where($column, $type);
        $this->authorities->hasColumn('heading_normalized')
            ? $query->where('heading_normalized', AuthorityControlService::normalizeHeading($reference['label']))
            : $query->where('heading', $reference['label']);
        $match = $query->value('id');

        if ($match) {
            $existing = $this->records->get((int) $match);
            if ($existing && $existing->external_id) {
                return null; // already another concept's record
            }
            $otherId = (int) $match;
        } else {
            $otherId = $this->authorities->create([
                'heading' => $reference['label'], 'subject_type' => $type, 'source' => $scheme, 'uri' => $reference['uri'],
            ]);
            $this->records->setType($otherId, $this->records->get((int) $authority->id)->record_type ?? AuthorityRecordService::DEFAULT_TYPE);
            $result['created']++;
        }

        $this->records->linkExternal($otherId, $scheme, $reference['external_id']);

        return $otherId;
    }

    private function stamp(int $id, ?string $error): void
    {
        DB::table('library_authority_record')->where('authority_id', $id)->update(array_filter([
            'last_synced_at' => $error === null ? now() : null,
        ]) + ['last_sync_error' => $error === null ? null : mb_substr($error, 0, 500), 'updated_at' => now()]);
    }
}
