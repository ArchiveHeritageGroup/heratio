<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Services;

use Illuminate\Support\Facades\DB;

/**
 * ONIX ingestion (heratio#1094).
 *
 * Parses EDItEUR ONIX for Books messages (ONIX 3.0 reference + ONIX 2.1),
 * stages each <Product> in a review queue (`library_onix_ingest_line`), and on
 * commit creates a bibliographic record (reusing LibraryService::create so the
 * object/IO/i18n/slug/library_item/creator graph is built the same way as
 * copy-cataloguing) plus an acquisitions order line (LibraryAcquisitionService).
 *
 * Tag access is namespace-agnostic via local-name() XPath, so namespaced 3.0
 * and bare 2.1 feeds parse through the same code path.
 */
class OnixIngestService
{
    public function __construct(
        protected LibraryService $library,
        protected LibraryAcquisitionService $acq,
    ) {}

    // ── Parsing ───────────────────────────────────────────────────────────

    /**
     * Parse an ONIX message into ['version' => string|null, 'records' => array].
     * Each record is a flat associative array of bibliographic + supply fields.
     *
     * @throws \RuntimeException on empty or malformed XML.
     */
    public function parse(string $xml): array
    {
        $xml = trim($xml);
        if ($xml === '') {
            throw new \RuntimeException('Empty ONIX payload.');
        }

        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT);
        if (!$ok) {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            $msg = $errs ? trim($errs[0]->message) : 'unknown parse error';
            throw new \RuntimeException('Malformed ONIX XML: ' . $msg);
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xp = new \DOMXPath($dom);

        $version = null;
        $rel = $xp->query('//*[local-name()="ONIXMessage"]/@release');
        if ($rel && $rel->length) {
            $version = $rel->item(0)->nodeValue;
        }

        $records = [];
        foreach ($xp->query('//*[local-name()="Product"]') as $product) {
            $records[] = $this->extractProduct($xp, $product, $dom);
        }

        return ['version' => $version, 'records' => $records];
    }

    /**
     * Extract one <Product> into a flat record array.
     */
    protected function extractProduct(\DOMXPath $xp, \DOMNode $product, \DOMDocument $dom): array
    {
        // Identifiers
        $isbn = $gtin = $isbn10 = $issn = null;
        foreach ($xp->query('.//*[local-name()="ProductIdentifier"]', $product) as $pi) {
            $type = $this->val($xp, $pi, './*[local-name()="ProductIDType"]');
            $idv  = $this->val($xp, $pi, './*[local-name()="IDValue"]');
            if ($idv === null) {
                continue;
            }
            $clean = preg_replace('/[^0-9Xx]/', '', $idv);
            switch ($type) {
                case '15': $isbn = $clean; break;                 // ISBN-13
                case '03': $gtin = $clean; break;                 // GTIN-13
                case '02': $isbn10 = $clean; break;               // ISBN-10
                case '22': $issn = $clean; break;                 // ISSN
                case '23': if ($issn === null) $issn = $clean; break; // ISSN-13
            }
        }
        $isbn = $isbn ?: $gtin ?: $isbn10;

        // Title (prefer the product TitleDetail, not a Collection/series title)
        $title = $this->val($xp, $product, './/*[local-name()="TitleDetail"]//*[local-name()="TitleText"]')
            ?? $this->val($xp, $product, './/*[local-name()="Title"]/*[local-name()="TitleText"]')
            ?? $this->val($xp, $product, './/*[local-name()="TitleText"]');
        $subtitle = $this->val($xp, $product, './/*[local-name()="TitleDetail"]//*[local-name()="Subtitle"]')
            ?? $this->val($xp, $product, './/*[local-name()="Subtitle"]');

        // Contributors
        $creators = [];
        foreach ($xp->query('.//*[local-name()="Contributor"]', $product) as $c) {
            $name = $this->val($xp, $c, './*[local-name()="PersonName"]');
            if ($name === null) {
                $before = $this->val($xp, $c, './*[local-name()="NamesBeforeKey"]');
                $key    = $this->val($xp, $c, './*[local-name()="KeyNames"]');
                $joined = trim(($before ? $before . ' ' : '') . ($key ?? ''));
                $name = $joined !== '' ? $joined : null;
            }
            if ($name === null) {
                $name = $this->val($xp, $c, './*[local-name()="CorporateName"]');
            }
            if ($name === null) {
                continue;
            }
            $role = $this->mapContributorRole($this->val($xp, $c, './*[local-name()="ContributorRole"]'));
            $creators[] = ['name' => $name, 'role' => $role];
        }

        // Edition / form / publisher / dates / place
        $edition   = $this->val($xp, $product, './/*[local-name()="EditionNumber"]');
        $form      = $this->val($xp, $product, './/*[local-name()="ProductForm"]');
        $publisher = $this->val($xp, $product, './/*[local-name()="PublisherName"]');
        $place     = $this->val($xp, $product, './/*[local-name()="CityOfPublication"]');

        $pubDate = $this->val($xp, $product, './/*[local-name()="PublishingDate"]/*[local-name()="Date"]')
            ?? $this->val($xp, $product, './/*[local-name()="PublicationDate"]')
            ?? $this->val($xp, $product, './/*[local-name()="CopyrightYear"]');
        $pubYear = $pubDate ? substr(preg_replace('/[^0-9]/', '', $pubDate), 0, 4) : null;

        // Supply detail
        $price    = $this->val($xp, $product, './/*[local-name()="Price"]/*[local-name()="PriceAmount"]');
        $currency = $this->val($xp, $product, './/*[local-name()="Price"]/*[local-name()="CurrencyCode"]');
        $supplier = $this->val($xp, $product, './/*[local-name()="Supplier"]/*[local-name()="SupplierName"]');

        $author = implode('; ', array_map(fn ($c) => $c['name'], $creators));

        return [
            'product_ref'   => $this->val($xp, $product, './*[local-name()="RecordReference"]'),
            'isbn'          => $isbn,
            'issn'          => $issn,
            'title'         => $title,
            'subtitle'      => $subtitle,
            'author'        => $author !== '' ? $author : null,
            'creators'      => $creators,
            'publisher'     => $publisher,
            'publication_place' => $place,
            'pub_year'      => $pubYear,
            'edition'       => $edition,
            'material_type' => $this->mapForm($form),
            'price'         => $price !== null && is_numeric($price) ? (float) $price : null,
            'currency'      => $currency,
            'supplier'      => $supplier,
            'raw'           => $dom->saveXML($product),
        ];
    }

    private function val(\DOMXPath $xp, \DOMNode $ctx, string $rel): ?string
    {
        $nodes = $xp->query($rel, $ctx);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }
        $text = trim($nodes->item(0)->textContent);
        return $text === '' ? null : $text;
    }

    private function mapContributorRole(?string $code): string
    {
        if ($code === null) {
            return 'contributor';
        }
        return match (true) {
            str_starts_with($code, 'A') => 'author',
            str_starts_with($code, 'B') => 'editor',
            str_starts_with($code, 'C') => 'translator',
            default                     => 'contributor',
        };
    }

    private function mapForm(?string $code): string
    {
        if ($code === null || $code === '') {
            return 'monograph';
        }
        $c = strtoupper($code);
        return match (true) {
            str_starts_with($c, 'D') => 'ebook',     // digital
            str_starts_with($c, 'A') => 'audio',     // audio
            str_starts_with($c, 'V') => 'video',     // video / film
            default                  => 'monograph', // BB/BC and the rest
        };
    }

    // ── Validation ────────────────────────────────────────────────────────

    /**
     * Validate one parsed record. Returns ['status' => parsed|valid|invalid|duplicate,
     * 'error' => string|null].
     */
    public function validateRecord(array $rec): array
    {
        if (empty($rec['title'])) {
            return ['status' => 'invalid', 'error' => 'Missing title (TitleText).'];
        }

        $isbn = $rec['isbn'] ?? null;
        $issn = $rec['issn'] ?? null;

        if (empty($isbn) && empty($issn)) {
            return ['status' => 'invalid', 'error' => 'No ISBN or ISSN identifier.'];
        }
        if (!empty($isbn) && strlen($isbn) === 13 && !$this->isValidIsbn13($isbn)) {
            return ['status' => 'invalid', 'error' => "Invalid ISBN-13 checksum ({$isbn})."];
        }
        if (!empty($isbn) && strlen($isbn) === 10 && !$this->isValidIsbn10($isbn)) {
            return ['status' => 'invalid', 'error' => "Invalid ISBN-10 checksum ({$isbn})."];
        }
        if (!empty($issn) && !$this->isValidIssn($issn)) {
            return ['status' => 'invalid', 'error' => "Invalid ISSN checksum ({$issn})."];
        }

        // Duplicate detection against the existing catalogue.
        $dupe = DB::table('library_item')
            ->when(!empty($isbn), fn ($q) => $q->orWhere('isbn', $isbn))
            ->when(!empty($issn), fn ($q) => $q->orWhere('issn', $issn))
            ->exists();
        if ($dupe) {
            return ['status' => 'duplicate', 'error' => 'Matching ISBN/ISSN already in catalogue.'];
        }

        return ['status' => 'valid', 'error' => null];
    }

    public function isValidIsbn13(string $isbn): bool
    {
        if (!preg_match('/^\d{13}$/', $isbn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += ((int) $isbn[$i]) * ($i % 2 === 0 ? 1 : 3);
        }
        return $sum % 10 === 0;
    }

    public function isValidIsbn10(string $isbn): bool
    {
        if (!preg_match('/^\d{9}[\dXx]$/', $isbn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $ch = $isbn[$i];
            $val = ($ch === 'X' || $ch === 'x') ? 10 : (int) $ch;
            $sum += $val * (10 - $i);
        }
        return $sum % 11 === 0;
    }

    public function isValidIssn(string $issn): bool
    {
        $issn = strtoupper($issn);
        if (!preg_match('/^\d{7}[\dX]$/', $issn)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += ((int) $issn[$i]) * (8 - $i);
        }
        $check = (11 - ($sum % 11)) % 11;
        $expected = $check === 10 ? 'X' : (string) $check;
        return $expected === $issn[7];
    }

    // ── Ingest (parse + validate + stage) ───────────────────────────────────

    /**
     * Parse + validate + persist a batch and its review-queue lines.
     * Returns ['ingest_id' => int, 'record_count' => int, 'valid_count' => int,
     * 'error_count' => int].
     */
    public function ingest(string $xml, ?string $filename = null, string $source = 'file', ?int $userId = null): array
    {
        $parsed  = $this->parse($xml);
        $records = $parsed['records'];

        $valid = 0;
        $errors = 0;
        $lines = [];
        $now = now();

        foreach ($records as $rec) {
            $v = $this->validateRecord($rec);
            if ($v['status'] === 'valid') {
                $valid++;
            } else {
                $errors++;
            }
            $lines[] = [
                'product_ref'   => $rec['product_ref'] ?? null,
                'isbn'          => $rec['isbn'] ?? null,
                'issn'          => $rec['issn'] ?? null,
                'title'         => $this->clip($rec['title'] ?? null, 500),
                'subtitle'      => $this->clip($rec['subtitle'] ?? null, 500),
                'author'        => $this->clip($rec['author'] ?? null, 500),
                'publisher'     => $this->clip($rec['publisher'] ?? null, 255),
                'pub_year'      => $rec['pub_year'] ?? null,
                'edition'       => $this->clip($rec['edition'] ?? null, 100),
                'material_type' => $rec['material_type'] ?? null,
                'price'         => $rec['price'] ?? null,
                'currency'      => $rec['currency'] ?? null,
                'supplier'      => $this->clip($rec['supplier'] ?? null, 255),
                'status'        => $v['status'],
                'error'         => $this->clip($v['error'] ?? null, 1000),
                'raw'           => $rec['raw'] ?? null,
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        }

        return DB::transaction(function () use ($filename, $source, $parsed, $records, $valid, $errors, $userId, $lines, $now) {
            $ingestId = (int) DB::table('library_onix_ingest')->insertGetId([
                'filename'      => $filename,
                'source'        => $source,
                'onix_version'  => $parsed['version'],
                'status'        => 'parsed',
                'record_count'  => count($records),
                'valid_count'   => $valid,
                'error_count'   => $errors,
                'imported_count' => 0,
                'created_by'    => $userId,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            foreach ($lines as $line) {
                $line['ingest_id'] = $ingestId;
                DB::table('library_onix_ingest_line')->insert($line);
            }

            return [
                'ingest_id'    => $ingestId,
                'record_count' => count($records),
                'valid_count'  => $valid,
                'error_count'  => $errors,
            ];
        });
    }

    // ── Commit (review queue -> catalogue + order) ───────────────────────────

    /**
     * Commit a parsed ingest: every line still in 'valid' status creates a
     * bibliographic record + an acquisitions order line. Duplicate/invalid/
     * skipped lines are left untouched. One order is created per commit.
     *
     * Returns ['imported' => int, 'skipped' => int, 'failed' => int, 'order_id' => int|null].
     */
    public function commit(int $ingestId, ?int $userId = null): array
    {
        $ingest = $this->getIngest($ingestId);
        if (!$ingest) {
            throw new \RuntimeException("ONIX ingest #{$ingestId} not found.");
        }
        if ($ingest->status === 'committed') {
            throw new \RuntimeException("ONIX ingest #{$ingestId} is already committed.");
        }

        $lines = DB::table('library_onix_ingest_line')
            ->where('ingest_id', $ingestId)
            ->where('status', 'valid')
            ->get();

        $imported = 0;
        $failed = 0;
        $orderId = $ingest->order_id ? (int) $ingest->order_id : null;

        foreach ($lines as $line) {
            try {
                // 1. Bibliographic record (reuse the canonical create path).
                $creators = [];
                foreach ($this->creatorsFromLine($line) as $c) {
                    $creators[] = $c;
                }
                // create() returns the information_object id; resolve the
                // library_item.id from it for the order-line + queue links.
                $ioId = $this->library->create([
                    'title'                    => $line->title,
                    'subtitle'                 => $line->subtitle,
                    'material_type'            => $line->material_type ?: 'monograph',
                    'isbn'                     => $line->isbn,
                    'issn'                     => $line->issn,
                    'edition'                  => $line->edition,
                    'publisher'                => $line->publisher,
                    'publication_place'        => null,
                    'publication_date'         => $line->pub_year,
                    'responsibility_statement' => $line->author,
                    'creators'                 => $creators,
                    'cataloging_source'        => 'ONIX',
                ]);
                $itemId = (int) DB::table('library_item')
                    ->where('information_object_id', $ioId)
                    ->value('id');

                // 2. Lazily create the order on first imported line.
                if ($orderId === null) {
                    $orderId = $this->acq->createOrder([
                        'order_type'  => 'deposit',           // ONIX legal-deposit / supplier feed
                        'vendor_name' => $line->supplier ?: 'ONIX ingest',
                        'currency'    => $line->currency ?: 'ZAR',
                        'status'      => 'ordered',
                        'notes'       => 'Auto-created from ONIX ingest #' . $ingestId
                            . ($ingest->filename ? ' (' . $ingest->filename . ')' : ''),
                    ]);
                }

                // 3. Order line linked to the new catalogue item.
                $lineId = $this->acq->addLine($orderId, [
                    'library_item_id' => $itemId,
                    'isbn'            => $line->isbn,
                    'title'           => $line->title,
                    'author'          => $line->author,
                    'publisher'       => $line->publisher,
                    'edition'         => $line->edition,
                    'material_type'   => $line->material_type,
                    'quantity'        => 1,
                    'unit_price'      => $line->price ?? 0,
                    'line_status'     => 'pending',
                ]);

                DB::table('library_onix_ingest_line')->where('id', $line->id)->update([
                    'status'          => 'imported',
                    'library_item_id' => $itemId,
                    'order_line_id'   => $lineId,
                    'error'           => null,
                    'updated_at'      => now(),
                ]);
                $imported++;
            } catch (\Throwable $e) {
                DB::table('library_onix_ingest_line')->where('id', $line->id)->update([
                    'status'     => 'invalid',
                    'error'      => $this->clip('Commit failed: ' . $e->getMessage(), 1000),
                    'updated_at' => now(),
                ]);
                $failed++;
            }
        }

        $skipped = (int) DB::table('library_onix_ingest_line')
            ->where('ingest_id', $ingestId)
            ->whereIn('status', ['skipped', 'duplicate'])
            ->count();

        DB::table('library_onix_ingest')->where('id', $ingestId)->update([
            'status'         => 'committed',
            'imported_count' => $imported,
            'order_id'       => $orderId,
            'created_by'     => $ingest->created_by ?? $userId,
            'updated_at'     => now(),
        ]);

        return ['imported' => $imported, 'skipped' => $skipped, 'failed' => $failed, 'order_id' => $orderId];
    }

    /**
     * Re-derive creators from a stored line's raw <Product> so the bib record
     * keeps role-tagged contributors (the queue row only stores a flat author
     * string for display).
     */
    protected function creatorsFromLine(object $line): array
    {
        if (empty($line->raw)) {
            return $line->author
                ? [['name' => $line->author, 'role' => 'author']]
                : [];
        }
        try {
            $parsed = $this->parse($line->raw);
            $rec = $parsed['records'][0] ?? null;
            if ($rec && !empty($rec['creators'])) {
                return $rec['creators'];
            }
        } catch (\Throwable) {
            // fall through to the flat author below
        }
        return $line->author ? [['name' => $line->author, 'role' => 'author']] : [];
    }

    // ── CRUD / review queue ──────────────────────────────────────────────────

    public function listIngests(array $filters = []): \Illuminate\Support\Collection
    {
        return DB::table('library_onix_ingest')
            ->when(!empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id')
            ->limit((int) ($filters['limit'] ?? 100))
            ->get();
    }

    public function getIngest(int $id): ?object
    {
        return DB::table('library_onix_ingest')->where('id', $id)->first();
    }

    public function getLines(int $ingestId, ?string $statusFilter = null): \Illuminate\Support\Collection
    {
        return DB::table('library_onix_ingest_line')
            ->where('ingest_id', $ingestId)
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->orderBy('id')
            ->get();
    }

    public function deleteIngest(int $id): bool
    {
        return (bool) DB::transaction(function () use ($id) {
            DB::table('library_onix_ingest_line')->where('ingest_id', $id)->delete();
            return DB::table('library_onix_ingest')->where('id', $id)->delete();
        });
    }

    /**
     * Review-queue action: move a line to 'skipped' (exclude from commit) or
     * back to 'valid' (re-include). Only pre-commit lines can change.
     */
    public function updateLineStatus(int $lineId, string $status): bool
    {
        if (!in_array($status, ['valid', 'skipped'], true)) {
            return false;
        }
        return (bool) DB::table('library_onix_ingest_line')
            ->where('id', $lineId)
            ->whereIn('status', ['valid', 'skipped', 'duplicate'])
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    private function clip(?string $v, int $len): ?string
    {
        if ($v === null) {
            return null;
        }
        return mb_substr($v, 0, $len);
    }
}
