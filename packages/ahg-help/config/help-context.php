<?php

/**
 * Contextual help map (#1332).
 *
 * Associates application areas with the most relevant in-app help article so a
 * page can deep-link to its own documentation instead of only the global Help
 * Center. Resolution is by longest matching URL-path prefix, with optional
 * exact route-name overrides taking precedence.
 *
 * Every slug here must be a real, published help_article slug; if a slug does
 * not resolve, HelpArticleService::contextualFor() simply returns null (no
 * broken link). Admin-only articles are hidden from guests automatically by
 * the service's admin-visibility filter.
 *
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

return [
    // Longest-prefix-wins: list specific sub-paths before their parent.
    'paths' => [
        'admin/settings'        => 'ahg-settings-user-guide',
        'admin/dropdowns'       => 'ahg-settings-user-guide',
        'admin/reports'         => 'reports-dashboard-user-guide',

        'research/annotations'  => 'research-annotations',
        'research/claim'        => 'research-claim-ledger',
        'research/dmp'          => 'research-dmp-builder',
        'research/decision'     => 'research-decision-log',
        'research/argument'     => 'research-argument-builder',
        'research/ethics'       => 'research-ethics-register',
        'research/biblio'       => 'research-bibliographies',
        'research'              => 'ahgresearchplugin',

        'ingest'                => 'ingest-user-guide',
        'search'                => 'advanced-search-user-guide',
        'dam'                   => 'dam-user-guide',
        'library'               => 'library-user-guide',
        'reports'               => 'reports-user-guide',
    ],

    // Exact route-name overrides (win over path prefixes). e.g.
    //   'clipboard.index' => 'some-clipboard-article',
    'routes' => [
    ],
];
