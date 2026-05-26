<?php

/**
 * Heratio - Cross-vocabulary SKOS match round-trip tests.
 *
 * (c) 2026 Johan Pieterse / Plain Sailing iSystems / The Archive and
 * Heritage Group (Pty) Ltd. Released under the AGPL-3.0-or-later licence.
 *
 * Inserts a few cross-match rows for a fixture term and asserts each of
 * the four SKOS serialisations emits the expected predicates + target
 * URIs. Also exercises the ?skos_xl=1 toggle.
 *
 * #661 Phase 3.
 */

namespace Tests\Feature;

use AhgTermTaxonomy\Services\CrossMatchService;
use Database\Factories\TermFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SkosCrossMatchExportTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Make sure the phase-3 table exists for the test connection. The
        // service provider auto-installs it but tests may run against a
        // db with an older snapshot.
        if (! Schema::hasTable('ahg_term_cross_match')) {
            $sql = file_get_contents(
                __DIR__.'/../../packages/ahg-term-taxonomy/database/install.sql'
            );
            DB::unprepared($sql);
        }
    }

    public function test_cross_match_round_trips_through_all_four_serialisations(): void
    {
        $term = TermFactory::new()->subject()
            ->withI18n(['name' => 'Archives (cross-match test)'])
            ->create();

        /** @var CrossMatchService $svc */
        $svc = app(CrossMatchService::class);
        $svc->create(
            (int) $term->id,
            'exactMatch',
            'https://id.loc.gov/authorities/subjects/sh85007034',
            [
                'target_label' => 'Archives',
                'target_vocab' => 'LCSH',
                'source' => 'loc',
                'confidence' => 0.98,
            ]
        );
        $svc->create(
            (int) $term->id,
            'closeMatch',
            'http://vocab.getty.edu/aat/300242542',
            [
                'target_label' => 'archives (groupings)',
                'target_vocab' => 'AAT',
                'source' => 'getty',
            ]
        );

        $taxonomyId = 35; // Subjects

        foreach (['rdfxml', 'turtle', 'ntriples', 'jsonld'] as $fmt) {
            $url = '/term/export/skos?taxonomy='.$taxonomyId.'&format='.$fmt;
            $resp = $this->get($url);
            $resp->assertOk();
            $body = $resp->getContent();
            $this->assertStringContainsString(
                'https://id.loc.gov/authorities/subjects/sh85007034',
                $body,
                "LCSH exactMatch target URI missing in $fmt"
            );
            $this->assertStringContainsString(
                'http://vocab.getty.edu/aat/300242542',
                $body,
                "AAT closeMatch target URI missing in $fmt"
            );

            // Predicate appearance depends on serialisation
            if ($fmt === 'jsonld') {
                $this->assertStringContainsString('skos:exactMatch', $body);
                $this->assertStringContainsString('skos:closeMatch', $body);
            } elseif ($fmt === 'ntriples') {
                $this->assertStringContainsString('<http://www.w3.org/2004/02/skos/core#exactMatch>', $body);
                $this->assertStringContainsString('<http://www.w3.org/2004/02/skos/core#closeMatch>', $body);
            } else {
                $this->assertStringContainsString('exactMatch', $body);
                $this->assertStringContainsString('closeMatch', $body);
            }
        }
    }

    public function test_skos_xl_emission_is_off_by_default_and_on_via_flag(): void
    {
        TermFactory::new()->subject()
            ->withI18n(['name' => 'SKOS-XL probe'])
            ->create();

        $taxonomyId = 35;

        $plain = $this->get('/term/export/skos?taxonomy='.$taxonomyId.'&format=turtle');
        $plain->assertOk();
        $this->assertStringNotContainsString('skosxl:', $plain->getContent());

        $xl = $this->get('/term/export/skos?taxonomy='.$taxonomyId.'&format=turtle&skos_xl=1');
        $xl->assertOk();
        $body = $xl->getContent();
        $this->assertStringContainsString('skosxl:', $body);
        $this->assertStringContainsString('skosxl:Label', $body);
        $this->assertStringContainsString('skosxl:literalForm', $body);
        // Plain literal labels are still emitted alongside (don't break legacy consumers).
        $this->assertStringContainsString('skos:prefLabel', $body);
    }
}
