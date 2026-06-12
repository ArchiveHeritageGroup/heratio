<?php

/**
 * OembedController - Heratio ahg-core
 *
 * The oEmbed 1.0 provider endpoint: GET /oembed?url={recordUrl}. oEmbed is the
 * open standard ( https://oembed.com/ ) that lets ANY consumer site (a CMS, a
 * chat app, a blog editor, a learning platform) turn a pasted Heratio record URL
 * into a rich embeddable card - the same mechanism that auto-embeds a YouTube or
 * Flickr link.
 *
 * Request
 * -------
 *   GET /oembed?url={recordUrl}&format=json|xml&maxwidth=&maxheight=
 *
 *   - url       (required) the public record URL to embed, e.g.
 *               https://host/title-of-object. Resolved to a PUBLISHED record by
 *               OembedResolverService (URL -> first path segment -> slug -> status
 *               type_id=158 status_id=160 gate, root id=1 excluded). A missing /
 *               unparseable / unknown / unpublished url returns a CLEAN oEmbed
 *               error (404 / 400), never a 500.
 *   - format    json (default) or xml. An unsupported format -> 501 per the spec.
 *   - maxwidth  optional consumer hint; the card is clamped to it (and a sane
 *   - maxheight floor) so the embed fits the consumer's column.
 *
 * Response (oEmbed 1.0)
 * ---------------------
 * A `rich` type response: version "1.0", type, title, provider_name (the site /
 * institution name from the siteTitle setting), provider_url (url('/')),
 * author_name (the record's creator, when cheap to resolve), thumbnail_url (the
 * record's thumbnail surrogate, when present), width/height, and an `html` field
 * holding a small SELF-CONTAINED embeddable card: a compact <figure> that links
 * back to the record (optional thumbnail + title + provider line). The card has no
 * external script/style dependency and is safe to drop into a host page. The title
 * and every other dynamic value are escaped for the HTML card (htmlspecialchars)
 * and for the XML document (ENT_XML1) so a record title can never inject markup.
 *
 * Formats
 * -------
 *   - json  application/json, CORS-open (Access-Control-Allow-Origin: *) so a
 *           consumer's browser-side oEmbed fetch succeeds.
 *   - xml   text/xml, a well-formed <oembed> document with the same fields.
 *
 * Safety
 * ------
 * Read-only (only the resolver's bounded SELECTs); no DB writes, no ALTER, no new
 * table; hosts/URLs from url(), never hardcoded. Bounded to ONE record. Never
 * 500s: a bad/missing url is a 404, an unknown format a 501, and any unexpected
 * failure degrades to a 404 oEmbed error. /oembed is a SINGLE-segment path with a
 * `?url=` query param, so the record URL travels in the query string and the path
 * itself is just /oembed - registered alongside the other single-segment public
 * routes (ahg-core boots early; first-registered route wins over the /{slug}
 * catch-all, and /oembed is also on that route's exclusion list by name).
 * International: culture-neutral; all copy is internationalised via __().
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

namespace AhgCore\Controllers;

use AhgCore\Services\OembedResolverService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

class OembedController extends Controller
{
    /** Neutral fallback when no siteTitle setting is configured. */
    private const DEFAULT_PROVIDER = 'Heratio';

    /** Default embeddable-card geometry, and the clamp floor for consumer hints. */
    private const DEFAULT_WIDTH  = 480;
    private const DEFAULT_HEIGHT = 280;
    private const MIN_WIDTH      = 200;
    private const MIN_HEIGHT     = 120;

    /** Thumbnail strip dimensions inside the card (the surrogate is not resized). */
    private const THUMB_WIDTH  = 120;
    private const THUMB_HEIGHT = 120;

    public function __construct(private OembedResolverService $resolver)
    {
    }

    /**
     * GET /oembed - the oEmbed 1.0 endpoint.
     *
     * Never 500s: validates format, resolves the url to a published record, and on
     * any miss or failure returns a clean oEmbed error in the requested format.
     */
    public function index(Request $request)
    {
        $format = strtolower(trim((string) $request->query('format', 'json')));
        if ($format === '') {
            $format = 'json';
        }

        // Unknown format -> 501 Not Implemented (oEmbed spec).
        if (! in_array($format, ['json', 'xml'], true)) {
            return $this->error(501, __('Unsupported oEmbed format.'), 'json');
        }

        $url = trim((string) $request->query('url', ''));
        if ($url === '') {
            return $this->error(400, __('The url parameter is required.'), $format);
        }

        try {
            $record = $this->resolver->resolve($url);
        } catch (Throwable $e) {
            \Log::warning('[ahg-core] oembed index failed: '.$e->getMessage());
            $record = null;
        }

        if ($record === null) {
            return $this->error(404, __('No published record was found for that URL.'), $format);
        }

        $maxWidth  = $this->intHint($request->query('maxwidth'));
        $maxHeight = $this->intHint($request->query('maxheight'));

        $payload = $this->buildPayload($record, $maxWidth, $maxHeight);

        return $format === 'xml'
            ? $this->xmlResponse($payload)
            : $this->jsonResponse($payload);
    }

    // ---------------------------------------------------------------------
    // Payload assembly
    // ---------------------------------------------------------------------

    /**
     * Build the oEmbed 1.0 field set for a resolved record, respecting the
     * consumer's optional maxwidth/maxheight hints.
     *
     * @param  array{id:int,slug:string,title:string,url:string,author_name:?string,thumbnail_url:?string}  $record
     * @return array<string,mixed>
     */
    private function buildPayload(array $record, ?int $maxWidth, ?int $maxHeight): array
    {
        [$width, $height] = $this->dimensions($maxWidth, $maxHeight);

        $title       = (string) ($record['title'] ?? __('Untitled record'));
        $recordUrl   = (string) ($record['url'] ?? url('/'));
        $author      = $record['author_name'] ?? null;
        $thumb       = $record['thumbnail_url'] ?? null;
        $providerN   = $this->providerName();
        $providerUrl = url('/');

        $payload = [
            'version'       => '1.0',
            'type'          => 'rich',
            'title'         => $title,
            'provider_name' => $providerN,
            'provider_url'  => $providerUrl,
            'cache_age'     => 86400,
            'width'         => $width,
            'height'        => $height,
            'html'          => $this->cardHtml($title, $recordUrl, $thumb, $author, $providerN, $providerUrl, $width),
        ];

        if (is_string($author) && $author !== '') {
            $payload['author_name'] = $author;
            $payload['author_url']  = $recordUrl;
        }

        if (is_string($thumb) && $thumb !== '') {
            $payload['thumbnail_url']    = $thumb;
            $payload['thumbnail_width']  = self::THUMB_WIDTH;
            $payload['thumbnail_height'] = self::THUMB_HEIGHT;
        }

        return $payload;
    }

    /**
     * The self-contained embeddable HTML card. A compact, inline-styled <figure>
     * that links back to the record: optional thumbnail, the title, an optional
     * "by {creator}" line, and a provider footer. No external script or stylesheet.
     * Every dynamic value is HTML-escaped so a record title can never inject markup.
     */
    private function cardHtml(
        string $title,
        string $recordUrl,
        ?string $thumb,
        ?string $author,
        string $providerName,
        string $providerUrl,
        int $width
    ): string {
        $h = fn (string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');

        $t   = $h($title);
        $ru  = $h($recordUrl);
        $pn  = $h($providerName);
        $pu  = $h($providerUrl);
        $w   = max(self::MIN_WIDTH, $width);

        $img = '';
        if (is_string($thumb) && $thumb !== '') {
            $img = '<img src="'.$h($thumb).'" alt="'.$t.'" '
                .'style="width:'.self::THUMB_WIDTH.'px;height:'.self::THUMB_HEIGHT.'px;'
                .'object-fit:cover;border-radius:4px;flex:0 0 auto;" loading="lazy" referrerpolicy="no-referrer">';
        }

        $byLine = '';
        if (is_string($author) && $author !== '') {
            $byLine = '<div style="font-size:12px;color:#555;margin-top:2px;">'
                .$h(__('Created by')).' '.$h($author).'</div>';
        }

        // Inline-styled, dependency-free card. The whole card links to the record.
        return '<div class="heratio-oembed" style="box-sizing:border-box;max-width:'.$w.'px;'
            .'border:1px solid #ddd;border-radius:8px;padding:12px;font-family:'
            .'-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#fff;">'
            .'<a href="'.$ru.'" target="_blank" rel="noopener noreferrer" '
            .'style="display:flex;gap:12px;align-items:flex-start;text-decoration:none;color:#1a1a1a;">'
            .$img
            .'<div style="min-width:0;">'
            .'<div style="font-size:16px;font-weight:600;line-height:1.3;">'.$t.'</div>'
            .$byLine
            .'<div style="font-size:12px;color:#777;margin-top:6px;">'.$pn.'</div>'
            .'</div>'
            .'</a>'
            .'<div style="font-size:11px;color:#999;margin-top:8px;border-top:1px solid #eee;padding-top:6px;">'
            .'<a href="'.$pu.'" target="_blank" rel="noopener noreferrer" style="color:#999;text-decoration:none;">'.$pn.'</a>'
            .'</div>'
            .'</div>';
    }

    /**
     * Resolve the card width/height from the consumer's optional maxwidth/maxheight
     * hints. The card is clamped to the smaller of the default and the hint, and
     * never smaller than the sane floor.
     *
     * @return array{0:int,1:int}
     */
    private function dimensions(?int $maxWidth, ?int $maxHeight): array
    {
        $width = self::DEFAULT_WIDTH;
        if ($maxWidth !== null && $maxWidth > 0) {
            $width = max(self::MIN_WIDTH, min(self::DEFAULT_WIDTH, $maxWidth));
        }

        $height = self::DEFAULT_HEIGHT;
        if ($maxHeight !== null && $maxHeight > 0) {
            $height = max(self::MIN_HEIGHT, min(self::DEFAULT_HEIGHT, $maxHeight));
        }

        return [$width, $height];
    }

    /** Parse a positive-integer query hint, or null when absent / non-numeric. */
    private function intHint($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (int) $value;

        return $n > 0 ? $n : null;
    }

    // ---------------------------------------------------------------------
    // Responses
    // ---------------------------------------------------------------------

    /** The CORS-open JSON oEmbed response. */
    private function jsonResponse(array $payload)
    {
        return response()->json($payload, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=86400',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** The well-formed XML oEmbed response (text/xml). */
    private function xmlResponse(array $payload)
    {
        return response($this->buildXml($payload), 200, [
            'Content-Type'                => 'text/xml; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=86400',
        ]);
    }

    /**
     * Serialise an oEmbed payload as a well-formed <oembed> XML document. Every
     * value is XML-escaped (ENT_XML1); integers are emitted as-is. The field order
     * follows the spec's example document.
     */
    private function buildXml(array $payload): string
    {
        $lines = [];
        $lines[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $lines[] = '<oembed>';
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (is_int($value)) {
                $lines[] = '  <'.$key.'>'.$value.'</'.$key.'>';
            } else {
                $lines[] = '  <'.$key.'>'.$this->x((string) $value).'</'.$key.'>';
            }
        }
        $lines[] = '</oembed>';

        return implode("\n", $lines)."\n";
    }

    /**
     * A clean oEmbed error in the requested format. The spec lets a provider
     * return an HTTP error (400 / 404 / 501) for bad / unfound / unsupported
     * requests; we also carry a small machine-readable body. Never a 500.
     */
    private function error(int $status, string $message, string $format)
    {
        if ($format === 'xml') {
            $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                .'<oembed>'."\n"
                .'  <error>'.$this->x($message).'</error>'."\n"
                .'</oembed>'."\n";

            return response($xml, $status, [
                'Content-Type'                => 'text/xml; charset=UTF-8',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response()->json(['error' => $message], $status, [
            'Access-Control-Allow-Origin' => '*',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * The institution / site name for provider_name, read from the existing
     * setting/setting_i18n `siteTitle` value (the same value the theme header
     * renders), culture en, with a neutral fallback. Guarded.
     */
    private function providerName(): string
    {
        try {
            $setting = DB::table('setting')->where('name', 'siteTitle')->first();
            if ($setting) {
                $i18n = DB::table('setting_i18n')
                    ->where('id', $setting->id)
                    ->where('culture', 'en')
                    ->first();
                $value = trim((string) ($i18n->value ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (Throwable $e) {
            // fall through to default
        }

        return self::DEFAULT_PROVIDER;
    }

    /** XML-escape an element value. */
    private function x(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
