<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Services;

use AhgAiServices\Support\FsDataSafeCsv;
use AhgAiServices\Support\FsKeyingRules;
use AhgAiServices\Support\FsModelFieldMap;
use AhgAiServices\Support\FsScotlandProfile;

/**
 * FS-Scotland AI indexer (heratio FS-metadata-capture).
 *
 * Orchestrates: derive DGS/image number from the file path -> classify image
 * type -> extract records via the trained HTR model (gateway HTR plane) ->
 * ditto-inherit + normalise per the keying rules -> assemble Data Safe rows ->
 * CSV. The extraction step calls HtrService (gateway, routes to whichever GPU
 * serves the trained Scotland doc_type); everything else is deterministic and
 * works without the model (so it's testable + buildable now).
 */
class FsScotlandIndexerService
{
    public function __construct(private HtrService $htr)
    {
    }

    /**
     * DGS number from the parent folder (9-digit) + 5-digit image number from
     * the filename, e.g. ".../008066403/008066403_00015.jpg" -> 008066403 / 00015.
     *
     * @return array{dgs:string,image_nbr:string}
     */
    public function parseImageMeta(string $path): array
    {
        $dgs = '';
        if (preg_match('/(\d{9})/', basename(dirname($path)), $m)) {
            $dgs = $m[1];
        }
        $imageNbr = '';
        if (preg_match('/_(\d{1,5})\.[A-Za-z]+$/', basename($path), $m)) {
            $imageNbr = str_pad($m[1], 5, '0', STR_PAD_LEFT);
        } elseif (preg_match('/(\d{1,5})\.[A-Za-z]+$/', basename($path), $m)) {
            $imageNbr = str_pad($m[1], 5, '0', STR_PAD_LEFT);
        }

        return ['dgs' => $dgs, 'image_nbr' => $imageNbr];
    }

    /**
     * Constant + per-image fields shared by every record on an image.
     *
     * @param array{collection_id?:string,ppq_id?:string} $project
     * @return array<string,string>
     */
    public function baseFields(string $dgs, string $imageNbr, array $project = []): array
    {
        return [
            'FS_COLLECTION_ID'    => (string) ($project['collection_id'] ?? ''),
            'FS_PPQ_ID'           => (string) ($project['ppq_id'] ?? ''),
            'FS_VIS_STATUS'       => '',
            'FS_DIGITAL_FILM_NBR' => $dgs,
            'FS_IMAGE_NBR'        => $imageNbr,
        ];
    }

    /**
     * Deterministic pipeline (no model): raw extracted records -> ditto
     * inheritance -> per-field normalisation -> merge base + record number ->
     * keep only the fields valid for the event type. Returns Data Safe rows.
     *
     * @param array<int,array<string,string>> $extracted raw records (system-name => value)
     * @param array<string,string>            $base      from baseFields()
     * @return array<int,array<string,string>>
     */
    public function processRecords(array $extracted, string $eventType, array $base): array
    {
        $extracted = FsKeyingRules::applyDitto($extracted);
        $allowed = array_flip(FsScotlandProfile::fieldsFor($eventType));

        $rows = [];
        foreach (array_values($extracted) as $i => $rec) {
            $rec = FsKeyingRules::normalizeRecord($rec);
            $row = $base;
            $row['EVENT_TYPE'] = $eventType;
            $row['FS_LANGUAGE'] = 'en';
            $row['FS_IMAGE_TYPE'] = '';
            $row['FS_RECORD_NBR'] = (string) $i; // starts at 0, +1 per record
            foreach ($rec as $field => $value) {
                if (isset($allowed[$field])) {
                    $row[$field] = (string) $value;
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * A single non-record image row (leader card, blank, duplicate, etc.):
     * Image Type set, Language 'und', no event fields.
     *
     * @return array<string,string>
     */
    public function nonRecordRow(string $imageType, array $base): array
    {
        return array_merge($base, [
            'FS_LANGUAGE'   => 'und',
            'FS_IMAGE_TYPE' => $imageType,
            'FS_RECORD_NBR' => '',
            'EVENT_TYPE'    => '',
        ]);
    }

    /**
     * Index one image end to end (needs the live HTR model). Returns Data Safe
     * rows, or null if extraction is unavailable (model down) so the caller can
     * queue/skip rather than fail.
     *
     * @param array{collection_id?:string,ppq_id?:string,doc_type?:string} $project
     * @return array<int,array<string,string>>|null
     */
    public function indexImage(string $path, array $project = []): ?array
    {
        $meta = $this->parseImageMeta($path);
        $base = $this->baseFields($meta['dgs'], $meta['image_nbr'], $project);
        $src = basename($path);
        $tag = static fn (array $rows): array => array_map(static fn ($r) => $r + ['_src' => $src], $rows);

        // The HTR doc_type (type_a birth / type_b death / type_c marriage) is
        // chosen by the batch's event type - the model doesn't return it. The
        // operator sets event_type per batch; an explicit doc_type override wins.
        $eventType = (string) ($project['event_type'] ?? '');
        $docType = (string) ($project['doc_type'] ?? '');
        if ($docType === '') {
            $docType = $eventType !== '' ? FsModelFieldMap::docTypeForEvent($eventType) : 'auto';
        }

        $result = $this->htr->extract($path, $docType, 'all');
        if ($result === null) {
            return null; // model unavailable / rejected the doc_type - caller queues
        }

        // The model returns a flat `fields:[{name,value,confidence}]` list.
        $fields = $result['fields'] ?? null;
        if (! is_array($fields) || $fields === []) {
            return $tag([$this->nonRecordRow('No Extractable Data Image', $base)]);
        }

        // Field shaping needs the event type (which also picked the doc_type);
        // without it we can't map model names -> Data Safe columns.
        if (! FsScotlandProfile::isEventType($eventType)) {
            return $tag([$this->nonRecordRow('Other Image', $base)]);
        }

        // Adapter: model field names -> FS Data Safe system names (one record
        // per image; the model doesn't segment multiple records yet).
        $record = FsModelFieldMap::toFsRecord($fields, $eventType);
        if ($record === []) {
            // Model emitted only no-Data-Safe-home fields (or hallucinated on a
            // non-record) -> nothing keyable.
            return $tag([$this->nonRecordRow('No Extractable Data Image', $base)]);
        }

        return $tag($this->processRecords([$record], $eventType, $base));
    }

    /**
     * Raw model fields for ONE image, each tagged with its Data Safe target and
     * bbox - powers the review-grid overlay (draw boxes on the image + per-box
     * recognise). Returns [] when the model is unavailable.
     *
     * @return array<int,array{model_name:string,fs_field:?string,value:string,confidence:mixed,bbox:mixed}>
     */
    public function rawFields(string $path, string $eventType): array
    {
        $docType = $eventType !== '' ? FsModelFieldMap::docTypeForEvent($eventType) : 'auto';
        $result = $this->htr->extract($path, $docType, 'all');
        $out = [];
        foreach ((array) ($result['fields'] ?? []) as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $out[] = [
                'model_name' => $name,
                'fs_field'   => FsModelFieldMap::fsFieldFor($name, $eventType),
                'value'      => (string) ($f['value'] ?? ''),
                'confidence' => $f['confidence'] ?? null,
                'bbox'       => $f['bbox'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Instant folder listing - ONE Data Safe row per image with the constant /
     * per-image fields filled (DGS, image number, collection, PPQ, event type)
     * and the event fields blank. No HTR call, so it returns immediately; the
     * event fields fill in per-image on demand (reviewImage) from the grid. This
     * is what powers "Run preview" - a 49-image folder used to block ~70s on a
     * synchronous full-folder HTR sweep; now the grid appears at once.
     *
     * @param array{collection_id?:string,ppq_id?:string,event_type?:string} $project
     * @return array<int,array<string,string>>
     */
    public function listImages(string $folder, array $project = []): array
    {
        $images = glob(rtrim($folder, '/').'/*.{jpg,jpeg,png,tif,tiff,JPG,JPEG,PNG}', GLOB_BRACE) ?: [];
        sort($images);
        $rows = [];
        foreach ($images as $img) {
            $meta = $this->parseImageMeta($img);
            $row = $this->baseFields($meta['dgs'], $meta['image_nbr'], $project);
            $row['EVENT_TYPE'] = (string) ($project['event_type'] ?? '');
            $row['FS_LANGUAGE'] = 'en';
            $row['FS_IMAGE_TYPE'] = '';
            $row['FS_RECORD_NBR'] = (string) count($rows);
            $row['_src'] = basename($img);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Review ONE image with a single HTR extraction: returns the overlay boxes
     * (model fields tagged with Data Safe target + bbox) AND the assembled Data
     * Safe row (toFsRecord -> keying-rule normalisation). Powers row-click in the
     * review grid (fill the row + draw the boxes) without re-extracting.
     *
     * @param array{collection_id?:string,ppq_id?:string,event_type?:string} $project
     * @return array{fields:array<int,array<string,mixed>>,row:array<string,string>}
     */
    public function reviewImage(string $path, string $eventType, array $project = []): array
    {
        $docType = $eventType !== '' ? FsModelFieldMap::docTypeForEvent($eventType) : 'auto';
        $result = $this->htr->extract($path, $docType, 'all');
        $modelFields = is_array($result['fields'] ?? null) ? $result['fields'] : [];

        // Overlay boxes (same shape as rawFields()).
        $fields = [];
        foreach ($modelFields as $f) {
            $name = (string) ($f['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $fields[] = [
                'model_name' => $name,
                'fs_field'   => FsModelFieldMap::fsFieldFor($name, $eventType),
                'value'      => (string) ($f['value'] ?? ''),
                'confidence' => $f['confidence'] ?? null,
                'bbox'       => $f['bbox'] ?? null,
            ];
        }

        // Assembled Data Safe row for the grid.
        $meta = $this->parseImageMeta($path);
        $base = $this->baseFields($meta['dgs'], $meta['image_nbr'], $project);
        $base['EVENT_TYPE'] = $eventType;
        if ($modelFields !== [] && FsScotlandProfile::isEventType($eventType)) {
            $record = FsModelFieldMap::toFsRecord($modelFields, $eventType);
            $rows = $this->processRecords([$record], $eventType, $base);
            $row = $rows[0] ?? $base;
        } else {
            $row = $base;
        }
        $row['_src'] = basename($path);

        return ['fields' => $fields, 'row' => $row];
    }

    /**
     * Index a DGS folder; returns [rows, csv]. Images that can't be extracted
     * yet (model down) are reported under `pending`.
     *
     * @return array{rows:array<int,array<string,string>>,pending:list<string>,csv:string}
     */
    public function indexFolder(string $folder, array $project = []): array
    {
        $rows = [];
        $pending = [];
        $images = glob(rtrim($folder, '/').'/*.{jpg,jpeg,png,tif,tiff,JPG,JPEG,PNG}', GLOB_BRACE) ?: [];
        sort($images);
        foreach ($images as $img) {
            $res = $this->indexImage($img, $project);
            if ($res === null) {
                $pending[] = basename($img);
                continue;
            }
            array_push($rows, ...$res);
        }

        return ['rows' => $rows, 'pending' => $pending, 'csv' => FsDataSafeCsv::toString($rows)];
    }
}
