<?php

/**
 * AlmaConnector - real-time federated search against an Ex Libris Alma library
 * services platform via its public SRU interface (#1330).
 *
 * Alma is the dominant academic LSP; this lets a Heratio federation peer of
 * peer_type 'alma' be searched live alongside DSpace/OAI/AtoM peers. SRU is used
 * rather than the Alma REST API because SRU is public per-institution and needs
 * no API key, which is what a federated-search peer wants; it returns MARCXML,
 * mapped here to a PeerSearchResult.
 *
 * The peer's base_url is the full SRU endpoint, e.g.
 *   https://<inst>.alma.exlibrisgroup.com/view/sru/<INST_CODE>
 * (unlike DSpace, nothing is appended). The click-through URL uses
 * config.record_url_template with a {mms_id} placeholder when set (typically a
 * Primo VE permalink); otherwise it falls back to the institution host.
 *
 * Bib/holdings import into information objects is a separate path: Alma also
 * exposes OAI-PMH, so an oai_pmh harvest peer covers it - see the package README.
 *
 * Mirrors DSpaceConnector's SSRF host block.
 *
 * @phase F (issue #1330)
 */

namespace AhgFederation\Connectors;

use Illuminate\Support\Facades\Http;

final class AlmaConnector implements PeerConnector
{
    public const PEER_TYPE = 'alma';

    private const MARC_NS = 'http://www.loc.gov/MARC21/slim';

    /** Block obvious cloud-metadata IPs (SSRF), same list as the other connectors. */
    private const SSRF_BLOCKED_HOSTS = [
        '169.254.169.254',
        'metadata.google.internal',
        'metadata.internal',
    ];

    private object $peer;

    public function bind(object $peerRow): void
    {
        $this->peer = $peerRow;
    }

    public function peerTypeKey(): string
    {
        return self::PEER_TYPE;
    }

    public function supportsCapability(string $capability): bool
    {
        return in_array($capability, ['full_text_search', 'metadata_filter'], true);
    }

    public function search(string $query, array $filters = [], int $limit = 50): array
    {
        $endpoint = $this->sruEndpoint();
        if ($endpoint === null || trim($query) === '') {
            return [];
        }

        // CQL: general keyword index. Quote the term so multi-word queries are
        // one phrase-ish search and embedded quotes cannot break the CQL.
        $cql = 'alma.all_for_ui="'.str_replace('"', ' ', $query).'"';

        try {
            $response = Http::withHeaders(['User-Agent' => 'AHG-Federation-Alma-Connector/1.0'])
                ->timeout((int) ceil(((int) ($this->peer->timeout_ms ?? 5000)) / 1000))
                ->connectTimeout(2)
                ->accept('application/xml')
                ->get($endpoint, [
                    'version'      => '1.2',
                    'operation'    => 'searchRetrieve',
                    'recordSchema' => 'marcxml',
                    'maximumRecords' => max(1, min($limit, 50)),
                    'query'        => $cql,
                ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->parse($response->body(), $limit);
    }

    /** Parse an SRU MARCXML response into PeerSearchResult value objects. */
    private function parse(string $xml, int $limit): array
    {
        if (trim($xml) === '') {
            return [];
        }

        $prev = libxml_use_internal_errors(true);
        $doc = new \DOMDocument;
        $loaded = $doc->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (! $loaded) {
            return [];
        }

        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('marc', self::MARC_NS);

        $records = $xp->query('//marc:record');
        if ($records === false || $records->length === 0) {
            return [];
        }

        $peerName = (string) ($this->peer->peer_name ?? $this->peer->name ?? 'Alma');
        $badge = sprintf('Federated from %s (Alma)', $peerName);
        $template = $this->peerConfig()['record_url_template'] ?? null;

        $out = [];
        $i = 0;
        foreach ($records as $rec) {
            $mms = $this->controlField($xp, $rec, '001');
            if ($mms === null) {
                continue;
            }

            $title = $this->title($xp, $rec);
            if ($title === null) {
                continue;
            }

            $author = $this->subField($xp, $rec, '100', 'a')
                ?? $this->subField($xp, $rec, '110', 'a')
                ?? $this->subField($xp, $rec, '700', 'a');
            $date = $this->subField($xp, $rec, '264', 'c')
                ?? $this->subField($xp, $rec, '260', 'c')
                ?? $this->dateFrom008($xp, $rec);
            $snippet = $this->subField($xp, $rec, '520', 'a');
            $isbn = $this->subField($xp, $rec, '020', 'a');

            $out[] = new PeerSearchResult(
                sourceId: $mms,
                title: $title,
                snippet: $snippet !== null ? mb_substr($snippet, 0, 280) : null,
                url: $this->recordUrl($mms, $template),
                peerType: self::PEER_TYPE,
                sourceBadge: $badge,
                score: max(0.1, 1.0 - ($i * 0.02)),
                dedupeKey: 'mms:'.$mms,
                date: $this->cleanDate($date),
                extras: [
                    'peer_id'   => (int) ($this->peer->peer_id ?? $this->peer->id ?? 0),
                    'peer_name' => $peerName,
                    'author'    => $author !== null ? rtrim($author, ' .,') : null,
                    'isbn'      => $isbn,
                    'mms_id'    => $mms,
                    'reference' => $mms,
                ],
            );
            $i++;
        }

        return array_slice($out, 0, $limit);
    }

    private function title(\DOMXPath $xp, \DOMNode $rec): ?string
    {
        $a = $this->subField($xp, $rec, '245', 'a');
        if ($a === null) {
            return null;
        }
        $b = $this->subField($xp, $rec, '245', 'b');
        $title = trim($a.' '.(string) $b);

        // MARC 245 routinely ends with ISBD punctuation ( / : , ; ).
        return rtrim($title, " /:,;") ?: $a;
    }

    private function controlField(\DOMXPath $xp, \DOMNode $rec, string $tag): ?string
    {
        $n = $xp->query('.//marc:controlfield[@tag="'.$tag.'"]', $rec);
        if ($n !== false && $n->length > 0) {
            $v = trim($n->item(0)->textContent);

            return $v !== '' ? $v : null;
        }

        return null;
    }

    private function subField(\DOMXPath $xp, \DOMNode $rec, string $tag, string $code): ?string
    {
        $n = $xp->query('.//marc:datafield[@tag="'.$tag.'"]/marc:subfield[@code="'.$code.'"]', $rec);
        if ($n !== false && $n->length > 0) {
            $v = trim($n->item(0)->textContent);

            return $v !== '' ? $v : null;
        }

        return null;
    }

    /** MARC 008 positions 07-10 hold the publication year. */
    private function dateFrom008(\DOMXPath $xp, \DOMNode $rec): ?string
    {
        $f = $this->controlField($xp, $rec, '008');
        if ($f === null || strlen($f) < 11) {
            return null;
        }
        $year = substr($f, 7, 4);

        return preg_match('/^\d{4}$/', $year) ? $year : null;
    }

    private function cleanDate(?string $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return preg_match('/(\d{4})/', $date, $m) ? $m[1] : null;
    }

    private function recordUrl(string $mms, ?string $template): string
    {
        if (is_string($template) && str_contains($template, '{mms_id}')) {
            return str_replace('{mms_id}', $mms, $template);
        }

        // No permalink template configured: link to the institution host so the
        // result is still clickable. The SRU path is stripped for a cleaner root.
        $base = rtrim((string) ($this->peer->base_url ?? ''), '/');
        $host = parse_url($base, PHP_URL_SCHEME).'://'.parse_url($base, PHP_URL_HOST);

        return (str_contains($host, 'http')) ? $host : $base;
    }

    private function sruEndpoint(): ?string
    {
        $base = rtrim((string) ($this->peer->base_url ?? ''), '/');
        if ($base === '') {
            return null;
        }

        $host = strtolower((string) (parse_url($base, PHP_URL_HOST) ?? ''));
        if ($host === '' || in_array($host, self::SSRF_BLOCKED_HOSTS, true)) {
            return null;
        }

        return $base;
    }

    /** @return array<string,mixed> */
    private function peerConfig(): array
    {
        $raw = $this->peer->config ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
