<?php

namespace App\Support;

use AhgCore\Services\AhgSettingsService;

/**
 * Centralised reader for the Media Player settings shown on
 * /admin/ahgSettings/media. Closes audit issue #85; #103 ships the
 * Plyr + Video.js vendor bundles so all three player_type values
 * are functional end-to-end.
 *
 * Six keys wired here:
 *   - media_player_type      basic | plyr | videojs (#103: all three live)
 *   - media_autoplay         <video|audio autoplay> + Plyr/Video.js opts
 *   - media_loop             <video|audio loop> + Plyr/Video.js opts
 *   - media_default_volume   JS sets player.volume on loadedmetadata
 *                            (and Plyr/Video.js volume option)
 *   - media_show_controls    <video|audio controls> (Plyr/Video.js opts)
 *   - media_show_download    toggles "Download <type>" button visibility
 *
 * Two additional keys exist on the form but require additional work
 * outside this issue's scope (see follow-up tickets):
 *   - media_show_waveform        needs WaveSurfer.js in the front-end bundle (#101)
 *   - media_transcription_enabled needs a transcription-storage backend (#102)
 *   - media_max_file_size        upload-validation concern, separate ticket
 *
 * Defaults match the seeded values so a fresh install behaves identically
 * pre-/post- wiring.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */
class MediaSettings
{
    public static function playerType(): string
    {
        $t = (string) AhgSettingsService::get('media_player_type', 'basic');
        return in_array($t, ['basic', 'plyr', 'videojs'], true) ? $t : 'basic';
    }

    public static function autoplay(): bool
    {
        return AhgSettingsService::getBool('media_autoplay', false);
    }

    public static function loop(): bool
    {
        return AhgSettingsService::getBool('media_loop', false);
    }

    public static function defaultVolume(): float
    {
        $v = (float) AhgSettingsService::get('media_default_volume', '1');
        if ($v < 0) return 0.0;
        if ($v > 1) return 1.0;
        return $v;
    }

    public static function showControls(): bool
    {
        return AhgSettingsService::getBool('media_show_controls', true);
    }

    public static function showDownload(): bool
    {
        return AhgSettingsService::getBool('media_show_download', false);
    }

    public static function showWaveform(): bool
    {
        return AhgSettingsService::getBool('media_show_waveform', false);
    }

    public static function transcriptionEnabled(): bool
    {
        return AhgSettingsService::getBool('media_transcription_enabled', false);
    }

    /**
     * Single payload for the master.blade.php window.AHG_MEDIA injector.
     * The JS player init reads default_volume from this so it can apply
     * it on the player's `loadedmetadata` event without an extra fetch.
     */
    public static function payload(): array
    {
        return [
            'player_type'           => self::playerType(),
            'autoplay'              => self::autoplay(),
            'loop'                  => self::loop(),
            'default_volume'        => self::defaultVolume(),
            'show_controls'         => self::showControls(),
            'show_download'         => self::showDownload(),
            'show_waveform'         => self::showWaveform(),
            'transcription_enabled' => self::transcriptionEnabled(),
        ];
    }
}
