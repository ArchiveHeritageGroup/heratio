<?php

/**
 * CollectionMapTest - the collection map view of the explorer (heratio#1567):
 * served with the graph for a collection, places only records the visitor may
 * see (a draft never appears as a point, a title or a text vector), groups
 * museum materials as values, and reduces stored text vectors so that alike
 * records stay alike.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Services\CollectionMapService;
use AhgApi\Services\ExplorerDataService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Support\StatusRow;
use AhgSearch\Services\VectorSearchService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CollectionMapTest extends TestCase
{
    use DatabaseTransactions;

    private function obj(string $class): int
    {
        return (int) DB::table('object')->insertGetId(['class_name' => $class, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function record(int $lft, int $rgt, int $status, ?int $parent, string $title): int
    {
        $id = $this->obj('QubitInformationObject');
        DB::table('information_object')->insert(['id' => $id, 'parent_id' => $parent ?? 1, 'lft' => $lft, 'rgt' => $rgt, 'source_culture' => 'en', 'identifier' => "CM-$id"]);
        DB::table('information_object_i18n')->insert(['id' => $id, 'culture' => 'en', 'title' => $title]);
        DB::table('slug')->insert(['object_id' => $id, 'slug' => "cm-rec-$id"]);
        DB::table('status')->insert(['id' => StatusRow::allocateId(), 'object_id' => $id, 'type_id' => 158, 'status_id' => $status]);

        return $id;
    }

    private function material(int $record, string $materials): void
    {
        DB::table('museum_metadata')->insert(['object_id' => $record, 'materials' => $materials]);
    }

    private function setting(string $key, string $value): void
    {
        DB::table('ahg_settings')->updateOrInsert(['setting_key' => $key], ['setting_value' => $value]);
        AhgSettingsService::clearCache();
    }

    /** @return array{0:int,1:int,2:int,3:int} fonds, two published items, one draft */
    private function collection(): array
    {
        $base = (int) DB::table('information_object')->max('rgt') + 100;
        $fonds = $this->record($base, $base + 9, 160, null, 'Map test fonds');
        $a = $this->record($base + 1, $base + 2, 160, $fonds, 'Silver teapot');
        $b = $this->record($base + 3, $base + 4, 160, $fonds, 'Silver spoon');
        $draft = $this->record($base + 5, $base + 6, 159, $fonds, 'Zzhidden draft jug');
        $this->material($a, 'Silver; wood');
        $this->material($b, 'SILVER');
        $this->material($draft, 'silver, Zzsecretclay');

        return [$fonds, $a, $b, $draft];
    }

    public function test_the_map_ships_with_the_collection_explorer_and_never_shows_a_hidden_record(): void
    {
        [$fonds, $a, $b, $draft] = $this->collection();
        $this->setting('explorer_map_embeddings', '1');
        $asked = new \ArrayObject;
        $this->app->instance(VectorSearchService::class, new class($asked) extends VectorSearchService
        {
            public function __construct(private \ArrayObject $asked) {}

            public function pointVectors(array $ids): array
            {
                $this->asked->exchangeArray($ids);

                return array_combine($ids, array_map(fn ($i) => array_map(fn ($j) => sin($i * 7 + $j), range(0, 15)), $ids));
            }
        });

        $html = $this->get("/glam/browse?ancestor=$fonds&view=graph&topLevel=0&type=all")->assertOk()->getContent();

        $this->assertStringContainsString('window.MAPVIEW', $html, 'the map script is inlined beside the graph');
        $this->assertStringNotContainsString('<script src="map.js">', $html);
        $this->assertStringContainsString('Silver teapot', $html);
        $this->assertStringNotContainsString('Zzhidden', $html, 'a draft is never a point or a title');
        $this->assertStringNotContainsString('Zzsecretclay', $html, 'nor are its values');

        preg_match('/window\.DATA = (\{.*?\}); window\.LIVE/s', $html, $m);
        $data = json_decode($m[1], true);
        $this->assertNotContains((string) $draft, array_column($data['records'], 'id'));
        $this->assertNotContains($draft, $asked->getArrayCopy(), 'only visible records are read from the vector index');
        $this->assertNotContains((string) $draft, $data['vec']['ids']);
        $this->assertContains((string) $a, $data['vec']['ids']);

        $silver = collect($data['entities'])->firstWhere('id', 'material:silver');
        $this->assertNotNull($silver, 'materials are values on the map');
        $this->assertEqualsCanonicalizing([(string) $a, (string) $b], $silver['recs']);
    }

    public function test_text_vectors_are_off_unless_switched_on(): void
    {
        [$fonds, $a, $b] = $this->collection();
        $this->setting('explorer_map_embeddings', '0');
        $this->app->instance(VectorSearchService::class, new class extends VectorSearchService
        {
            public function pointVectors(array $ids): array
            {
                throw new \RuntimeException('the vector index is not read when the setting is off');
            }
        });

        $this->assertArrayNotHasKey('vec', ExplorerDataService::build([$fonds, $a, $b], 'https://example.test'));
        $this->assertNull(CollectionMapService::textVectors([$fonds, $a, $b]));
    }

    public function test_reduced_vectors_keep_alike_records_alike(): void
    {
        $base = array_map(fn ($j) => cos($j), range(0, 63));
        $other = array_map(fn ($j) => sin(3 * $j + 1), range(0, 63));
        $vectors = [10 => $base, 11 => array_map(fn ($x, $j) => $x + 0.05 * sin($j), $base, array_keys($base)), 12 => $other, 13 => array_map(fn ($x) => -$x, $other)];

        $out = CollectionMapService::reduce($vectors);
        $this->assertSame(['10', '11', '12', '13'], $out['ids']);
        $bytes = array_values(unpack('c*', base64_decode($out['q'])));
        $this->assertCount(4 * $out['dim'], $bytes);
        $row = fn ($i) => array_slice($bytes, $i * $out['dim'], $out['dim']);
        $dot = fn ($p, $q) => array_sum(array_map(fn ($x, $y) => $x * $y, $row($p), $row($q))) / 16129;

        $this->assertGreaterThan(0.9, $dot(0, 1), 'near-identical texts stay together');
        $this->assertLessThan($dot(0, 1), $dot(0, 2));
        $this->assertLessThan(-0.5, $dot(2, 3), 'opposites stay apart');
        $this->assertSame($out, CollectionMapService::reduce($vectors), 'the same input gives the same map');
    }
}
