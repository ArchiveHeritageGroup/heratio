<?php

/**
 * OfficeDisplayCopyService - Heratio ahg-core
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * heratio#1627: a Word, Excel, PowerPoint, OpenDocument or RTF master gets a
 * PDF display copy, so the record viewer can show its pages in the PDF viewer
 * instead of a grey icon.
 *
 * The copy is a reference derivative (usage 141, application/pdf) beside the
 * master: uploads_path/r/<id>/reference_<name>.pdf. One row per master; a
 * re-run writes over the file and updates that row. The PDF's text is kept as
 * an `extracted_text` property of the copy, which RecordTextService puts in
 * the record's searchable text.
 *
 * On upload the PDF LibreOffice already made for the thumbnail is kept
 * (store()); a replaced master and the nightly ahg:office-pdfs sweep convert
 * again through GenerateOfficePdfJob (make()).
 */
class OfficeDisplayCopyService
{
    public const TEXT_PROPERTY = 'extracted_text';

    public const FAILED_KEY = 'office-pdf-failed:';

    /** The master's PDF display copy row, or null. */
    public static function pdfFor(int $masterId): ?object
    {
        return DB::table('digital_object')->where('parent_id', $masterId)
            ->where('usage_id', DigitalObjectService::USAGE_REFERENCE)
            ->where('mime_type', 'application/pdf')->orderBy('id')->first();
    }

    /**
     * Convert the master with LibreOffice and store the copy.
     * With $refreshPictures the page-one reference and thumbnail JPEGs are
     * re-rendered from the new PDF, written over in place (a replaced master).
     *
     * @return int|null the PDF row id, or null when nothing was stored
     */
    public static function make(int $masterId, bool $refreshPictures = false): ?int
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (! $master || ! DigitalObjectService::isOfficeDocument((string) $master->name, (string) $master->mime_type)) {
            return null;
        }
        $path = DigitalObjectService::resolveDiskPath($master);
        if (! $path) {
            Log::info('[office-pdf] master file missing for DO '.$masterId);

            return null;
        }
        $preview = DigitalObjectService::previewSource($path, (string) $master->mime_type);
        if (! $preview) {
            // An encrypted, damaged or too-large file: the nightly sweep skips it for a week.
            Cache::put(self::FAILED_KEY.$masterId, true, now()->addDays(7));

            return null;
        }
        try {
            $id = self::store($masterId, $preview['path']);
            if ($id && $refreshPictures) {
                self::refreshPictures($masterId, $preview['path']);
            }

            return $id;
        } finally {
            DigitalObjectService::removePreviewTmp($preview['tmp']);
        }
    }

    /**
     * Keep $pdf (a PDF made from the master) as the master's display copy.
     * A no-op for anything that is not an office master and for a target the
     * shared-uploads guard refuses.
     */
    public static function store(int $masterId, string $pdf): ?int
    {
        $master = DB::table('digital_object')->where('id', $masterId)->first();
        if (! $master || ! $master->object_id || ! is_file($pdf)
            || ! DigitalObjectService::isOfficeDocument((string) $master->name, (string) $master->mime_type)) {
            return null;
        }
        $masterPath = DigitalObjectService::resolveDiskPath($master);
        if (! $masterPath) {
            return null;
        }
        $safe = pathinfo((string) $master->name, PATHINFO_FILENAME);
        $safe = str_starts_with($safe, 'master_') ? substr($safe, 7) : $safe;
        $name = 'reference_'.$safe.'.pdf';
        $target = dirname($masterPath).'/'.$name;
        if ($why = SharedUploadsGuard::refuseWrite($target)) {
            Log::warning('[office-pdf] not written for DO '.$masterId.': '.$why);

            return null;
        }
        // Written under a temporary name in the same folder, then renamed, so a
        // half-written PDF never sits under the final name.
        $tmp = $target.'.part-'.bin2hex(random_bytes(4));
        if (! @copy($pdf, $tmp) || ! @rename($tmp, $target)) {
            @unlink($tmp);
            Log::warning('[office-pdf] could not write '.$target);

            return null;
        }
        SharedUploadsGuard::recordWrite($target, $masterId, 'office_pdf');

        $row = [
            'object_id' => $master->object_id, 'usage_id' => DigitalObjectService::USAGE_REFERENCE,
            'mime_type' => 'application/pdf', 'media_type_id' => DigitalObjectService::MEDIA_TEXT,
            'name' => $name, 'path' => $master->path ?: '/uploads/r/'.$master->object_id.'/',
            'byte_size' => filesize($target), 'checksum' => md5_file($target), 'checksum_type' => 'md5', 'parent_id' => $masterId,
        ];
        $existing = self::pdfFor($masterId);
        if ($existing) {
            $id = (int) $existing->id;
            DB::table('digital_object')->where('id', $id)->update($row);
        } else {
            $now = now()->format('Y-m-d H:i:s');
            $id = (int) DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => $now, 'updated_at' => $now, 'serial_number' => 0]);
            DB::table('digital_object')->insert(['id' => $id] + $row);
        }
        self::storeText($id, $target);

        return $id;
    }

    /** The PDF's text layer (pdftotext), as the copy's extracted_text property. */
    private static function storeText(int $doId, string $pdf): void
    {
        if (trim((string) @shell_exec('command -v pdftotext 2>/dev/null')) === '') {
            return;
        }
        $text = trim((string) @shell_exec(sprintf('timeout 60 pdftotext -enc UTF-8 %s - 2>/dev/null', escapeshellarg($pdf))));
        $text = mb_substr(preg_replace('/[ \t]+/u', ' ', $text), 0, 1000000);
        $prop = DB::table('property')->where('object_id', $doId)->where('name', self::TEXT_PROPERTY)->value('id');
        if ($text === '') {
            if ($prop) {
                DB::table('property_i18n')->where('id', $prop)->delete();
                DB::table('property')->where('id', $prop)->delete();
                DB::table('object')->where('id', $prop)->delete();
            }

            return;
        }
        $culture = (string) config('app.locale', 'en');
        if (! $prop) {
            $now = now()->format('Y-m-d H:i:s');
            $prop = (int) DB::table('object')->insertGetId(['class_name' => 'QubitProperty', 'created_at' => $now, 'updated_at' => $now, 'serial_number' => 0]);
            DB::table('property')->insert(['id' => $prop, 'object_id' => $doId, 'name' => self::TEXT_PROPERTY, 'source_culture' => $culture]);
        }
        DB::table('property_i18n')->updateOrInsert(['id' => $prop, 'culture' => $culture], ['value' => $text]);
    }

    /** Page one of the new PDF over the master's existing JPEG reference and thumbnail. */
    private static function refreshPictures(int $masterId, string $pdf): void
    {
        $convert = trim((string) @shell_exec('command -v convert 2>/dev/null'));
        if ($convert === '') {
            return;
        }
        $rows = DB::table('digital_object')->where('parent_id', $masterId)
            ->whereIn('usage_id', [DigitalObjectService::USAGE_REFERENCE, DigitalObjectService::USAGE_THUMBNAIL])
            ->where('mime_type', 'image/jpeg')->get();
        foreach ($rows as $r) {
            $file = DigitalObjectService::resolveDiskPath($r);
            if (! $file || SharedUploadsGuard::refuseWrite($file)) {
                continue;
            }
            $size = (int) $r->usage_id === DigitalObjectService::USAGE_THUMBNAIL ? 100 : 480;
            @shell_exec(sprintf('%s -density 150 %s -resize %dx%d\> -quality 85 -colorspace sRGB %s 2>/dev/null',
                escapeshellcmd($convert), escapeshellarg($pdf.'[0]'), $size, $size, escapeshellarg($file)));
            clearstatcache(true, $file);
            DB::table('digital_object')->where('id', $r->id)->update(['byte_size' => filesize($file), 'checksum' => md5_file($file)]);
        }
    }

    /**
     * Office masters without a PDF display copy, oldest first, leaving out
     * those whose conversion failed in the last week (so they cannot hold up
     * the rest of the sweep).
     */
    public static function missing(int $limit = 500)
    {
        return self::missingQuery()->lazyById(500, 'm.id', 'id')
            ->reject(fn ($m) => Cache::has(self::FAILED_KEY.$m->id))
            ->take(max(1, $limit))->collect();
    }

    private static function missingQuery()
    {
        $exts = DigitalObjectService::OFFICE_PREVIEW_EXTENSIONS;

        return DB::table('digital_object as m')
            ->whereNull('m.parent_id')->whereNotNull('m.object_id')
            ->where(function ($q) use ($exts) {
                $q->where('m.mime_type', 'like', 'application/vnd.openxmlformats-officedocument.%')
                    ->orWhere('m.mime_type', 'like', 'application/vnd.oasis.opendocument.%')
                    ->orWhereIn('m.mime_type', ['application/msword', 'application/vnd.ms-excel', 'application/vnd.ms-powerpoint', 'application/rtf']);
                foreach ($exts as $e) {
                    $q->orWhere('m.name', 'like', '%.'.$e);
                }
            })
            ->whereNotExists(fn ($q) => $q->from('digital_object as d')->whereColumn('d.parent_id', 'm.id')
                ->where('d.usage_id', DigitalObjectService::USAGE_REFERENCE)->where('d.mime_type', 'application/pdf'))
            ->select(['m.id', 'm.name', 'm.mime_type']);
    }
}
