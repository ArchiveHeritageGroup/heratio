<?php

/**
 * BrowseDefaultViewTest - the browse view an administrator chooses under
 * Settings > Global is the view browse opens with (the settings existed but
 * nothing read them; GLAM browse always opened in cards), including a
 * separate default for browsing digital objects (cf. AtoM #2370).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Services\SettingHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BrowseDefaultViewTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        SettingHelper::flush();
        parent::tearDown();
    }

    private function set(string $name, string $value): void
    {
        app(\AhgSettings\Services\SettingsService::class)->saveSetting($name, null, $value, 'en');
        SettingHelper::flush();
    }

    /** The view browse opens with when the URL names none. */
    private function viewOf(array $query): string
    {
        $m = new \ReflectionMethod(\AhgDisplay\Controllers\DisplayController::class, 'defaultViewMode');
        $m->setAccessible(true);

        return (string) $m->invoke(app(\AhgDisplay\Controllers\DisplayController::class), \Illuminate\Http\Request::create('/glam/browse', 'GET', $query));
    }

    public function test_browse_opens_in_the_chosen_view(): void
    {
        $this->set('default_archival_description_browse_view', 'table');
        $this->set('default_digital_object_browse_view', '');
        $this->assertSame('table', $this->viewOf([]));
        $this->assertSame('table', $this->viewOf(['hasDigital' => 1]), 'digital objects follow descriptions unless set');

        $this->set('default_digital_object_browse_view', 'card');
        $this->assertSame('card', $this->viewOf(['hasDigital' => 1]));
        $this->assertSame('table', $this->viewOf([]));

        $this->get(route('glam.browse', ['topLevel' => 0]))->assertOk();
    }
}
