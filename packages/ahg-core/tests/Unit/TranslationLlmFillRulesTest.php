<?php

/**
 * TranslationLlmFillRulesTest - when a machine translation may be written
 * (heratio#1510).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

namespace AhgCore\Tests\Unit;

use AhgCore\Commands\TranslationLlmFillCommand;
use PHPUnit\Framework\TestCase;

class TranslationLlmFillRulesTest extends TestCase
{
    private TranslationLlmFillCommand $c;

    protected function setUp(): void
    {
        $this->c = new TranslationLlmFillCommand;
    }

    public function test_a_faithful_translation_is_accepted(): void
    {
        $this->assertTrue($this->c->acceptable('Upload :count files to %1%', 'Téléverser :count fichiers vers %1%', 'fr'));
        $this->assertTrue($this->c->acceptable('Scope and content', 'النطاق والمحتوى', 'ar'));
    }

    public function test_a_lost_or_changed_placeholder_is_rejected(): void
    {
        $this->assertFalse($this->c->acceptable('Upload :count files', 'Téléverser des fichiers', 'fr'));
        $this->assertFalse($this->c->acceptable('<strong>Note:</strong> %s', 'Remarque : %s', 'fr'));
        $this->assertFalse($this->c->acceptable('{{ name }} saved', '{{ nom }} enregistré', 'fr'));
    }

    public function test_wrong_script_and_stray_english_are_rejected(): void
    {
        $this->assertFalse($this->c->acceptable('Scope and content', 'Scope and content', 'ar'), 'no Arabic script');
        $this->assertFalse($this->c->acceptable('Scope and content', 'النطاق and المحتوى', 'ar'), 'leftover English word');
        $this->assertFalse($this->c->acceptable('Save record', 'حفظRecord', 'ar'), 'Latin glued to Arabic');
        $this->assertTrue($this->c->acceptable('Export to EAD', 'تصدير إلى EAD', 'ar'), 'an acronym in the source may stay');
    }

    public function test_an_unchanged_latin_string_is_not_written(): void
    {
        $this->assertFalse($this->c->acceptable('Archival description', 'Archival description', 'fr'));
    }
}
