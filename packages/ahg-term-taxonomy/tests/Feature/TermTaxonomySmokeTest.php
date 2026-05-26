<?php
/**
 * Heratio - ahg-term-taxonomy package smoke test (issue #682 audit).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgTermTaxonomy\Tests\Feature;

use AhgTermTaxonomy\Providers\AhgTermTaxonomyServiceProvider;
use PHPUnit\Framework\TestCase;

final class TermTaxonomySmokeTest extends TestCase
{
    public function testProviderClassExists(): void
    {
        $this->assertTrue(class_exists(AhgTermTaxonomyServiceProvider::class));
    }

    public function testCrossMatchServiceLoads(): void
    {
        $this->assertTrue(class_exists('AhgTermTaxonomy\\Services\\CrossMatchService'));
    }

    public function testShaclValidatorLoads(): void
    {
        $this->assertTrue(class_exists('AhgTermTaxonomy\\Validation\\ShaclValidator'));
    }

    public function testSkosValidateCommandLoads(): void
    {
        $this->assertTrue(class_exists('AhgTermTaxonomy\\Console\\SkosValidateCommand'));
    }
}
