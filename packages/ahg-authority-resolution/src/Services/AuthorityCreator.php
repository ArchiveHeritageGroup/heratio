<?php

/**
 * AuthorityCreator - Heratio
 *
 * Task 6 of the AHG Authority Resolution Engine. Creates new authority
 * records (actors for PERSON/ORG, terms for PLACE) via the Qubit class-
 * table-inheritance pattern. Matches the inserts AiController::
 * nerCreateActor / nerCreatePlace already do in the locked ahg-ai-services
 * package - we do not call those methods (we'd reach across a lock); we
 * mirror the same DB shape from this side instead.
 *
 * ISAAR-CPF mandatory fields enforced for persons / orgs:
 *   - authorized_form_of_name (non-empty)
 *   - dates_of_existence      (non-empty)
 *   - history                 (non-empty)
 *   - descriptive_standard    (default 'ISAAR-CPF' if not supplied)
 *
 * For places (ISDF): coordinates required only when one was supplied;
 * name is mandatory.
 *
 * All inserts wrap in a single DB::transaction so a partial failure leaves
 * no orphaned object / slug rows behind.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgAuthorityResolution\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthorityCreator
{
    private const CORPORATE_BODY_ID = 131;

    private const PERSON_ID = 132;

    private const TAXONOMY_PLACE_ID = 42;

    public function createPerson(array $form, int $userId, string $culture = 'en'): int
    {
        $this->assertIsaarCpf($form, false);

        return DB::transaction(function () use ($form, $culture) {
            return $this->insertActor($form, self::PERSON_ID, $culture);
        });
    }

    public function createOrg(array $form, int $userId, string $culture = 'en'): int
    {
        $this->assertIsaarCpf($form, true);

        return DB::transaction(function () use ($form, $culture) {
            return $this->insertActor($form, self::CORPORATE_BODY_ID, $culture);
        });
    }

    public function createPlace(array $form, int $userId, string $culture = 'en'): int
    {
        $name = trim((string) ($form['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Place name is required.');
        }
        // ISDF: coordinates optional - but if one is provided both must be.
        $lat = $form['latitude'] ?? null;
        $lng = $form['longitude'] ?? null;
        if (($lat !== null && $lat !== '' && ($lng === null || $lng === ''))
            || ($lng !== null && $lng !== '' && ($lat === null || $lat === ''))) {
            throw new \InvalidArgumentException('Latitude and longitude must be provided together (or both omitted).');
        }

        return DB::transaction(function () use ($name, $culture) {
            $termId = DB::table('object')->insertGetId([
                'class_name' => 'QubitTerm',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('term')->insert([
                'id' => $termId,
                'taxonomy_id' => self::TAXONOMY_PLACE_ID,
                'source_culture' => $culture,
            ]);

            DB::table('term_i18n')->insert([
                'id' => $termId,
                'culture' => $culture,
                'name' => $name,
            ]);

            DB::table('slug')->insert([
                'object_id' => $termId,
                'slug' => $this->generateUniqueSlug($name),
            ]);

            return $termId;
        });
    }

    /**
     * @return int new actor.id
     */
    private function insertActor(array $form, int $entityTypeId, string $culture): int
    {
        $actorId = DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $descriptiveStandard = (string) ($form['descriptive_standard'] ?? 'ISAAR-CPF');

        DB::table('actor')->insert([
            'id' => $actorId,
            'entity_type_id' => $entityTypeId,
            'source_culture' => $culture,
            // ISAAR-CPF "Description Standard" lives on actor.source_standard.
            'source_standard' => $descriptiveStandard,
        ]);

        $name = (string) $form['authorized_form_of_name'];
        $dates = isset($form['dates_of_existence']) ? (string) $form['dates_of_existence'] : null;
        $history = isset($form['history']) ? (string) $form['history'] : null;

        DB::table('actor_i18n')->insert([
            'id' => $actorId,
            'culture' => $culture,
            'authorized_form_of_name' => $name,
            'dates_of_existence' => $dates,
            'history' => $history,
        ]);

        DB::table('slug')->insert([
            'object_id' => $actorId,
            'slug' => $this->generateUniqueSlug($name),
        ]);

        return $actorId;
    }

    private function assertIsaarCpf(array $form, bool $isOrg): void
    {
        $missing = [];
        foreach (['authorized_form_of_name', 'dates_of_existence', 'history'] as $key) {
            if (! isset($form[$key]) || trim((string) $form[$key]) === '') {
                $missing[] = $key;
            }
        }
        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing ISAAR-CPF mandatory field(s) for '.($isOrg ? 'organisation' : 'person')
                .': '.implode(', ', $missing)
            );
        }
    }

    private function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $slug = 'untitled';
        }
        $base = $slug;
        $counter = 1;
        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter;
            $counter++;
            if ($counter > 1000) {
                // Defensive: append a random suffix rather than loop forever.
                $slug = $base.'-'.substr(bin2hex(random_bytes(4)), 0, 6);
                break;
            }
        }

        return $slug;
    }
}
