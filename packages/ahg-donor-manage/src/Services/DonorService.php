<?php

/**
 * DonorService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DonorService
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    public function getBySlug(string $slug): ?object
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
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
        return DB::table('contact_information')
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
    }

    public function getRelatedAccessions(int $donorId): \Illuminate\Support\Collection
    {
        return DB::table('relation')
            ->join('accession', 'relation.object_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($j) {
                $j->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'accession.id', '=', 'slug.object_id')
            ->where('relation.subject_id', $donorId)
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
                $slug = $baseSlug . '-' . $counter++;
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

            if (!empty($data['contacts'])) {
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
            if (!empty($actorUpdate)) {
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
            if (!empty($i18n)) {
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
            if (!empty($contactIds)) {
                DB::table('contact_information_i18n')->whereIn('id', $contactIds)->delete();
                DB::table('contact_information')->whereIn('id', $contactIds)->delete();
            }

            $relationIds = DB::table('relation')->where('subject_id', $id)->orWhere('object_id', $id)->pluck('id')->toArray();
            if (!empty($relationIds)) {
                DB::table('relation_i18n')->whereIn('id', $relationIds)->delete();
                DB::table('relation')->whereIn('id', $relationIds)->delete();
                DB::table('slug')->whereIn('object_id', $relationIds)->delete();
                DB::table('object')->whereIn('id', $relationIds)->delete();
            }

            $noteIds = DB::table('note')->where('object_id', $id)->pluck('id')->toArray();
            if (!empty($noteIds)) {
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
            if ($this->isContactEmpty($c)) continue;
            $cid = DB::table('contact_information')->insertGetId([
                'actor_id' => $actorId, 'primary_contact' => !empty($c['primary_contact']) ? 1 : 0,
                'contact_person' => $c['contact_person'] ?? null, 'street_address' => $c['street_address'] ?? null,
                'website' => $c['website'] ?? null, 'email' => $c['email'] ?? null,
                'telephone' => $c['telephone'] ?? null, 'fax' => $c['fax'] ?? null,
                'postal_code' => $c['postal_code'] ?? null, 'country_code' => $c['country_code'] ?? null,
                'longitude' => $c['longitude'] ?? null, 'latitude' => $c['latitude'] ?? null,
                'contact_note' => $c['contact_note'] ?? null,
                'created_at' => now(), 'updated_at' => now(),
                'source_culture' => $this->culture, 'serial_number' => 0,
            ]);
            DB::table('contact_information_i18n')->insert([
                'id' => $cid, 'culture' => $this->culture,
                'contact_type' => $c['contact_type'] ?? null, 'city' => $c['city'] ?? null,
                'region' => $c['region'] ?? null, 'note' => $c['note'] ?? null,
            ]);
        }
    }

    protected function syncContacts(int $actorId, array $contacts): void
    {
        foreach ($contacts as $c) {
            if (!empty($c['delete']) && !empty($c['id'])) {
                DB::table('contact_information_i18n')->where('id', $c['id'])->delete();
                DB::table('contact_information')->where('id', $c['id'])->delete();
                continue;
            }
            if ($this->isContactEmpty($c)) continue;
            if (!empty($c['id'])) {
                DB::table('contact_information')->where('id', $c['id'])->update([
                    'primary_contact' => !empty($c['primary_contact']) ? 1 : 0,
                    'contact_person' => $c['contact_person'] ?? null, 'street_address' => $c['street_address'] ?? null,
                    'website' => $c['website'] ?? null, 'email' => $c['email'] ?? null,
                    'telephone' => $c['telephone'] ?? null, 'fax' => $c['fax'] ?? null,
                    'postal_code' => $c['postal_code'] ?? null, 'country_code' => $c['country_code'] ?? null,
                    'longitude' => $c['longitude'] ?? null, 'latitude' => $c['latitude'] ?? null,
                    'contact_note' => $c['contact_note'] ?? null,
                    'updated_at' => now(), 'serial_number' => DB::raw('serial_number + 1'),
                ]);
                $i18n = ['contact_type' => $c['contact_type'] ?? null, 'city' => $c['city'] ?? null, 'region' => $c['region'] ?? null, 'note' => $c['note'] ?? null];
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
            if (!empty($d[$f])) return false;
        }
        return true;
    }
}
