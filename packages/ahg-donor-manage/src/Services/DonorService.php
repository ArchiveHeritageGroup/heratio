<?php

/**
 * DonorService - Service for Heratio
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

namespace AhgDonorManage\Services;

use AhgCore\Constants\TermId;
use AhgCore\Services\EncryptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonorService
{
    protected string $culture;

    public function __construct(?string $culture = null)
    {
        $this->culture = $culture ?? (string) app()->getLocale();
    }

    /**
     * Typeahead search for donors by authorized form of name.
     *
     * Joins donor -> actor (parent_id = 3) -> actor_i18n (culture-aware, with a
     * COALESCE fallback to the source_culture row) -> slug. Returns rows shaped
     * for the YUI form-autocomplete widget that powers the accession "Related
     * donor" modal: the widget reads an HTML table where each row's first <td>
     * holds an <a href="VALUE">DISPLAY NAME</a>. The blade list template turns
     * each returned row into exactly that anchor, using 'slug' for the href
     * (value written into the hidden donor input) and 'label' for the link text.
     *
     * @return array<int,array{id:int,label:string,slug:?string}>
     */
    public function search(string $q, int $limit = 20): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        // Escape LIKE wildcards in the user term so '%' / '_' are literal.
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = '%'.$escaped.'%';

        $rows = DB::table('donor')
            ->join('actor', 'donor.id', '=', 'actor.id')
            ->join('object', 'donor.id', '=', 'object.id')
            ->leftJoin('slug', 'donor.id', '=', 'slug.object_id')
            // Preferred-culture i18n row.
            ->leftJoin('actor_i18n as ai', function ($j) {
                $j->on('donor.id', '=', 'ai.id')
                    ->where('ai.culture', '=', $this->culture);
            })
            // Source-culture i18n row, for the COALESCE fallback.
            ->leftJoin('actor_i18n as src', function ($j) {
                $j->on('donor.id', '=', 'src.id')
                    ->whereColumn('src.culture', 'actor.source_culture');
            })
            ->where('actor.parent_id', 3)
            ->where('object.class_name', 'QubitDonor')
            ->where(function ($w) use ($like) {
                $w->where('ai.authorized_form_of_name', 'LIKE', $like)
                    ->orWhere('src.authorized_form_of_name', 'LIKE', $like);
            })
            ->select([
                'donor.id',
                DB::raw('COALESCE(ai.authorized_form_of_name, src.authorized_form_of_name) as label'),
                'slug.slug',
            ])
            ->orderBy('label')
            ->limit($limit)
            ->get();

        return $rows->map(function ($r) {
            return [
                'id' => (int) $r->id,
                'label' => (string) ($r->label ?? ''),
                'slug' => $r->slug,
            ];
        })->all();
    }

    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (! $objectId) {
            return null;
        }

        return $this->getById($objectId);
    }

    public function getById(int $id): ?object
    {
        return DB::table('donor')
            ->join('actor', 'donor.id', '=', 'actor.id')
            ->join('object', 'donor.id', '=', 'object.id')
            ->join('slug', 'donor.id', '=', 'slug.object_id')
            ->leftJoin('actor_i18n', function ($j) {
                $j->on('donor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->where('donor.id', $id)
            ->where('object.class_name', 'QubitDonor')
            ->select([
                'donor.id',
                'actor.entity_type_id',
                'actor.description_identifier',
                'actor.corporate_body_identifiers',
                'actor.source_culture',
                'actor_i18n.authorized_form_of_name',
                'actor_i18n.dates_of_existence',
                'actor_i18n.history',
                'actor_i18n.places',
                'actor_i18n.legal_status',
                'actor_i18n.functions',
                'actor_i18n.mandates',
                'actor_i18n.internal_structures',
                'actor_i18n.general_context',
                'actor_i18n.institution_responsible_identifier',
                'actor_i18n.rules',
                'actor_i18n.sources',
                'actor_i18n.revision_history',
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();
    }

    public function getContacts(int $donorId): \Illuminate\Support\Collection
    {
        $rows = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($j) {
                $j->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $donorId)
            ->select([
                'contact_information.id', 'contact_information.primary_contact',
                'contact_information.contact_person', 'contact_information.street_address',
                'contact_information.website', 'contact_information.email',
                'contact_information.telephone', 'contact_information.fax',
                'contact_information.postal_code', 'contact_information.country_code',
                'contact_information.longitude', 'contact_information.latitude',
                'contact_information.contact_note',
                'contact_information_i18n.contact_type', 'contact_information_i18n.city',
                'contact_information_i18n.region', 'contact_information_i18n.note',
            ])
            ->get();

        // #1261: decrypt the registered contact_details PII columns. Mirrors
        // RepositoryService::getContacts - donor + repository share the base
        // contact_information table, so the same columns (email + city) are
        // encrypted on write and must be decrypted on read. decrypt() is a
        // pass-through for plaintext (no ENC2: sentinel), so legacy rows + rows
        // written while encryption was off both read correctly.
        $enc = new EncryptionService;
        foreach ($rows as $r) {
            if (! empty($r->email)) {
                $r->email = $enc->decrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, (string) $r->email, 'contact_information', 'email', $r->id);
            }
            if (! empty($r->city)) {
                $r->city = $enc->decrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, (string) $r->city, 'contact_information_i18n', 'city', $r->id);
            }
        }

        return $rows;
    }

    /**
     * Get archival description (information_object) records linked to this donor.
     * Relation: object_id = donor.id, subject_id = information_object.id, type_id = TermId::RELATION_DONOR.
     */
    public function getInformationObjects(int $donorId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('information_object', 'relation.subject_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('relation.object_id', $donorId)
            ->where('relation.type_id', TermId::RELATION_DONOR)
            ->select([
                'information_object.id',
                'information_object.identifier',
                'information_object_i18n.title',
                'slug.slug',
            ])
            ->get();
    }

    /**
     * Replace the donor↔IO relations with the given list of IO ids.
     * Relation: object_id = donor.id, subject_id = information_object.id, type_id = TermId::RELATION_DONOR.
     */
    public function syncInformationObjects(int $donorId, array $ioIds): void
    {
        $ioIds = array_values(array_unique(array_filter(array_map('intval', $ioIds))));

        DB::transaction(function () use ($donorId, $ioIds) {
            // Remove existing donor↔IO relations and their parent object rows
            $oldRelationIds = DB::table('relation')
                ->where('object_id', $donorId)
                ->where('type_id', TermId::RELATION_DONOR)
                ->whereIn('subject_id', function ($q) {
                    $q->select('id')->from('information_object');
                })
                ->pluck('id')
                ->toArray();
            if (! empty($oldRelationIds)) {
                DB::table('relation')->whereIn('id', $oldRelationIds)->delete();
                DB::table('object')->whereIn('id', $oldRelationIds)->delete();
            }

            foreach ($ioIds as $ioId) {
                $relObjectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitRelation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('relation')->insert([
                    'id' => $relObjectId,
                    'subject_id' => $ioId,
                    'object_id' => $donorId,
                    'type_id' => TermId::RELATION_DONOR,
                    'source_culture' => $this->culture,
                ]);
            }
        });
    }

    public function getRelatedAccessions(int $donorId): \Illuminate\Support\Collection
    {
        // AtoM: QubitRelation::getRelationsByObjectId($donorId, ['typeId' => QubitTerm::DONOR_ID])
        // relation.object_id = donor.id, relation.subject_id = accession.id, type_id = TermId::RELATION_DONOR
        return DB::table('relation')
            ->join('accession', 'relation.subject_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.object_id', $donorId)
            ->where('relation.type_id', TermId::RELATION_DONOR)
            ->select('accession.id', 'accession.identifier', 'accession_i18n.title', 'slug.slug')
            ->get();
    }

    public function create(array $data): int
    {
        return DB::transaction(function () use ($data) {
            $id = DB::table('object')->insertGetId([
                'class_name' => 'QubitDonor',
                'created_at' => now(),
                'updated_at' => now(),
                'serial_number' => 0,
            ]);

            $baseSlug = Str::slug($data['authorized_form_of_name'] ?? 'untitled');
            $slug = $baseSlug;
            $counter = 1;
            while (DB::table('slug')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$counter++;
            }
            DB::table('slug')->insert(['object_id' => $id, 'slug' => $slug]);

            DB::table('actor')->insert([
                'id' => $id,
                'entity_type_id' => $data['entity_type_id'] ?? null,
                'description_identifier' => $data['description_identifier'] ?? null,
                'corporate_body_identifiers' => $data['corporate_body_identifiers'] ?? null,
                'parent_id' => 3,
                'source_culture' => $this->culture,
            ]);

            DB::table('donor')->insert(['id' => $id]);

            DB::table('actor_i18n')->insert([
                'id' => $id,
                'culture' => $this->culture,
                'authorized_form_of_name' => $data['authorized_form_of_name'] ?? null,
                'dates_of_existence' => $data['dates_of_existence'] ?? null,
                'history' => $data['history'] ?? null,
                'places' => $data['places'] ?? null,
                'legal_status' => $data['legal_status'] ?? null,
                'functions' => $data['functions'] ?? null,
                'mandates' => $data['mandates'] ?? null,
                'internal_structures' => $data['internal_structures'] ?? null,
                'general_context' => $data['general_context'] ?? null,
                'institution_responsible_identifier' => $data['institution_responsible_identifier'] ?? null,
                'rules' => $data['rules'] ?? null,
                'sources' => $data['sources'] ?? null,
                'revision_history' => $data['revision_history'] ?? null,
            ]);

            if (! empty($data['contacts'])) {
                $this->saveContacts($id, $data['contacts']);
            }

            return $id;
        });
    }

    public function update(int $id, array $data): void
    {
        DB::transaction(function () use ($id, $data) {
            $actorUpdate = [];
            foreach (['entity_type_id', 'description_identifier', 'corporate_body_identifiers'] as $f) {
                if (array_key_exists($f, $data)) {
                    $actorUpdate[$f] = $data[$f];
                }
            }
            if (! empty($actorUpdate)) {
                DB::table('actor')->where('id', $id)->update($actorUpdate);
            }

            $i18nFields = [
                'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
                'legal_status', 'functions', 'mandates', 'internal_structures',
                'general_context', 'institution_responsible_identifier', 'rules',
                'sources', 'revision_history',
            ];
            $i18n = [];
            foreach ($i18nFields as $f) {
                if (array_key_exists($f, $data)) {
                    $i18n[$f] = $data[$f];
                }
            }
            if (! empty($i18n)) {
                $exists = DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->exists();
                if ($exists) {
                    DB::table('actor_i18n')->where('id', $id)->where('culture', $this->culture)->update($i18n);
                } else {
                    DB::table('actor_i18n')->insert(array_merge(['id' => $id, 'culture' => $this->culture], $i18n));
                }
            }

            if (array_key_exists('contacts', $data)) {
                $this->syncContacts($id, $data['contacts']);
            }

            DB::table('object')->where('id', $id)->update([
                'updated_at' => now(),
                'serial_number' => DB::raw('serial_number + 1'),
            ]);
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $contactIds = DB::table('contact_information')->where('actor_id', $id)->pluck('id')->toArray();
            if (! empty($contactIds)) {
                DB::table('contact_information_i18n')->whereIn('id', $contactIds)->delete();
                DB::table('contact_information')->whereIn('id', $contactIds)->delete();
            }

            $relationIds = DB::table('relation')->where('subject_id', $id)->orWhere('object_id', $id)->pluck('id')->toArray();
            if (! empty($relationIds)) {
                DB::table('relation_i18n')->whereIn('id', $relationIds)->delete();
                DB::table('relation')->whereIn('id', $relationIds)->delete();
                DB::table('slug')->whereIn('object_id', $relationIds)->delete();
                DB::table('object')->whereIn('id', $relationIds)->delete();
            }

            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (! empty($noteIds)) {
                DB::table('note_i18n')->whereIn('id', $noteIds)->delete();
                DB::table('note')->whereIn('id', $noteIds)->delete();
                DB::table('object')->whereIn('id', $noteIds)->delete();
            }

            DB::table('object_term_relation')->where('object_id', $id)->delete();
            DB::table('actor_i18n')->where('id', $id)->delete();
            DB::table('donor')->where('id', $id)->delete();
            DB::table('actor')->where('id', $id)->delete();
            DB::table('slug')->where('object_id', $id)->delete();
            DB::table('object')->where('id', $id)->delete();
        });
    }

    public function getSlug(int $id): ?string
    {
        return DB::table('slug')->where('object_id', $id)->value('slug');
    }

    protected function saveContacts(int $actorId, array $contacts): void
    {
        foreach ($contacts as $c) {
            if ($this->isContactEmpty($c)) {
                continue;
            }

            // #1261: encrypt the registered contact_details PII columns the same
            // way RepositoryService does (encrypt() self-gates on
            // encryption_enabled + the per-category flag, so it is a no-op /
            // plaintext when encryption is off). Only email + city are wide
            // enough to hold ENC2: ciphertext (~205-233 chars) and are the
            // columns registered in ahg_encrypted_fields.
            $enc = new EncryptionService;
            $emailEnc = $enc->encrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, $c['email'] ?? null, 'contact_information', 'email', null);

            $cid = DB::table('contact_information')->insertGetId([
                'actor_id' => $actorId, 'primary_contact' => ! empty($c['primary_contact']) ? 1 : 0,
                'contact_person' => $c['contact_person'] ?? null, 'street_address' => $c['street_address'] ?? null,
                'website' => $c['website'] ?? null, 'email' => $emailEnc,
                'telephone' => $c['telephone'] ?? null, 'fax' => $c['fax'] ?? null,
                'postal_code' => $c['postal_code'] ?? null, 'country_code' => $c['country_code'] ?? null,
                'longitude' => $c['longitude'] ?? null, 'latitude' => $c['latitude'] ?? null,
                'contact_note' => $c['contact_note'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
                'source_culture' => $this->culture, 'serial_number' => 0,
            ]);

            $cityEnc = $enc->encrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, $c['city'] ?? null, 'contact_information_i18n', 'city', $cid);

            DB::table('contact_information_i18n')->insert([
                'id' => $cid, 'culture' => $this->culture,
                'contact_type' => $c['contact_type'] ?? null, 'city' => $cityEnc,
                'region' => $c['region'] ?? null, 'note' => $c['note'] ?? null,
            ]);
        }
    }

    protected function syncContacts(int $actorId, array $contacts): void
    {
        foreach ($contacts as $c) {
            if (! empty($c['delete']) && ! empty($c['id'])) {
                // #1395(A) — contact_information is shared across all actors; only
                // delete a contact that actually belongs to THIS actor, so a
                // client-supplied id can't tamper with another actor's PII.
                if (DB::table('contact_information')->where('id', $c['id'])->where('actor_id', $actorId)->exists()) {
                    DB::table('contact_information_i18n')->where('id', $c['id'])->delete();
                    DB::table('contact_information')->where('id', $c['id'])->delete();
                }

                continue;
            }
            if ($this->isContactEmpty($c)) {
                continue;
            }
            if (! empty($c['id'])) {
                // #1261: encrypt PII on update (email + city), mirroring
                // RepositoryService::syncContacts. encrypt() self-gates.
                $enc = new EncryptionService;
                $emailEnc = $enc->encrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, $c['email'] ?? null, 'contact_information', 'email', $c['id']);

                DB::table('contact_information')->where('id', $c['id'])->where('actor_id', $actorId)->update([ // #1395(A) scope to this actor
                    'primary_contact' => ! empty($c['primary_contact']) ? 1 : 0,
                    'contact_person' => $c['contact_person'] ?? null, 'street_address' => $c['street_address'] ?? null,
                    'website' => $c['website'] ?? null, 'email' => $emailEnc,
                    'telephone' => $c['telephone'] ?? null, 'fax' => $c['fax'] ?? null,
                    'postal_code' => $c['postal_code'] ?? null, 'country_code' => $c['country_code'] ?? null,
                    'longitude' => $c['longitude'] ?? null, 'latitude' => $c['latitude'] ?? null,
                    'contact_note' => $c['contact_note'] ?? null,
                    'updated_at' => now(), 'serial_number' => DB::raw('serial_number + 1'),
                ]);
                $cityEnc = $enc->encrypt(EncryptionService::CATEGORY_CONTACT_DETAILS, $c['city'] ?? null, 'contact_information_i18n', 'city', $c['id']);
                $i18n = ['contact_type' => $c['contact_type'] ?? null, 'city' => $cityEnc, 'region' => $c['region'] ?? null, 'note' => $c['note'] ?? null];
                $exists = DB::table('contact_information_i18n')->where('id', $c['id'])->where('culture', $this->culture)->exists();
                if ($exists) {
                    DB::table('contact_information_i18n')->where('id', $c['id'])->where('culture', $this->culture)->update($i18n);
                } else {
                    DB::table('contact_information_i18n')->insert(array_merge(['id' => $c['id'], 'culture' => $this->culture], $i18n));
                }
            } else {
                $this->saveContacts($actorId, [$c]);
            }
        }
    }

    protected function isContactEmpty(array $d): bool
    {
        foreach (['contact_person', 'street_address', 'website', 'email', 'telephone', 'fax', 'city', 'region', 'postal_code', 'country_code'] as $f) {
            if (! empty($d[$f])) {
                return false;
            }
        }

        return true;
    }
}
