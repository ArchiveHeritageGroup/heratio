<?php

/**
 * TransferPackageService - Service for Heratio
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

namespace AhgNarssa\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use InvalidArgumentException;
use XMLWriter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TransferPackageService
{
    public function build(array $informationObjectIds, ?int $initiatedBy = null, ?string $title = null, ?string $description = null): array
    {
        if (empty($informationObjectIds)) {
            throw new InvalidArgumentException('At least one information_object_id required');
        }
        $ref = $this->allocateReference();
        $workDir = $this->workDir($ref);
        @mkdir($workDir . '/items', 0775, true);

        $now = date('Y-m-d H:i:s');

        $transferId = (int) DB::table('narssa_transfer')->insertGetId([
            'transfer_reference' => $ref,
            'title'              => $title ?? ('NARSSA transfer ' . $ref),
            'description'        => $description,
            'initiated_by'       => $initiatedBy,
            'item_count'         => 0,
            'total_size_bytes'   => 0,
            'status'             => 'draft',
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $manifestRows = [['archival_reference', 'title', 'schedule_code', 'digital_object_count', 'bytes', 'sha256']];
        $totalBytes = 0;
        $totalDOs = 0;
        $items = [];
        $culture = $this->culture();
        $scheduleCodes = [];

        foreach ($informationObjectIds as $ioId) {
            $ioId = (int) $ioId;
            $detail = $this->loadIoDetail($ioId, $culture);
            if (!$detail) {
                continue;
            }

            $itemDir = $workDir . '/items/' . $this->sanitiseRef($detail['identifier'] ?: 'IO-' . $ioId);
            @mkdir($itemDir . '/digital_objects', 0775, true);

            file_put_contents(
                $itemDir . '/description.xml',
                $this->buildEad2002($detail),
            );

            $doInfo = $this->copyDigitalObjects($ioId, $itemDir . '/digital_objects');

            $itemSha = $this->hashDirectory($itemDir);
            file_put_contents($itemDir . '/checksums.sha256', $itemSha . '  ' . basename($itemDir) . "\n");

            $totalBytes += $doInfo['bytes'];
            $totalDOs   += $doInfo['count'];

            $scheduleCode = $detail['schedule_code'] ?: '';
            if ($scheduleCode !== '') {
                $scheduleCodes[$scheduleCode] = true;
            }

            $manifestRows[] = [
                $detail['identifier'] ?: ('IO-' . $ioId),
                $detail['title']      ?: 'Untitled',
                $scheduleCode,
                (string) $doInfo['count'],
                (string) $doInfo['bytes'],
                $itemSha,
            ];

            $items[] = [
                'transfer_id'           => $transferId,
                'information_object_id' => $ioId,
                'disposal_action_id'    => $detail['disposal_action_id'],
                'archival_reference'    => $detail['identifier'],
                'title_snapshot'        => mb_substr((string) ($detail['title'] ?? ''), 0, 500),
                'schedule_code'         => $scheduleCode,
                'digital_object_count'  => $doInfo['count'],
                'digital_object_bytes'  => $doInfo['bytes'],
                'sha256'                => $itemSha,
                'created_at'            => $now,
            ];
        }

        $manifestPath = $workDir . '/manifest.csv';
        $fp = fopen($manifestPath, 'w');
        foreach ($manifestRows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        file_put_contents($workDir . '/transfer.xml', $this->buildMets($ref, $items));

        $tarPath = dirname($workDir) . '/' . $ref . '.tar.gz';
        $cwd = dirname($workDir);
        $base = basename($workDir);
        $cmd = sprintf('cd %s && tar -czf %s %s 2>&1', escapeshellarg($cwd), escapeshellarg($tarPath), escapeshellarg($base));
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);
        if ($exit !== 0) {
            throw new RuntimeException('tar failed: ' . implode("\n", $output));
        }

        $pkgSha = hash_file('sha256', $tarPath);

        DB::table('narssa_transfer')->where('id', $transferId)->update([
            'item_count'       => count($items),
            'total_size_bytes' => $totalBytes,
            'package_path'     => $tarPath,
            'package_sha256'   => $pkgSha,
            'schedule_codes'   => implode(',', array_keys($scheduleCodes)),
            'status'           => 'packaged',
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        if (!empty($items)) {
            DB::table('narssa_transfer_item')->insert($items);
        }

        try {
            DB::table('ahg_audit_log')->insert([
                'uuid'        => $this->generateUuid(),
                'action'      => 'narssa_transfer_packaged',
                'entity_type' => 'narssa_transfer',
                'entity_id'   => $transferId,
                'user_id'     => $initiatedBy,
                'new_values'  => json_encode([
                    'reference'    => $ref,
                    'item_count'   => count($items),
                    'bytes'        => $totalBytes,
                    'package_sha'  => $pkgSha,
                    'package_path' => $tarPath,
                ]),
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // non-fatal: audit table may be absent in trimmed installs
        }

        return [
            'transfer_id'     => $transferId,
            'reference'       => $ref,
            'package_path'    => $tarPath,
            'package_sha256'  => $pkgSha,
            'item_count'      => count($items),
            'total_bytes'     => $totalBytes,
            'digital_objects' => $totalDOs,
            'work_dir'        => $workDir,
        ];
    }

    public function buildFromApprovedDisposals(?int $initiatedBy = null): array
    {
        $rows = DB::table('disposal_action')
            ->where('status', 'approved')
            ->where('action_type', 'transfer_narssa')
            ->whereNull('transfer_manifest_path')
            ->select('id', 'information_object_id')
            ->get();
        if ($rows->isEmpty()) {
            return ['transfer_id' => null, 'message' => 'No approved transfer_narssa disposals found.'];
        }
        $ioIds = $rows->pluck('information_object_id')->all();
        $result = $this->build($ioIds, $initiatedBy, 'NARSSA transfer of approved disposals', null);

        foreach ($rows as $r) {
            DB::table('disposal_action')->where('id', $r->id)->update([
                'transfer_manifest_path' => $result['package_path'],
                'updated_at'             => date('Y-m-d H:i:s'),
            ]);
        }
        return $result;
    }

    private function allocateReference(): string
    {
        $year = date('Y');
        $count = (int) DB::table('narssa_transfer')
            ->where('transfer_reference', 'LIKE', "NARSSA-{$year}-%")
            ->count();
        return sprintf('NARSSA-%s-%03d', $year, $count + 1);
    }

    private function workDir(string $ref): string
    {
        $base = rtrim((string) config('heratio.uploads_path', base_path('uploads')), '/');
        $dir = $base . '/narssa/' . $ref;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private function loadIoDetail(int $ioId, string $culture): ?array
    {
        $row = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('retention_assignment as ra', 'io.id', '=', 'ra.information_object_id')
            ->leftJoin('retention_schedule as rs', 'ra.retention_schedule_id', '=', 'rs.id')
            ->leftJoin('disposal_action as da', function ($j) {
                $j->on('io.id', '=', 'da.information_object_id')
                  ->where('da.action_type', '=', 'transfer_narssa')
                  ->whereIn('da.status', ['approved', 'executed']);
            })
            ->where('io.id', $ioId)
            ->select(
                'io.id',
                'io.identifier',
                'ioi.title',
                'ioi.scope_and_content',
                'ioi.extent_and_medium',
                'rs.code as schedule_code',
                'rs.title as schedule_title',
                'da.id as disposal_action_id'
            )
            ->first();
        return $row ? (array) $row : null;
    }

    private function copyDigitalObjects(int $ioId, string $destDir): array
    {
        $count = 0;
        $bytes = 0;
        $dos = DB::table('digital_object')->where('object_id', $ioId)->select('path', 'name')->get();
        $uploadsBase = rtrim((string) config('heratio.uploads_path', base_path('uploads')), '/');
        foreach ($dos as $do) {
            if (empty($do->path)) {
                continue;
            }
            $src = $uploadsBase . '/' . ltrim((string) $do->path, '/') . '/' . (string) $do->name;
            if (!is_file($src)) {
                continue;
            }
            $dst = $destDir . '/' . basename($do->name);
            if (copy($src, $dst)) {
                $bytes += filesize($dst);
                $count++;
            }
        }
        return ['count' => $count, 'bytes' => $bytes];
    }

    private function buildEad2002(array $detail): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('ead');
        $w->writeAttribute('xmlns', 'urn:isbn:1-931666-22-9');
        $w->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $w->writeAttribute('xsi:schemaLocation', 'urn:isbn:1-931666-22-9 http://www.loc.gov/ead/ead.xsd');

        $w->startElement('eadheader');
        $w->writeAttribute('countryencoding', 'iso3166-1');
        $w->writeAttribute('langencoding', 'iso639-2b');
        $w->writeAttribute('repositoryencoding', 'iso15511');
        $w->writeAttribute('scriptencoding', 'iso15924');
        $w->writeAttribute('dateencoding', 'iso8601');
        $w->writeElement('eadid', (string) ($detail['identifier'] ?? ''));
        $w->startElement('filedesc');
        $w->startElement('titlestmt');
        $w->writeElement('titleproper', (string) ($detail['title'] ?? 'Untitled'));
        $w->endElement();
        $w->endElement();
        $w->endElement();

        $w->startElement('archdesc');
        $w->writeAttribute('level', 'item');
        $w->startElement('did');
        $w->writeElement('unitid', (string) ($detail['identifier'] ?? ''));
        $w->writeElement('unittitle', (string) ($detail['title'] ?? 'Untitled'));
        if (!empty($detail['extent_and_medium'])) {
            $w->writeElement('physdesc', strip_tags((string) $detail['extent_and_medium']));
        }
        $w->endElement();
        if (!empty($detail['scope_and_content'])) {
            $w->startElement('scopecontent');
            $w->writeElement('p', strip_tags((string) $detail['scope_and_content']));
            $w->endElement();
        }
        if (!empty($detail['schedule_code'])) {
            $w->startElement('processinfo');
            $w->writeElement('p', sprintf('Retention schedule: %s - %s', $detail['schedule_code'], $detail['schedule_title'] ?? ''));
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->endDocument();
        return $w->outputMemory();
    }

    private function buildMets(string $ref, array $items): string
    {
        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElement('mets');
        $w->writeAttribute('xmlns', 'http://www.loc.gov/METS/');
        $w->writeAttribute('OBJID', $ref);
        $w->writeAttribute('LABEL', 'NARSSA transfer ' . $ref);
        $w->writeAttribute('TYPE', 'archive-transfer');

        $w->startElement('metsHdr');
        $w->writeAttribute('CREATEDATE', date('c'));
        $w->endElement();

        $w->startElement('fileSec');
        $w->startElement('fileGrp');
        $w->writeAttribute('USE', 'archival-items');
        foreach ($items as $i => $it) {
            $w->startElement('file');
            $w->writeAttribute('ID', 'item-' . ($i + 1));
            $w->writeAttribute('CHECKSUM', (string) $it['sha256']);
            $w->writeAttribute('CHECKSUMTYPE', 'SHA-256');
            $w->writeAttribute('SIZE', (string) $it['digital_object_bytes']);
            $w->startElement('FLocat');
            $w->writeAttribute('LOCTYPE', 'OTHER');
            $w->writeAttribute('OTHERLOCTYPE', 'tar-path');
            $w->writeAttribute('xlink:href', 'items/' . $this->sanitiseRef($it['archival_reference'] ?? ('IO-' . $it['information_object_id'])));
            $w->endElement();
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endDocument();
        return $w->outputMemory();
    }

    private function hashDirectory(string $dir): string
    {
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $files[] = $f->getPathname();
            }
        }
        sort($files);
        $hash = hash_init('sha256');
        foreach ($files as $f) {
            hash_update($hash, basename($f) . ':');
            hash_update_file($hash, $f);
        }
        return hash_final($hash);
    }

    private function sanitiseRef(string $s): string
    {
        $s = preg_replace('/[^a-zA-Z0-9_\-\.]+/', '_', $s);
        return trim((string) $s, '_') ?: 'unknown';
    }

    private function culture(): string
    {
        try {
            return app()->getLocale() ?: 'en';
        } catch (\Throwable $e) {
            return 'en';
        }
    }

    private function generateUuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
        $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
