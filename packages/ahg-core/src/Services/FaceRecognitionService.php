<?php

/**
 * FaceRecognitionService - Heratio
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
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgCore\Services;

use AhgAuditTrail\Services\AuditLogger;
use AhgCore\Constants\TermId;
use AhgPrivacy\Services\FileScreeningGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Face recognition over the faces FaceDetectionService stores (heratio#1764).
 *
 *  - People: face_person rows, optionally the authority record (actor) they are.
 *  - Naming: staff assign a face to a person. Matching: other unassigned
 *    faces whose embedding is close (cosine similarity at or above
 *    face_match_threshold, default 0.6) are offered as suggestions, which
 *    staff confirm or reject. Nothing is assigned without a person doing it.
 *  - A named or confirmed face links its record to the person, and - when the
 *    person has an authority record - adds that actor as a name access point
 *    (relation type 161) in the same transaction.
 *  - Search: the records (and video times) where a person's faces appear,
 *    and a search by an uploaded photo.
 *
 * Embeddings are stored unit-length as packed float32, so cosine similarity
 * is a dot product. Matching scans candidate rows in id-ordered batches in
 * PHP. ponytail: a full scan per query; fine to tens of thousands of faces,
 * move to a vector index (Qdrant through the gateway) beyond that.
 *
 * Every naming, confirming, rejecting, unassigning and purging action is
 * written to the audit log (ahg_audit_log).
 */
class FaceRecognitionService
{
    public const DEFAULT_THRESHOLD = 0.6;

    private const BATCH = 1000;

    /** Reference faces per person used for matching (most recent first). */
    private const MAX_REFERENCES = 50;

    // ── settings ────────────────────────────────────────────────────────

    /** face_enabled off switches face processing off entirely: no detection, naming, matching or search. */
    public function enabled(): bool
    {
        return AhgSettingsService::getBool('face_enabled', false);
    }

    /** Similarity at or above which a face is suggested as a match (approximate; tune per model). */
    public function threshold(): float
    {
        $v = (float) AhgSettingsService::get('face_match_threshold', (string) self::DEFAULT_THRESHOLD);

        return $v > 0 && $v <= 1 ? $v : self::DEFAULT_THRESHOLD;
    }

    /** Off by default: person search pages are staff-only unless face_search_public is on. */
    public function publicSearch(): bool
    {
        return AhgSettingsService::getBool('face_search_public', false);
    }

    // ── vectors ─────────────────────────────────────────────────────────

    /** Normalise to unit length and pack as little-endian float32. */
    public static function pack(array $vector): string
    {
        $v = array_map('floatval', array_values($vector));
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));
        if ($norm > 0) {
            $v = array_map(fn ($x) => $x / $norm, $v);
        }

        return pack('g*', ...$v);
    }

    /** @return list<float> */
    public static function unpack(?string $bin): array
    {
        return $bin === null || $bin === '' ? [] : array_values(unpack('g*', $bin));
    }

    public static function dot(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        $s = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $s += $a[$i] * $b[$i];
        }

        return $s;
    }

    /**
     * Stored faces (unassigned only, unless $unassignedOnly is false) ranked
     * by their best similarity to any of $refs, at or above the threshold,
     * best first.
     *
     * @param  list<list<float>>  $refs  unit vectors
     * @param  int[]  $excludeFaceIds
     * @return list<array{face_id: int, similarity: float}>
     */
    public function rank(array $refs, array $excludeFaceIds = [], int $limit = 100, bool $unassignedOnly = true): array
    {
        $refs = array_values(array_filter($refs));
        if ($refs === []) {
            return [];
        }
        $dim = count($refs[0]);
        $exclude = array_flip($excludeFaceIds);
        $threshold = $this->threshold();
        $hits = [];

        DB::table('digital_object_faces')->whereNotNull('embedding')->where('embedding_dim', $dim)
            ->when($unassignedOnly, fn ($q) => $q->whereNull('person_id'))
            ->select('id', 'embedding')
            ->chunkById(self::BATCH, function ($rows) use ($refs, $exclude, $threshold, &$hits) {
                foreach ($rows as $r) {
                    if (isset($exclude[$r->id])) {
                        continue;
                    }
                    $v = self::unpack($r->embedding);
                    $best = -1.0;
                    foreach ($refs as $ref) {
                        $best = max($best, self::dot($ref, $v));
                    }
                    if ($best >= $threshold) {
                        $hits[] = ['face_id' => (int) $r->id, 'similarity' => round($best, 4)];
                    }
                }
            });

        usort($hits, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        return array_slice($hits, 0, $limit);
    }

    // ── people ──────────────────────────────────────────────────────────

    public function createPerson(string $name, ?int $actorId = null, ?string $notes = null): int
    {
        $id = (int) DB::table('face_person')->insertGetId([
            'name' => trim($name), 'actor_id' => $actorId ?: null, 'notes' => $notes,
            'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit('create', 'face_person', $id, ['name' => trim($name), 'actor_id' => $actorId]);

        return $id;
    }

    /**
     * Rename a person or change the authority record they are. A newly set
     * actor becomes a name access point on every record with a confirmed face.
     */
    public function updatePerson(int $personId, string $name, ?int $actorId, ?string $notes = null): void
    {
        $old = DB::table('face_person')->where('id', $personId)->first();
        if (! $old) {
            return;
        }
        DB::transaction(function () use ($personId, $name, $actorId, $notes, $old) {
            DB::table('face_person')->where('id', $personId)->update([
                'name' => trim($name), 'actor_id' => $actorId ?: null, 'notes' => $notes, 'updated_at' => now(),
            ]);
            if ($actorId && (int) $old->actor_id !== $actorId) {
                $faceIds = DB::table('digital_object_faces')->where('person_id', $personId)->where('match_verified', 1)->pluck('id')->all();
                DB::table('digital_object_faces')->whereIn('id', $faceIds)->update(['matched_actor_id' => $actorId]);
                foreach ($this->recordIdsOfFaces($faceIds) as $io) {
                    $this->linkAccessPoint($io, $actorId);
                }
            }
        });
        $this->audit('update', 'face_person', $personId, ['name' => trim($name), 'actor_id' => $actorId, 'old_name' => $old->name, 'old_actor_id' => $old->actor_id]);
    }

    public function person(int $personId): ?object
    {
        return DB::table('face_person as p')
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('ai.id', '=', 'p.actor_id')->where('ai.culture', '=', 'en');
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'p.actor_id')
            ->where('p.id', $personId)
            ->first(['p.*', 'ai.authorized_form_of_name as actor_name', 's.slug as actor_slug']);
    }

    /** People with their confirmed-face counts. */
    public function people(): array
    {
        return DB::table('face_person as p')
            ->leftJoin('digital_object_faces as f', function ($j) {
                $j->on('f.person_id', '=', 'p.id')->where('f.match_verified', '=', 1);
            })
            ->groupBy('p.id', 'p.name', 'p.actor_id')
            ->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.actor_id', DB::raw('COUNT(f.id) as faces')])->all();
    }

    // ── naming, confirming, rejecting ──────────────────────────────────

    /** Staff name a face: assign it to a person. */
    public function nameFace(int $faceId, int $personId): bool
    {
        return $this->assign($faceId, $personId, 'manual', null, 'face_named');
    }

    /** Staff confirm a suggested match. */
    public function confirm(int $faceId, int $personId, ?float $similarity = null): bool
    {
        return $this->assign($faceId, $personId, 'verified', $similarity, 'face_confirmed');
    }

    /** Staff reject a suggestion: this face is not this person, never suggest it again. */
    public function reject(int $faceId, int $personId): void
    {
        DB::table('face_match_rejection')->insertOrIgnore([
            'face_id' => $faceId, 'person_id' => $personId, 'rejected_by' => auth()->id(), 'created_at' => now(),
        ]);
        $this->audit('face_rejected', 'digital_object_face', $faceId, ['person_id' => $personId]);
    }

    /**
     * Take a face back off a person. A name access point added earlier stays
     * on the record (it may have been there before, and is description, not
     * face data); staff remove it on the record if it is wrong.
     */
    public function unassign(int $faceId): void
    {
        $face = DB::table('digital_object_faces')->where('id', $faceId)->first();
        if (! $face || ! $face->person_id) {
            return;
        }
        DB::table('digital_object_faces')->where('id', $faceId)->update([
            'person_id' => null, 'is_identified' => 0, 'match_verified' => 0, 'identification_source' => null,
            'matched_actor_id' => null, 'match_similarity' => null, 'identified_by' => auth()->id(), 'identified_at' => now(),
        ]);
        $this->audit('face_unassigned', 'digital_object_face', $faceId, ['person_id' => (int) $face->person_id]);
    }

    private function assign(int $faceId, int $personId, string $source, ?float $similarity, string $action): bool
    {
        $face = DB::table('digital_object_faces as f')->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->where('f.id', $faceId)->first(['f.id', 'd.object_id']);
        $person = DB::table('face_person')->where('id', $personId)->first();
        if (! $face || ! $person) {
            return false;
        }

        DB::transaction(function () use ($face, $person, $faceId, $source, $similarity) {
            DB::table('digital_object_faces')->where('id', $faceId)->update([
                'person_id' => $person->id, 'is_identified' => 1, 'match_verified' => 1,
                'identification_source' => $source, 'match_similarity' => $similarity,
                'matched_actor_id' => $person->actor_id ?: null,
                'identified_by' => auth()->id(), 'identified_at' => now(),
            ]);
            if ($person->actor_id && $face->object_id) {
                $this->linkAccessPoint((int) $face->object_id, (int) $person->actor_id);
            }
        });
        $this->audit($action, 'digital_object_face', $faceId, [
            'person_id' => (int) $person->id, 'information_object_id' => (int) $face->object_id,
            'actor_id' => $person->actor_id ? (int) $person->actor_id : null, 'similarity' => $similarity,
        ]);

        return true;
    }

    /** The actor as a name access point on the record (relation type 161), unless it already is. Caller holds the transaction. */
    private function linkAccessPoint(int $ioId, int $actorId): void
    {
        $exists = DB::table('relation')->where('subject_id', $ioId)->where('object_id', $actorId)
            ->where('type_id', TermId::RELATION_NAME_ACCESS_POINT)->exists();
        if ($exists) {
            return;
        }
        $id = DB::table('object')->insertGetId(['class_name' => 'QubitRelation', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('relation')->insert([
            'id' => $id, 'subject_id' => $ioId, 'object_id' => $actorId,
            'type_id' => TermId::RELATION_NAME_ACCESS_POINT, 'source_culture' => app()->getLocale() ?: 'en',
        ]);
    }

    // ── matching ───────────────────────────────────────────────────────

    /**
     * Unassigned faces that look like this person, best first, leaving out
     * the ones staff already rejected for them.
     *
     * @return list<array{face_id: int, similarity: float}>
     */
    public function suggestions(int $personId, int $limit = 100): array
    {
        $refs = DB::table('digital_object_faces')->where('person_id', $personId)->where('match_verified', 1)
            ->whereNotNull('embedding')->orderByDesc('id')->limit(self::MAX_REFERENCES)->pluck('embedding')
            ->map(fn ($b) => self::unpack($b))->all();
        $rejected = DB::table('face_match_rejection')->where('person_id', $personId)->pluck('face_id')->map('intval')->all();

        return $this->rank($refs, $rejected, $limit);
    }

    /**
     * Search by photo: detect faces in the uploaded image through the gateway
     * backend, then rank stored faces against them.
     *
     * @return array{detected: int, matches: list<array{face_id: int, similarity: float}>}
     */
    public function searchByPhoto(string $imagePath, int $limit = 100): array
    {
        $faces = app(FaceDetectionService::class)->detectFile(FaceDetectionService::BACKEND_GATEWAY, $imagePath);
        $refs = [];
        foreach ($faces as $f) {
            if ($f['embedding']) {
                $refs[] = self::unpack(self::pack($f['embedding']));
            }
        }
        $matches = [];
        if ($refs !== []) {
            // Assigned faces are candidates too: a photo search wants everyone it may be.
            $matches = $this->rank($refs, [], $limit, false);
        }

        return ['detected' => count($faces), 'matches' => $matches];
    }

    // ── search ─────────────────────────────────────────────────────────

    /**
     * "Find all items featuring this person": the records where the
     * person's confirmed faces appear, with the video times. $public: the
     * caller is not staff, so only records DisclosureGate allows and
     * FileScreeningGate does not withhold are listed.
     *
     * @return list<object{io_id: int, title: ?string, slug: ?string, times: list<float>, faces: int}>
     */
    public function itemsForPerson(int $personId, bool $public): array
    {
        $rows = DB::table('digital_object_faces as f')->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->where('f.person_id', $personId)->where('f.match_verified', 1)
            ->orderBy('f.frame_time_seconds')->get(['f.id', 'f.frame_time_seconds', 'd.object_id']);

        return $this->groupByRecord($rows, $public);
    }

    /**
     * Faces with their record, for suggestion and photo-search hits.
     *
     * @param  list<array{face_id: int, similarity: float}>  $hits
     * @return list<object>
     */
    public function describeHits(array $hits, bool $public = false): array
    {
        if ($hits === []) {
            return [];
        }
        $sim = array_column($hits, 'similarity', 'face_id');
        $rows = DB::table('digital_object_faces as f')->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->leftJoin('face_person as p', 'p.id', '=', 'f.person_id')
            ->whereIn('f.id', array_keys($sim))
            ->get(['f.id', 'f.frame_time_seconds', 'f.bounding_box', 'f.person_id', 'p.name as person_name', 'd.object_id', 'd.id as do_id'])->all();
        $allowed = $this->allowedRecords(array_map(fn ($r) => (int) $r->object_id, $rows), $public);
        $meta = $this->recordMeta($allowed);
        $out = [];
        foreach ($rows as $r) {
            if (! isset($meta[(int) $r->object_id])) {
                continue;
            }
            $r->similarity = $sim[$r->id];
            $r->title = $meta[(int) $r->object_id]->title;
            $r->slug = $meta[(int) $r->object_id]->slug;
            $out[] = $r;
        }
        usort($out, fn ($a, $b) => $b->similarity <=> $a->similarity);

        return $out;
    }

    private function groupByRecord($rows, bool $public): array
    {
        $by = [];
        foreach ($rows as $r) {
            $io = (int) $r->object_id;
            $by[$io] ??= ['times' => [], 'faces' => 0];
            $by[$io]['faces']++;
            if ($r->frame_time_seconds !== null) {
                $by[$io]['times'][] = (float) $r->frame_time_seconds;
            }
        }
        $allowed = $this->allowedRecords(array_keys($by), $public);
        $meta = $this->recordMeta($allowed);
        $out = [];
        foreach ($allowed as $io) {
            $out[] = (object) ['io_id' => $io, 'title' => $meta[$io]->title ?? null, 'slug' => $meta[$io]->slug ?? null,
                'times' => array_values(array_unique($by[$io]['times'])), 'faces' => $by[$io]['faces']];
        }

        return $out;
    }

    /** Staff see every record; the public only what DisclosureGate allows and FileScreeningGate does not withhold. */
    private function allowedRecords(array $ioIds, bool $public): array
    {
        $ioIds = array_values(array_unique(array_filter(array_map('intval', $ioIds))));
        if (! $public) {
            return $ioIds;
        }
        $ioIds = app(DisclosureGate::class)->filterIds($ioIds);
        if (class_exists(FileScreeningGate::class)) {
            $screening = app(FileScreeningGate::class);
            $ioIds = array_values(array_filter($ioIds, fn ($id) => ! $screening->withholds($id)));
        }

        return $ioIds;
    }

    /** @return array<int, object{title: ?string, slug: ?string}> */
    private function recordMeta(array $ioIds): array
    {
        if ($ioIds === []) {
            return [];
        }
        $culture = app()->getLocale() ?: 'en';

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as cur', function ($j) use ($culture) {
                $j->on('cur.id', '=', 'io.id')->where('cur.culture', '=', $culture);
            })
            ->leftJoin('information_object_i18n as src', function ($j) {
                $j->on('src.id', '=', 'io.id')->whereColumn('src.culture', 'io.source_culture');
            })
            ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
            ->whereIn('io.id', $ioIds)
            ->get(['io.id', DB::raw('COALESCE(cur.title, src.title) as title'), 's.slug'])
            ->keyBy('id')->all();
    }

    private function recordIdsOfFaces(array $faceIds): array
    {
        if ($faceIds === []) {
            return [];
        }

        return DB::table('digital_object_faces as f')->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->whereIn('f.id', $faceIds)->distinct()->pluck('d.object_id')->map('intval')->filter()->all();
    }

    // ── privacy ────────────────────────────────────────────────────────

    /**
     * Data-subject request: delete one person's face data - every face
     * assigned to them (box and embedding), their rejections and the person
     * row. Name access points on records are description, not face data, and
     * stay. Faces of the person nobody has named cannot be found by name:
     * find them first (photo search) and name them, then purge.
     *
     * @return array{faces: int, rejections: int}
     */
    public function purgePerson(int $personId): array
    {
        $person = DB::table('face_person')->where('id', $personId)->first();
        if (! $person) {
            return ['faces' => 0, 'rejections' => 0];
        }
        $counts = DB::transaction(function () use ($personId) {
            $faces = DB::table('digital_object_faces')->where('person_id', $personId)->delete();
            $rejections = DB::table('face_match_rejection')->where('person_id', $personId)->delete();
            DB::table('face_person')->where('id', $personId)->delete();

            return ['faces' => $faces, 'rejections' => $rejections];
        });
        $this->audit('face_person_purged', 'face_person', $personId, $counts + ['name' => $person->name, 'actor_id' => $person->actor_id]);

        return $counts;
    }

    private function audit(string $action, string $entityType, int $entityId, array $meta): void
    {
        if (! class_exists(AuditLogger::class)) {
            return;
        }
        try {
            $logger = app()->bound(AuditLogger::class)
                ? app(AuditLogger::class) : new AuditLogger;
            $logger->logAction($action, $entityType, $entityId, $meta + ['source' => 'face-recognition']);
        } catch (\Throwable $e) {
            Log::warning('[face-recognition] audit write failed: '.$e->getMessage());
        }
    }
}
