<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgCore\Support;

/**
 * Shared HTML sanitiser for rich-text / narrative fields that are rendered raw
 * ({!! !!}) somewhere in the UI. Allows basic formatting tags but strips
 * scripts, event handlers, and dangerous URL schemes, so stored content cannot
 * carry stored XSS into other users' views (issue #1309).
 *
 * DOM-based allow-list (replaces the earlier strip_tags + regex pass, #1309-fix
 * follow-up): the old regex could not see through HTML-entity encoding, so
 * `href="javascript&colon;alert(1)"` / `href="jav&#97;script:..."` survived
 * strip_tags and executed on click. This parser decodes entities before the
 * scheme check and drops every attribute not on a per-tag allow-list, so inline
 * event handlers (on*), style, and non-http(s)/mailto/tel URLs cannot get
 * through. ext-dom only, no external dependency.
 *
 * Reused by AhgResearch\Services\ResearchService::sanitizeHtml (delegates here)
 * so actor/archival/research narrative fields share one policy.
 */
final class HtmlSanitizer
{
    /** Formatting tags retained; anything else is unwrapped (text kept, tag dropped). */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li',
        'a', 'blockquote', 'code', 'pre', 'hr', 'table', 'thead',
        'tbody', 'tr', 'th', 'td', 'img', 'span', 'div', 'sub', 'sup',
    ];

    /** Per-tag attribute allow-list. Tags absent here keep NO attributes. */
    private const ALLOWED_ATTRS = [
        'a'   => ['href', 'title', 'name', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'td'  => ['colspan', 'rowspan'],
        'th'  => ['colspan', 'rowspan', 'scope'],
    ];

    /** Attributes carrying a URL: their value must pass the scheme check. */
    private const URL_ATTRS = ['href', 'src'];

    /** Schemes permitted in href/src. Relative / anchor / query URLs are always allowed. */
    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** Elements removed outright, content and all (never merely unwrapped). */
    private const DROP_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'template',
        'noscript', 'svg', 'math', 'link', 'meta', 'base', 'applet', 'frame', 'frameset',
    ];

    /**
     * Sanitise a rich-text value. Null/empty passes through unchanged so the
     * caller's "?? null" semantics are preserved.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // Plain text with no markup or entities: nothing to do.
        if (strpos($html, '<') === false && strpos($html, '&') === false) {
            return $html;
        }

        $doc  = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);

        // Force UTF-8; NOIMPLIED + NODEFDTD keep it a fragment (no <html>/<body>
        // wrapper); NONET blocks any network entity fetch.
        $ok = $doc->loadHTML(
            '<?xml encoding="UTF-8"><div data-sanitize-root="1">' . $html . '</div>',
            LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (! $ok) {
            // Unparseable: degrade to a hard strip rather than risk passing markup.
            return strip_tags($html);
        }

        $root = null;
        $xp   = new \DOMXPath($doc);
        $hit  = $xp->query('//div[@data-sanitize-root="1"]');
        if ($hit !== false && $hit->length > 0) {
            $root = $hit->item(0);
        }
        if (! $root instanceof \DOMElement) {
            return strip_tags($html);
        }

        self::sanitizeChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** Recursively enforce the tag + attribute allow-list on a node's children. */
    private static function sanitizeChildren(\DOMNode $node): void
    {
        // Snapshot first: the live child list mutates as we remove / unwrap.
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }

        foreach ($children as $child) {
            if ($child instanceof \DOMElement) {
                $tag = strtolower($child->nodeName);

                if (in_array($tag, self::DROP_TAGS, true)) {
                    $child->parentNode->removeChild($child);
                    continue;
                }

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // Unwrap: sanitise its subtree, hoist the children in place, drop the tag.
                    self::sanitizeChildren($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                $allowed   = self::ALLOWED_ATTRS[$tag] ?? [];
                $attrNames = [];
                if ($child->attributes !== null) {
                    foreach ($child->attributes as $attr) {
                        $attrNames[] = $attr->nodeName;
                    }
                }
                foreach ($attrNames as $an) {
                    $anl = strtolower($an);
                    if (! in_array($anl, $allowed, true)) {
                        $child->removeAttribute($an);
                        continue;
                    }
                    if (in_array($anl, self::URL_ATTRS, true) && ! self::urlSchemeSafe($child->getAttribute($an))) {
                        $child->removeAttribute($an);
                    }
                }

                // Neutralise reverse-tabnabbing on target=_blank links.
                if ($tag === 'a' && strtolower($child->getAttribute('target')) === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }

                self::sanitizeChildren($child);
            } elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $child->parentNode->removeChild($child);
            }
            // Text nodes are kept; saveHTML re-encodes them safely.
        }
    }

    /**
     * True if a URL attribute value is safe. Decodes HTML entities and strips
     * all whitespace / control chars first, so "javascript&colon;",
     * "jav&#97;script:", " java\tscript:" cannot smuggle a scheme past the check.
     * A value with no scheme (relative path, #anchor, ?query) is allowed.
     */
    private static function urlSchemeSafe(string $value): bool
    {
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = preg_replace('/[\x00-\x20\x7f]+/', '', $decoded) ?? '';

        if ($decoded === '') {
            return true;
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $decoded, $m)) {
            return in_array(strtolower($m[1]), self::SAFE_SCHEMES, true);
        }

        return true;
    }
}
