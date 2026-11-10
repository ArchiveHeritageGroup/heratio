<?php

/**
 * TranslationLintTextNodesTest - ahg:translation-lint sees the hardcoded text
 * the original pattern bank missed (heratio#1597): text that shares an element
 * with a Blade expression, text spread over an @if/@else block, text in
 * elements outside the bank, and literal PHP flash messages - while leaving
 * translated strings, expressions, script/style/code and directives alone.
 *
 * Runs against a throwaway fixture package that is removed afterwards.
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

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationLintTextNodesTest extends TestCase
{
    private const PKG = 'zz-translation-lint-fixture';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = base_path('packages/'.self::PKG);
        if (! is_writable(base_path('packages'))) {
            $this->markTestSkipped('packages/ is not writable here.');
        }
        File::ensureDirectoryExists($this->dir.'/resources/views');
        File::ensureDirectoryExists($this->dir.'/src');

        File::put($this->dir.'/resources/views/page.blade.php', <<<'BLADE'
<h1>
  @if($total)
    Showing {{ number_format($total) }} results
  @else
    No results found
  @endif
</h1>
<p>Repository: {{ $name }}</p>
<span>{{ __('Already translated') }}</span>
<td>{{ $value }}</td>
<script>var label = "Script text is ignored";</script>
<code>Code sample is ignored</code>
{{-- A Blade comment is ignored --}}
<li>@lang('Directive is ignored')</li>
<small>PDF</small>
BLADE);

        File::put($this->dir.'/src/Thing.php', <<<'PHP'
<?php
return [
    redirect()->back()->with('error', 'Record not found.'),
    redirect()->back()->with('success', __('Saved.')),
    session()->flash('warning', 'Session is about to expire.'),
];
PHP);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function lint(): string
    {
        Artisan::call('ahg:translation-lint', ['--package' => self::PKG, '--all' => true]);

        return Artisan::output();
    }

    public function test_text_mixed_with_an_expression_is_found(): void
    {
        $out = $this->lint();

        $this->assertStringContainsString('[text]  Showing {{…}} results', $out);
        $this->assertStringContainsString('[text]  Repository: {{…}}', $out);
    }

    public function test_text_inside_an_if_else_block_is_found(): void
    {
        $this->assertStringContainsString('[text]  No results found', $this->lint());
    }

    public function test_literal_flash_messages_are_found_and_translated_ones_are_not(): void
    {
        $out = $this->lint();

        $this->assertStringContainsString('[flash]  Record not found.', $out);
        $this->assertStringContainsString('[flash]  Session is about to expire.', $out);
        $this->assertStringNotContainsString('Saved.', $out);
    }

    public function test_translated_text_scripts_code_comments_directives_and_acronyms_are_ignored(): void
    {
        $out = $this->lint();

        foreach (['Already translated', 'Script text', 'Code sample', 'Blade comment', 'Directive is ignored', '[text]  PDF'] as $absent) {
            $this->assertStringNotContainsString($absent, $out);
        }
    }

    public function test_the_fixture_reports_exactly_the_expected_candidates(): void
    {
        $out = $this->lint();

        $this->assertStringContainsString('page.blade.php  (3 candidates)', $out);
        $this->assertStringContainsString('Thing.php  (2 candidates)', $out);
        $this->assertStringContainsString('L5     [text]  No results found', $out);
    }
}
