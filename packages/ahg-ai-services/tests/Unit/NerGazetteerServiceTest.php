<?php

/**
 * NerGazetteerServiceTest - smoke tests for the operator-curated NER
 * gazetteer pre-pass.
 *
 * Issue #667 Phase 1. Pure unit tests covering the parts of the gazetteer
 * that do not need a live DB - the empty-text short-circuit, the empty
 * gazetteer fallback (Schema unavailable), and the merge() function which
 * takes its inputs as plain arrays.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

declare(strict_types=1);

namespace AhgAiServices\Tests\Unit;

use AhgAiServices\Services\NerGazetteerService;
use Illuminate\Container\Container;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Facade;
use Monolog\Handler\NullHandler;
use Monolog\Logger as Monolog;
use PHPUnit\Framework\TestCase;

class NerGazetteerServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $container = new Container();
        Container::setInstance($container);
        $container->instance('log', new Logger(new Monolog('test', [new NullHandler()])));
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function svc(): NerGazetteerService
    {
        return new NerGazetteerService();
    }

    public function test_scan_returns_empty_buckets_for_blank_text(): void
    {
        $r = $this->svc()->scan('   ');
        $this->assertSame([], $r['buckets']['persons']);
        $this->assertSame([], $r['buckets']['organizations']);
        $this->assertSame([], $r['buckets']['places']);
        $this->assertSame([], $r['buckets']['dates']);
        $this->assertSame([], $r['buckets']['customs']);
        $this->assertSame([], $r['detailed']);
    }

    public function test_scan_returns_empty_when_schema_unavailable(): void
    {
        // No Laravel bootstrap; Schema::hasTable() throws and the outer
        // Throwable catch returns the empty shape rather than propagating.
        $r = $this->svc()->scan('Cecil John Rhodes signed papers at Kimberley.');
        $this->assertArrayHasKey('buckets',  $r);
        $this->assertArrayHasKey('detailed', $r);
        $this->assertSame([], $r['detailed']);
        foreach (['persons', 'organizations', 'places', 'dates', 'customs'] as $k) {
            $this->assertArrayHasKey($k, $r['buckets']);
            $this->assertSame([], $r['buckets'][$k]);
        }
    }

    public function test_merge_keeps_ml_buckets_when_gazetteer_is_empty(): void
    {
        $ml = [
            'persons'       => ['Alice', 'Bob'],
            'organizations' => ['Acme'],
            'places'        => ['Cape Town'],
            'dates'         => ['1948'],
        ];
        $out = $this->svc()->merge($ml, []);
        $this->assertSame(['Alice', 'Bob'], $out['persons']);
        $this->assertSame(['Acme'],          $out['organizations']);
        $this->assertSame(['Cape Town'],     $out['places']);
        $this->assertSame(['1948'],          $out['dates']);
        $this->assertSame([],                $out['customs']);
    }

    public function test_merge_appends_new_gazetteer_entries(): void
    {
        $ml  = ['persons' => ['Alice'], 'organizations' => [], 'places' => [], 'dates' => []];
        $gaz = ['persons' => ['Charlie'], 'organizations' => ['SAHRA'], 'places' => [], 'dates' => []];
        $out = $this->svc()->merge($ml, $gaz);
        $this->assertSame(['Alice', 'Charlie'], $out['persons']);
        $this->assertSame(['SAHRA'],            $out['organizations']);
    }

    public function test_merge_is_case_insensitive_on_dedup(): void
    {
        // ML already has "Alice"; gazetteer offers "alice" - one entry wins.
        $ml  = ['persons' => ['Alice'], 'organizations' => [], 'places' => [], 'dates' => []];
        $gaz = ['persons' => ['alice'], 'organizations' => [], 'places' => [], 'dates' => []];
        $out = $this->svc()->merge($ml, $gaz);
        $this->assertSame(['Alice'], $out['persons']);
    }

    public function test_merge_surfaces_customs_bucket(): void
    {
        // Non-canonical entity_type "project" maps to customs in the
        // service's TYPE_BUCKET map.
        $ml  = ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => [], 'customs' => []];
        $gaz = ['persons' => [], 'organizations' => [], 'places' => [], 'dates' => [], 'customs' => ['Project Sunshine']];
        $out = $this->svc()->merge($ml, $gaz);
        $this->assertSame(['Project Sunshine'], $out['customs']);
    }
}
