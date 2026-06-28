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
        'admin/dropdowns'       => 'dropdown-manage-user-guide', // #1350 (was ahg-settings-user-guide)
        'admin/reports'         => 'reports-dashboard-user-guide',

        'research/quotas'       => 'researcher-quotas-user-guide',
        'research/workspaces'   => 'research-workspace-files-user-guide',
        'research/annotations'  => 'research-annotations',
        'research/claim'        => 'research-claim-ledger',
        'research/dmp'          => 'research-dmp-builder',
        'research/decision'     => 'research-decision-log',
        'research/argument'     => 'research-argument-builder',
        'research/ethics'       => 'research-ethics-register',
        'research/biblio'       => 'research-bibliographies',
        'research'              => 'research-user-guide', // #1375 (was ahgresearchplugin legacy doc)

        'ingest'                => 'ingest-user-guide',
        'search'                => 'advanced-search-user-guide',
        'dam'                   => 'dam-user-guide',
        'library'               => 'library-user-guide',
        'reports'               => 'reports-user-guide',

        // ── Contextual-help wiring pass (#1350 / #1375) ──────────────────────
        // Every slug below is a verified help_article row; every prefix is a
        // real admin route prefix. Lights up the header help button on modules
        // whose articles existed but were only findable via global search.
        // T1 core/platform modules (#1350):
        'admin/acl'                  => 'acl-user-guide',
        'admin/users'                => 'user-manage-user-guide',
        'admin/menu'                 => 'menu-manage-user-guide',
        'taxonomy'                   => 'term-taxonomy-user-guide',
        'term'                       => 'term-taxonomy-user-guide',
        'staticpage'                 => 'static-page-user-guide',
        'landing-page'               => 'landing-page-user-guide',
        'tenant'                     => 'multi-tenant-user-guide',
        // Wider admin-module wiring pass (#1375 phase 2):
        'admin/authority-resolution' => 'authority-resolution-user-guide',
        'admin/security-clearance'   => 'security-clearance-user-guide',
        'admin/metadata-extraction'  => 'metadata-extraction-user-guide',
        'admin/media-processing'     => 'media-processing-user-guide',
        'admin/metadata-export'      => 'metadata-export-user-guide',
        'admin/data-migration'       => 'data-migration-user-guide',
        'admin/custom-fields'        => 'custom-fields-user-guide',
        'admin/preservation'         => 'preservation-user-guide',
        'admin/heritage'             => 'heritage-manage-user-guide',
        'admin/dacs-manage'          => 'dacs-manage-user-guide',
        'admin/rad-manage'           => 'rad-manage-user-guide',
        'admin/mods-manage'          => 'mods-manage-user-guide',
        'admin/marketplace'          => 'marketplace-user-guide',
        'admin/federation'           => 'federation-user-guide',
        'admin/dc-manage'            => 'dc-manage-user-guide',
        'admin/3d-models'            => '3d-model-user-guide',
        'admin/audit'                => 'audit-trail-user-guide',
        'admin/backup'               => 'backup-user-guide',
        'admin/condition'            => 'condition-user-guide',
        'admin/dedupe'               => 'dedupe-user-guide',
        'admin/doi'                  => 'doi-manage-user-guide',
        'admin/feedback'             => 'feedback-user-guide',
        'admin/gis'                  => 'gis-user-guide',
        'admin/graphql'              => 'graphql-user-guide',
        'admin/icip'                 => 'icip-user-guide',
        'admin/image-ar'             => 'image-ar-user-guide',
        'admin/jobs'                 => 'jobs-manage-user-guide',
        'admin/label'                => 'label-user-guide',
        'admin/naz'                  => 'naz-user-guide',
        'admin/pdf-tools'            => 'pdf-tools-user-guide',
        'admin/privacy'              => 'privacy-user-guide',
        'admin/records'              => 'records-manage-user-guide',
        'admin/scan'                 => 'scan-user-guide',
        'admin/spectrum'             => 'spectrum-user-guide',
        'admin/translation'          => 'translation-user-guide',
        'admin/vendor'               => 'vendor-user-guide',
        'admin/cdpa'                 => 'cdpa-user-guide',
    ],

    // Exact route-name overrides (win over path prefixes). e.g.
    //   'clipboard.index' => 'some-clipboard-article',
    'routes' => [
    ],
];
