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
 * scripts, event handlers, and javascript: URLs, so stored content cannot
 * carry stored XSS into other users' views (issue #1309).
 *
 * This mirrors the long-standing AhgResearch\Services\ResearchService::sanitizeHtml
 * behaviour, lifted into a shared home so actor/archival narrative fields can
 * reuse the exact same policy without coupling to the research package.
 */
final class HtmlSanitizer
{
    /** Formatting tags retained; everything else is stripped. */
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><s>'
        .'<h1><h2><h3><h4><h5><h6><ul><ol><li><a><blockquote><code><pre>'
        .'<hr><table><thead><tbody><tr><th><td><img><span><div><sub><sup>';

    /**
     * Sanitise a rich-text value. Null/empty passes through unchanged so the
     * caller's "?? null" semantics are preserved.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        // Drop disallowed tags (removes <script>, <iframe>, <style>, etc.).
        $clean = strip_tags($html, self::ALLOWED_TAGS);

        // Neutralise inline event handlers (onerror=, onclick=, ...) and
        // javascript: URLs that survive inside allowed tags/attributes.
        $clean = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $clean);
        $clean = preg_replace('/javascript\s*:/i', '', $clean);

        return $clean;
    }
}
