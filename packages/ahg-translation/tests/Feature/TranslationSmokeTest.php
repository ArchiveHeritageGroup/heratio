<?php
/**
 * Heratio - ahg-translation package smoke test (issue #684 audit).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgTranslation\Tests\Feature;

use AhgTranslation\Providers\AhgTranslationServiceProvider;
use PHPUnit\Framework\TestCase;

final class TranslationSmokeTest extends TestCase
{
    public function testProviderClassExists(): void
    {
        $this->assertTrue(class_exists(AhgTranslationServiceProvider::class));
    }

    public function testDbAwareLoaderLoads(): void
    {
        $this->assertTrue(class_exists('AhgTranslation\\Translation\\DbAwareLoader'));
    }

    public function testUiStringServiceLoads(): void
    {
        $this->assertTrue(class_exists('AhgTranslation\\Services\\UiStringService'));
    }

    public function testImportJsonToDbCommandLoads(): void
    {
        $this->assertTrue(class_exists('AhgTranslation\\Console\\Commands\\ImportJsonToDbCommand'));
    }
}
