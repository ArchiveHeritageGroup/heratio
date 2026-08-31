<?php

namespace AhgCore\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;

/**
 * QR codes, rendered locally.
 *
 * Every QR on a label used to be fetched from a third-party service - three
 * from api.qrserver.com and one from chart.googleapis.com, which Google retired
 * and which had been returning 404 for some time. Two reasons that is the wrong
 * default for this platform, beyond the dead one simply being broken:
 *
 *   A label print told an outside service what the institution holds. The record
 *   URL, and by extension the identifier of a possibly restricted or culturally
 *   sensitive item, left the building on every render. Nobody chose that.
 *
 *   Labels stopped working without internet access. A reading room, a store room
 *   or an air-gapped install could not print at all.
 *
 * bacon/bacon-qr-code was already a dependency and already used server-side for
 * two-factor setup. This renders through it, so a QR costs no network call and
 * leaks nothing.
 */
class QrCodeService
{
    /** Longest payload accepted. QR itself caps out well below this at usable sizes. */
    public const MAX_LENGTH = 1200;

    /**
     * Raw SVG markup for a payload, or null if it cannot be rendered.
     *
     * Returns null rather than throwing: a QR is decoration on a label, and a
     * label that prints without its QR is far better than a page that 500s.
     */
    public static function svg(string $data, int $size = 120): ?string
    {
        $data = trim($data);
        if ($data === '' || mb_strlen($data) > self::MAX_LENGTH) {
            return null;
        }

        $size = max(48, min(1024, $size));

        try {
            $renderer = new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd());

            return (new Writer($renderer))->writeString($data);
        } catch (\Throwable $e) {
            Log::warning('QR render failed: '.$e->getMessage(), ['length' => mb_strlen($data)]);

            return null;
        }
    }

    /**
     * A `data:` URI, for use directly in an <img src>.
     *
     * Embedding rather than linking is deliberate: it survives being saved,
     * emailed or printed to PDF, and needs no second request at print time -
     * which is exactly when a label is most likely to be somewhere with no
     * network.
     */
    public static function dataUri(string $data, int $size = 120): ?string
    {
        $svg = self::svg($data, $size);

        return $svg === null ? null : 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
