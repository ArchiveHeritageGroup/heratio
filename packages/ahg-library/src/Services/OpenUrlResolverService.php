<?php

/**
 * OpenUrlResolverService - OpenURL 1.0 (KEV) link resolver for the library catalogue.
 *
 * Parses OpenURL 1.0 Key/Encoded-Value (KEV) query parameters (rft.title,
 * rft.au, rft.isbn, rft.issn, rft.date, rft_id, etc.), normalises them into a
 * citation context object, and matches that context against the local
 * library_item catalogue (by ISBN, ISSN, then title). The matching layer is
 * deliberately separated from the parsing layer so the parser can be unit
 * tested without a database connection.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpenUrlResolverService
{
    /**
     * Recognised OpenURL 1.0 KEV metadata keys mapped to a normalised context key.
     * Both dotted (rft.title) and underscore (rft_id) forms are handled.
     */
    private const KEV_MAP = [
        'rft.title'    => 'title',
        'rft.atitle'   => 'atitle',
        'rft.jtitle'   => 'jtitle',
        'rft.btitle'   => 'btitle',
        'rft.au'       => 'author',
        'rft.aulast'   => 'aulast',
        'rft.aufirst'  => 'aufirst',
        'rft.isbn'     => 'isbn',
        'rft.issn'     => 'issn',
        'rft.eissn'    => 'eissn',
        'rft.date'     => 'date',
        'rft.pub'      => 'publisher',
        'rft.volume'   => 'volume',
        'rft.issue'    => 'issue',
        'rft.spage'    => 'spage',
        'rft.genre'    => 'genre',
        'rft.doi'      => 'doi',
    ];

    /**
     * Parse an associative array of raw OpenURL KEV query parameters into a
     * normalised citation context. Pure function - no database access.
     *
     * Resolves identifier shortcuts:
     *   - rft_id=urn:isbn:... / info:doi/... / urn:issn:... -> isbn/doi/issn
     *   - rft_id=info:oai/... is preserved as oai_id
     * Falls back to rft.jtitle / rft.btitle / rft.atitle for the title when
     * rft.title is absent.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,string>
     */
    public function parseContext(array $params): array
    {
        $ctx = [];

        foreach (self::KEV_MAP as $kev => $key) {
            // Accept both dotted and underscore variants of the same field.
            $alt = str_replace('.', '_', $kev);
            $value = $params[$kev] ?? $params[$alt] ?? null;
            if ($value !== null && trim((string) $value) !== '') {
                $ctx[$key] = trim((string) $value);
            }
        }

        // rft_id can carry one or more identifier URIs.
        $ids = $params['rft_id'] ?? $params['rft.id'] ?? [];
        if (is_string($ids)) {
            $ids = [$ids];
        }
        foreach ((array) $ids as $id) {
            $this->absorbIdentifier((string) $id, $ctx);
        }

        // Title fallbacks: prefer rft.title, then jtitle/btitle, then atitle.
        if (! isset($ctx['title'])) {
            foreach (['jtitle', 'btitle', 'atitle'] as $fallback) {
                if (isset($ctx[$fallback])) {
                    $ctx['title'] = $ctx[$fallback];
                    break;
                }
            }
        }

        // Author fallback: combine aulast/aufirst when rft.au absent.
        if (! isset($ctx['author']) && (isset($ctx['aulast']) || isset($ctx['aufirst']))) {
            $ctx['author'] = trim(($ctx['aulast'] ?? '') . ', ' . ($ctx['aufirst'] ?? ''), ', ');
        }

        // Normalise identifiers (strip hyphens/spaces from ISBN/ISSN).
        if (isset($ctx['isbn'])) {
            $ctx['isbn'] = $this->normaliseIsbn($ctx['isbn']);
        }
        if (isset($ctx['issn'])) {
            $ctx['issn'] = $this->normaliseIssn($ctx['issn']);
        }
        if (isset($ctx['eissn'])) {
            $ctx['eissn'] = $this->normaliseIssn($ctx['eissn']);
        }

        return $ctx;
    }

    /**
     * Decode a single rft_id identifier URI into the context array. Pure helper.
     */
    private function absorbIdentifier(string $id, array &$ctx): void
    {
        $id = trim($id);
        if ($id === '') {
            return;
        }

        if (preg_match('~^urn:isbn:(.+)$~i', $id, $m)) {
            $ctx['isbn'] = $this->normaliseIsbn($m[1]);
        } elseif (preg_match('~^urn:issn:(.+)$~i', $id, $m)) {
            $ctx['issn'] = $this->normaliseIssn($m[1]);
        } elseif (preg_match('~^info:doi/(.+)$~i', $id, $m)) {
            $ctx['doi'] = $m[1];
        } elseif (preg_match('~^info:oai/(.+)$~i', $id, $m)) {
            $ctx['oai_id'] = $m[1];
        } elseif (preg_match('~^https?://doi\.org/(.+)$~i', $id, $m)) {
            $ctx['doi'] = $m[1];
        }
    }

    /**
     * Strip everything but digits and a trailing X from an ISBN.
     */
    public function normaliseIsbn(string $isbn): string
    {
        return strtoupper(preg_replace('/[^0-9Xx]/', '', $isbn));
    }

    /**
     * Normalise an ISSN to the standard NNNN-NNNN form when possible.
     */
    public function normaliseIssn(string $issn): string
    {
        $digits = strtoupper(preg_replace('/[^0-9Xx]/', '', $issn));
        if (strlen($digits) === 8) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4);
        }
        return trim($issn);
    }

    /**
     * Resolve a parsed citation context against the local catalogue.
     *
     * Returns an array describing the outcome:
     *   ['status' => 'matched', 'item' => stdClass, 'slug' => string]
     *   ['status' => 'multiple', 'items' => array]
     *   ['status' => 'none']
     *
     * @param  array<string,string>  $ctx
     * @return array<string,mixed>
     */
    public function resolve(array $ctx): array
    {
        if (! Schema::hasTable('library_item')) {
            return ['status' => 'none', 'items' => []];
        }

        $matches = $this->findMatches($ctx);
        $count = count($matches);

        if ($count === 1) {
            $row = $matches[0];
            return [
                'status' => 'matched',
                'item'   => $row,
                'slug'   => $row->slug ?? null,
            ];
        }

        if ($count > 1) {
            return ['status' => 'multiple', 'items' => $matches];
        }

        return ['status' => 'none', 'items' => []];
    }

    /**
     * Run the matching query: identifier-first (ISBN/ISSN/eISSN/DOI), then title.
     *
     * @param  array<string,string>  $ctx
     * @return array<int,object>
     */
    private function findMatches(array $ctx): array
    {
        $culture = (string) app()->getLocale();

        $base = fn () => DB::table('library_item')
            ->join('information_object', 'library_item.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($j) use ($culture) {
                $j->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->select([
                'library_item.id as library_item_id',
                'information_object.id as information_object_id',
                'information_object_i18n.title as title',
                'library_item.isbn',
                'library_item.issn',
                'library_item.doi',
                'slug.slug as slug',
            ]);

        // 1. ISBN (most specific).
        if (! empty($ctx['isbn'])) {
            $hits = $base()
                ->whereRaw("REPLACE(REPLACE(UPPER(library_item.isbn), '-', ''), ' ', '') = ?", [$ctx['isbn']])
                ->limit(25)->get()->all();
            if (! empty($hits)) {
                return $hits;
            }
        }

        // 2. DOI.
        if (! empty($ctx['doi'])) {
            $hits = $base()
                ->where('library_item.doi', $ctx['doi'])
                ->limit(25)->get()->all();
            if (! empty($hits)) {
                return $hits;
            }
        }

        // 3. ISSN / eISSN.
        foreach (['issn', 'eissn'] as $issnKey) {
            if (! empty($ctx[$issnKey])) {
                $hits = $base()
                    ->where('library_item.issn', $ctx[$issnKey])
                    ->limit(25)->get()->all();
                if (! empty($hits)) {
                    return $hits;
                }
            }
        }

        // 4. Title (least specific) - exact then prefix.
        if (! empty($ctx['title'])) {
            $title = $ctx['title'];
            $hits = $base()
                ->where('information_object_i18n.title', $title)
                ->limit(25)->get()->all();
            if (! empty($hits)) {
                return $hits;
            }

            $hits = $base()
                ->where('information_object_i18n.title', 'like', $this->escapeLike($title) . '%')
                ->limit(25)->get()->all();
            if (! empty($hits)) {
                return $hits;
            }
        }

        return [];
    }

    /**
     * Escape LIKE wildcards in user-supplied input.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Build an OpenURL ContextObject XML response describing the resolution
     * outcome (used when there is no single match). Pure function - no DB.
     *
     * @param  array<string,string>  $ctx
     * @param  array<int,object>     $candidates
     */
    public function buildContextObjectXml(array $ctx, array $candidates = []): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('ctx:context-objects');
        $root->setAttribute('xmlns:ctx', 'info:ofi/fmt:xml:xsd:ctx');
        $dom->appendChild($root);

        $co = $dom->createElement('ctx:context-object');
        $co->setAttribute('timestamp', gmdate('Y-m-d\TH:i:s\Z'));
        $root->appendChild($co);

        $referent = $dom->createElement('ctx:referent');
        $meta = $dom->createElement('ctx:metadata');

        foreach ($ctx as $key => $value) {
            $el = $dom->createElement('ctx:' . $this->xmlSafeName($key));
            $el->appendChild($dom->createTextNode((string) $value));
            $meta->appendChild($el);
        }

        $referent->appendChild($meta);
        $co->appendChild($referent);

        // Resolution result element.
        $result = $dom->createElement('ctx:resolution');
        $result->setAttribute('matches', (string) count($candidates));
        foreach ($candidates as $cand) {
            $hit = $dom->createElement('ctx:candidate');
            $hit->setAttribute('id', (string) ($cand->library_item_id ?? ''));
            if (! empty($cand->slug)) {
                $hit->setAttribute('url', $this->libraryUrl($cand->slug));
            }
            $hit->appendChild($dom->createTextNode((string) ($cand->title ?? '')));
            $result->appendChild($hit);
        }
        $co->appendChild($result);

        return $dom->saveXML();
    }

    /**
     * Absolute URL for a library record, falling back to a root-relative path
     * when the Laravel URL generator is not available (e.g. unit tests).
     */
    private function libraryUrl(string $slug): string
    {
        $path = '/library/' . ltrim($slug, '/');

        if (function_exists('app') && app()->bound('url')) {
            return url($path);
        }

        return $path;
    }

    /**
     * Coerce a context key into a valid XML element name.
     */
    private function xmlSafeName(string $key): string
    {
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $key);
        if ($name === '' || ! preg_match('/^[A-Za-z_]/', $name)) {
            $name = 'x_' . $name;
        }
        return $name;
    }
}
