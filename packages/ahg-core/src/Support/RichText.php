<?php

/**
 * RichText - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Support;

use Illuminate\Support\Str;

/**
 * A description's free-text field for display. Text written in Markdown
 * (headings, lists, links, emphasis) is rendered; anything else keeps the old
 * behaviour, line breaks only. Either way any HTML typed into the field is
 * escaped, never run, and only http(s) and mailto links are kept.
 *
 * Headings are shifted down to h5/h6: a "# Title" inside a field must not
 * outrank the record's own page title.
 */
class RichText
{
    public static function render(?string $text): string
    {
        $text = (string) $text;
        if (trim($text) === '') {
            return '';
        }
        if (! self::looksLikeMarkdown($text)) {
            return nl2br(e($text));
        }
        $html = Str::markdown($text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
        $html = preg_replace_callback('#<(/?)h([1-6])\b#', fn ($m) => '<'.$m[1].'h'.min(6, (int) $m[2] + 4), $html);
        // Bare URLs in running text become links too.
        $html = preg_replace('#(?<![">=])\bhttps?://[^\s<]+[^\s<.,;:)\]]#', '<a href="$0" rel="noopener">$0</a>', $html);

        return '<div class="ahg-rich-text">'.$html.'</div>';
    }

    /** Markdown: a heading or list on its own line, a link, or bold or italic text. */
    public static function looksLikeMarkdown(string $text): bool
    {
        return (bool) preg_match('/^(#{1,6} \S|[-*] \S|\d+\. \S)|\[[^\]]+\]\(https?:|\*\*[^*\n]+\*\*|(?<![*\w])\*[^*\s][^*\n]*[^*\s]\*(?![*\w])/m', $text);
    }
}
