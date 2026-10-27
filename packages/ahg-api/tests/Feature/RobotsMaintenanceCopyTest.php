<?php

/**
 * RobotsMaintenanceCopyTest - heratio#1569: serving robots.txt keeps a copy
 * per host, which public/index.php serves while the app is in maintenance
 * mode (the nightly demo reset), so Google never sees a 503 on robots.txt.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace Tests\Feature;

use AhgApi\Controllers\PublicSitemapController;
use Tests\TestCase;

class RobotsMaintenanceCopyTest extends TestCase
{
    public function test_serving_robots_txt_keeps_a_copy_for_maintenance_mode(): void
    {
        $file = PublicSitemapController::robotsCopyPath('localhost');
        @unlink($file);

        $body = $this->get('http://localhost/robots.txt')->assertOk()->getContent();

        $this->assertFileExists($file);
        $this->assertSame($body, file_get_contents($file));
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $body);
        @unlink($file);
    }

    public function test_only_plain_host_names_get_a_copy(): void
    {
        $this->assertStringEndsWith('framework/robots/heratio.org.txt', PublicSitemapController::robotsCopyPath('Heratio.org:443'));
        $this->assertNull(PublicSitemapController::robotsCopyPath('../../etc'));
        $this->assertNull(PublicSitemapController::robotsCopyPath(''));
    }
}
