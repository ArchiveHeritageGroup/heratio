<?php

/**
 * RichTextTest - description fields written in Markdown display as Markdown,
 * plain text keeps its line breaks, and HTML in either is escaped.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace Tests\Feature;

use AhgCore\Support\RichText;
use Tests\TestCase;

class RichTextTest extends TestCase
{
    public function test_markdown_renders_and_html_is_escaped(): void
    {
        $html = RichText::render("# Funeral\n\nCuttings from *The Star* <script>alert(1)</script> - https://example.org/x.\n\n- one\n- two");
        $this->assertStringContainsString('<h5>Funeral</h5>', $html);
        $this->assertStringContainsString('<em>The Star</em>', $html);
        $this->assertStringContainsString('<li>one</li>', $html);
        $this->assertStringContainsString('<a href="https://example.org/x"', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->assertSame("Line one<br />\nline two &amp; more", RichText::render("Line one\nline two & more"));
        $this->assertStringNotContainsString('javascript:', RichText::render('[x](javascript:alert(1)) **b**'), 'unsafe links are dropped');
        $this->assertSame('', RichText::render(null));
    }
}
