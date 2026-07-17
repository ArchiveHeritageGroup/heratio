<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgCore\Support;

/**
 * Sector site profiles (heratio#1331).
 *
 * A profile is a declarative, jurisdiction-NEUTRAL overlay applied over a full
 * Heratio install to give an operator an opinionated starting point for their
 * sector. It is NOT package removal - every package stays installed; a profile
 * only sets defaults (theme palette + identifier mask + the `sector_default`
 * marker), so it is idempotent and re-applicable (switch sectors any time).
 *
 * Storage (applied by ApplySectorProfileCommand):
 *  - `theme`            -> ahg_settings (AhgSettingsService), the canonical theme keys.
 *  - `mask`             -> setting/setting_i18n as `sector_<code>_identifier_mask`
 *                          (+ `_enabled`=1); read by SectorIdentifierService.
 *  - the chosen code    -> ahg_settings `sector_default`.
 *
 * Sectors are the real ones the codebase already models (archive/museum/gallery/
 * library/dam) plus research. "personal" from the issue text is intentionally
 * omitted - it is not a sector (it would just be archive defaults).
 */
final class SectorProfiles
{
    /**
     * @var array<string,array{label:string,mask:string,theme:array<string,string>}>
     */
    public const PROFILES = [
        'archive' => [
            'label' => 'Archive (ISAD/DACS/RAD)',
            'mask'  => 'ARC/%Y%/%04i%',
            'theme' => self::PALETTE_ARCHIVE,
        ],
        'museum' => [
            'label' => 'Museum (CCO/Spectrum)',
            'mask'  => 'MUS/%Y%/%04i%',
            'theme' => self::PALETTE_MUSEUM,
        ],
        'gallery' => [
            'label' => 'Gallery',
            'mask'  => 'GAL/%Y%/%04i%',
            'theme' => self::PALETTE_GALLERY,
        ],
        'library' => [
            'label' => 'Library (MARC/KBART)',
            'mask'  => 'LIB/%Y%/%04i%',
            'theme' => self::PALETTE_LIBRARY,
        ],
        'dam' => [
            'label' => 'Digital Asset Management',
            'mask'  => 'DAM/%Y%/%04i%',
            'theme' => self::PALETTE_DAM,
        ],
        'research' => [
            'label' => 'Research portal',
            'mask'  => 'RES/%Y%/%04i%',
            'theme' => self::PALETTE_RESEARCH,
        ],
        // #1331 - the personal / small-collection starting point the issue is
        // motivated by (family archives, personal libraries, hobbyist collectors).
        'personal' => [
            'label' => 'Personal / family collection',
            'mask'  => 'PC/%Y%/%04i%',
            'theme' => self::PALETTE_PERSONAL,
        ],
    ];

    // Each palette sets the coordinated theme keys the BS5 theme actually reads
    // (verified against ahg-settings themes form). Header/card/button/sidebar
    // backgrounds take the sector primary; their text is white for contrast.
    private const PALETTE_ARCHIVE = [
        'ahg_primary_color' => '#1f3a5f', 'ahg_secondary_color' => '#3d5a80', 'ahg_link_color' => '#2a5d8f',
        'ahg_header_bg' => '#1f3a5f', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#1f3a5f', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#1f3a5f', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#1f3a5f', 'ahg_sidebar_text' => '#ffffff',
    ];

    private const PALETTE_MUSEUM = [
        'ahg_primary_color' => '#8a5a2b', 'ahg_secondary_color' => '#b08968', 'ahg_link_color' => '#9c6a3c',
        'ahg_header_bg' => '#8a5a2b', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#8a5a2b', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#8a5a2b', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#8a5a2b', 'ahg_sidebar_text' => '#ffffff',
    ];

    private const PALETTE_GALLERY = [
        'ahg_primary_color' => '#7b2d5e', 'ahg_secondary_color' => '#a84a86', 'ahg_link_color' => '#8e3a6e',
        'ahg_header_bg' => '#7b2d5e', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#7b2d5e', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#7b2d5e', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#7b2d5e', 'ahg_sidebar_text' => '#ffffff',
    ];

    private const PALETTE_LIBRARY = [
        'ahg_primary_color' => '#1f5c3a', 'ahg_secondary_color' => '#3a7d5c', 'ahg_link_color' => '#2c6e49',
        'ahg_header_bg' => '#1f5c3a', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#1f5c3a', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#1f5c3a', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#1f5c3a', 'ahg_sidebar_text' => '#ffffff',
    ];

    private const PALETTE_DAM = [
        'ahg_primary_color' => '#0f6e74', 'ahg_secondary_color' => '#2a8d93', 'ahg_link_color' => '#1a7d83',
        'ahg_header_bg' => '#0f6e74', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#0f6e74', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#0f6e74', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#0f6e74', 'ahg_sidebar_text' => '#ffffff',
    ];

    private const PALETTE_RESEARCH = [
        'ahg_primary_color' => '#3b3b8f', 'ahg_secondary_color' => '#5a5ab0', 'ahg_link_color' => '#4a4a9f',
        'ahg_header_bg' => '#3b3b8f', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#3b3b8f', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#3b3b8f', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#3b3b8f', 'ahg_sidebar_text' => '#ffffff',
    ];

    // Warm terracotta - approachable, distinct from the institutional palettes.
    private const PALETTE_PERSONAL = [
        'ahg_primary_color' => '#a1523a', 'ahg_secondary_color' => '#c07a5e', 'ahg_link_color' => '#b0603f',
        'ahg_header_bg' => '#a1523a', 'ahg_header_text' => '#ffffff',
        'ahg_card_header_bg' => '#a1523a', 'ahg_card_header_text' => '#ffffff',
        'ahg_button_bg' => '#a1523a', 'ahg_button_text' => '#ffffff',
        'ahg_sidebar_bg' => '#a1523a', 'ahg_sidebar_text' => '#ffffff',
    ];

    /** @return array<string,array{label:string,mask:string,theme:array<string,string>}> */
    public static function all(): array
    {
        return self::PROFILES;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::PROFILES);
    }

    public static function has(string $code): bool
    {
        return isset(self::PROFILES[$code]);
    }

    /** @return array{label:string,mask:string,theme:array<string,string>}|null */
    public static function get(string $code): ?array
    {
        return self::PROFILES[$code] ?? null;
    }
}
