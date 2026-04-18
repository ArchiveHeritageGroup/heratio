<?php

/*
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Seed a coherent mini-fonds into a fresh OpenRiC server so /demo/browse/
 * has something to show. Idempotent — re-running skips entities whose
 * slugs already exist, so it's safe in dev loops.
 *
 * Usage:
 *   php artisan openric:seed-demo                 # create the demo fonds
 *   php artisan openric:seed-demo --drop          # delete the demo fonds first (by slug prefix)
 *   php artisan openric:seed-demo --dry-run       # report intended creates, no writes
 */

namespace AhgRic\Console\Commands;

use AhgRic\Services\RicEntityService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDemo extends Command
{
    protected $signature = 'openric:seed-demo {--drop : Delete the demo fonds first} {--dry-run : Report without writing}';
    protected $description = 'Seed a coherent mini-fonds for /demo/browse/ — Repository, Agent, Place, Activity, Rule, Function, Records, Instantiation + relations.';

    private const SLUG_PREFIX = 'openric-demo';
    private RicEntityService $svc;

    // IDs of the entities we create, keyed by our internal symbolic name.
    private array $ids = [];

    public function handle(): int
    {
        $this->svc = new RicEntityService();
        $dry = (bool) $this->option('dry-run');

        if ($this->option('drop')) {
            return $this->drop();
        }

        $this->info('Seeding OpenRiC demo fonds' . ($dry ? ' [dry-run]' : '') . '…');

        // Each entry: [internal-key, type, slug, data, description].
        // Ordering matters — later entries reference earlier ones by internal key.
        $plan = [
            // Repository (custodial institution)
            ['repo', 'repository', 'openric-demo-archive', [
                'name'                => 'OpenRiC Demo Archive',
                'identifier'          => 'ORDA',
                'history'             => 'A small curated collection assembled to showcase the OpenRiC data model end-to-end. Not a real institution.',
                'geocultural_context' => 'Stellenbosch, Western Cape, South Africa — emblematic of the wine-country valley archives.',
                'collecting_policies' => 'Family papers, farm records, and botanical field notes from the Cape wine-growing region, 1860 – 1960.',
                'buildings'           => 'A single reading room above the bakery on Dorp Street.',
                'holdings'            => 'Approximately 20 linear metres of mixed paper and a few hundred glass-plate negatives.',
                'opening_times'       => 'Tues / Thurs 10:00 – 16:00 by appointment.',
                'access_conditions'   => 'Open to researchers on written request. Photography permitted without flash.',
            ]],

            // Place (geographic context)
            ['place', 'place', 'openric-demo-stellenbosch', [
                'name'          => 'Stellenbosch (OpenRiC demo)',
                'latitude'      => -33.9322,
                'longitude'     => 18.8602,
                'authority_uri' => 'https://www.geonames.org/964137/stellenbosch.html',
                'address'       => 'Western Cape, South Africa',
                'description'   => 'University town ~50 km east of Cape Town, centre of the Stellenbosch wine-growing district since the 17th century.',
            ]],

            // Agent (creator)
            ['agent', 'agent', 'openric-demo-agent-leroux', [
                'name'               => 'Johanna le Roux (OpenRiC demo)',
                'dates_of_existence' => '1872 – 1958',
                'history'            => 'Botanical illustrator and amateur archivist. Documented the fynbos of the Jonkershoek valley between 1898 and 1940 in a series of field journals, maintained family correspondence with cousins in Paarl and Swellendam, and kept meticulous farm-account ledgers for the family vineyard.',
                'places'             => 'Stellenbosch, Jonkershoek',
                'functions'          => 'Botanical illustration; family correspondence; vineyard accounting.',
            ]],

            // Rule (mandate governing retention / copyright)
            ['rule', 'rule', 'openric-demo-rule-retention', [
                'title'         => 'Family Papers Retention Policy (OpenRiC demo)',
                'jurisdiction'  => 'Internal — OpenRiC Demo Archive',
                'start_date'    => '2026-01-01',
                'description'   => 'Original family papers accepted by the OpenRiC Demo Archive are retained indefinitely. Digital surrogates are refreshed to current master formats on a five-year cycle. Access copies are re-derived from masters on demand.',
                'sources'       => 'Cape Wine Valley Archives Consortium, retention guidelines 2024 edition.',
            ]],

            // Function (what the records support)
            ['fn', 'function', 'openric-demo-fn-correspondence', [
                'name'        => 'Family Correspondence (OpenRiC demo)',
                'description' => 'The administrative function of maintaining personal and professional correspondence with family members, colleagues in the botanical illustration community, and suppliers of vineyard goods.',
                'dates'       => '1898 – 1958',
            ]],

            // Records — top-level fonds + two child series
            ['rec_fonds', 'record', 'openric-demo-leroux-papers', [
                'title'             => 'Johanna le Roux Papers (OpenRiC demo fonds)',
                'identifier'        => 'ORDA-1',
                'scope_and_content' => 'The personal papers of Johanna le Roux, botanical illustrator of Stellenbosch, comprising correspondence, field journals, and farm ledgers, 1898 – 1958.',
                'extent_and_medium' => '1 archive box; approx. 0.5 linear metres of paper plus 120 glass-plate negatives.',
                'archival_history'  => 'Accessioned 2026 by the OpenRiC Demo Archive from family descendants.',
                'arrangement'       => 'Arranged in three series: correspondence, field journals, accounts.',
                'access_conditions' => 'Open. Originals consulted in the reading room only.',
            ]],

            ['rec_corr', 'record', 'openric-demo-leroux-corr', [
                'title'             => 'Correspondence series (OpenRiC demo)',
                'identifier'        => 'ORDA-1-A',
                'scope_and_content' => 'Incoming and outgoing correspondence, arranged chronologically by year and thereunder alphabetically by correspondent.',
                'extent_and_medium' => 'Approximately 400 letters across 24 folders.',
            ]],

            ['rec_journals', 'record', 'openric-demo-leroux-journals', [
                'title'             => 'Botanical field journals series (OpenRiC demo)',
                'identifier'        => 'ORDA-1-B',
                'scope_and_content' => 'Eighteen bound field journals documenting fynbos observations in the Jonkershoek valley, 1898 – 1940, with watercolour plates.',
                'extent_and_medium' => '18 bound volumes, approx. 2000 pages of manuscript and 320 watercolours.',
            ]],

            // Activity (creation event — the field journals were produced)
            ['activity', 'activity', 'openric-demo-activity-journaling', [
                'name'         => 'Field-journaling activity (OpenRiC demo)',
                'date_display' => 'c. 1898 – 1940',
                'start_date'   => '1898-03-15',
                'end_date'     => '1940-11-02',
                'description'  => 'Regular walking-and-recording trips into the Jonkershoek mountains to observe, sketch, and annotate fynbos specimens across seasons.',
            ]],

            // Instantiation (a digital image surrogate)
            ['inst', 'instantiation', 'openric-demo-inst-watercolour-protea', [
                'title'         => 'Protea neriifolia watercolour (OpenRiC demo)',
                'carrier_type'  => 'Digital image',
                'mime_type'     => 'image/jpeg',
                'extent_value'  => 1200,
                'extent_unit'   => 'KB',
                'content_url'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/5d/Protea_neriifolia_2.jpg/1024px-Protea_neriifolia_2.jpg',
                'description'   => 'Scan of a watercolour plate from the 1912 journal depicting Protea neriifolia flowering in the upper Jonkershoek. Source image is a public-domain Wikimedia upload, used here to stand in for a real digitised watercolour.',
            ]],
        ];

        if ($dry) {
            foreach ($plan as [$key, $type, $slug]) {
                $this->line("  would create  {$type}  slug={$slug}  (key={$key})");
            }
            $this->info('--dry-run: no writes performed. Re-run without --dry-run to apply.');
            return self::SUCCESS;
        }

        foreach ($plan as [$key, $type, $slug, $data]) {
            $this->ids[$key] = $this->ensure($type, $slug, $data);
        }

        $this->seedRelations();

        $this->newLine();
        $this->info('Demo fonds seeded.');
        $this->line('  Repository  id=' . $this->ids['repo']      . '  slug=openric-demo-archive');
        $this->line('  Fonds       id=' . $this->ids['rec_fonds'] . '  slug=openric-demo-leroux-papers');
        $this->line('  Agent       id=' . $this->ids['agent']     . '  slug=openric-demo-agent-leroux');
        $this->line('  Place       id=' . $this->ids['place']     . '  slug=openric-demo-stellenbosch');
        $this->newLine();
        $this->info('Browse: https://openric.org/demo/browse/');
        $this->info('Graph:  https://viewer.openric.org/ → Start /default/recordset/' . $this->ids['rec_fonds']);
        return self::SUCCESS;
    }

    /**
     * Create the entity if its slug is free; otherwise return the existing id.
     */
    private function ensure(string $type, string $slug, array $data): int
    {
        $existingId = (int) DB::table('slug')->where('slug', $slug)->value('object_id');
        if ($existingId) {
            $this->line("  exists  {$type}  slug={$slug}  id={$existingId}");
            return $existingId;
        }

        $id = match ($type) {
            'repository'    => $this->svc->createRepository($data),
            'place'         => $this->svc->createPlace($data),
            'agent'         => $this->svc->createAgent($data),
            'rule'          => $this->svc->createRule($data),
            'function'      => $this->svc->createFunction($data),
            'record'        => $this->svc->createRecord($data),
            'activity'      => $this->svc->createActivity($data),
            'instantiation' => $this->svc->createInstantiation($data),
        };

        // Force-rename the slug to the stable demo slug (createXxx uses the
        // entity name for a slug; we want predictable ones for idempotency).
        DB::table('slug')->where('object_id', $id)->update(['slug' => $slug]);

        $this->line("  created {$type}  slug={$slug}  id={$id}");
        return $id;
    }

    private function seedRelations(): void
    {
        $this->info('Linking entities with rico:* relations…');

        $pairs = [
            // Records → Repository (held by)
            ['rec_fonds',    'repo',      'heldBy'],
            // Records hierarchy: children → parent
            ['rec_corr',     'rec_fonds', 'includedIn'],
            ['rec_journals', 'rec_fonds', 'includedIn'],
            // Records → Agent (created by)
            ['rec_fonds',    'agent',     'createdBy'],
            // Agent → Place (associated with)
            ['agent',        'place',     'associatedWithPlace'],
            // Activity → Place (located in)
            ['activity',     'place',     'tookPlaceIn'],
            // Agent → Activity (performed)
            ['agent',        'activity',  'performedActivity'],
            // Records → Activity (generated by)
            ['rec_journals', 'activity',  'resultedFrom'],
            // Records → Rule (governed by)
            ['rec_fonds',    'rule',      'governedBy'],
            // Records → Function (supports)
            ['rec_corr',     'fn',        'associatedWithFunction'],
            // Fonds → Instantiation (has instantiation)
            ['rec_journals', 'inst',      'hasInstantiation'],
        ];

        $relationsCreated = 0;
        foreach ($pairs as [$subjKey, $objKey, $predicate]) {
            $subjId = $this->ids[$subjKey] ?? null;
            $objId  = $this->ids[$objKey]  ?? null;
            if (!$subjId || !$objId) continue;
            // Skip if a relation with this triple already exists.
            $existing = DB::table('relation')
                ->where('subject_id', $subjId)
                ->where('object_id', $objId)
                ->exists();
            if ($existing) continue;
            try {
                $this->svc->createRelation($subjId, $objId, $predicate);
                $relationsCreated++;
            } catch (\Throwable $e) {
                $this->warn("  relation {$subjKey} →{$predicate}→ {$objKey} failed: " . $e->getMessage());
            }
        }
        $this->line("  created {$relationsCreated} relation(s).");
    }

    private function drop(): int
    {
        $this->warn('Dropping OpenRiC demo fonds (all objects with slug prefix "' . self::SLUG_PREFIX . '")…');
        $ids = DB::table('slug')->where('slug', 'like', self::SLUG_PREFIX . '%')->pluck('object_id')->all();
        if (empty($ids)) {
            $this->info('Nothing to drop — no slugs match.');
            return self::SUCCESS;
        }
        DB::transaction(function () use ($ids) {
            // Drop relations referencing these ids first
            DB::table('relation')->whereIn('subject_id', $ids)->orWhereIn('object_id', $ids)->delete();
            // Then drop the per-class extension tables
            foreach (['ric_place_i18n', 'ric_place', 'ric_rule_i18n', 'ric_rule',
                      'ric_activity_i18n', 'ric_activity', 'ric_instantiation_i18n', 'ric_instantiation',
                      'information_object_i18n', 'information_object',
                      'repository_i18n', 'repository',
                      'actor_i18n', 'actor',
                      'function_object_i18n', 'function_object',
                      'slug', 'object'] as $table) {
                $col = $table === 'slug' ? 'object_id' : 'id';
                DB::table($table)->whereIn($col, $ids)->delete();
            }
        });
        $this->info('Dropped ' . count($ids) . ' demo object(s) and their relations.');
        return self::SUCCESS;
    }
}
