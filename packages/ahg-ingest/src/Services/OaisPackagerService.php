<?php

/**
 * OaisPackagerService — Heratio ingest
 *
 * Builds OAIS Submission / Archival / Dissemination Information Packages
 * (SIP / AIP / DIP) for an information object. Uses BagIt (RFC 8493)
 * as the package format.
 *
 * Contents by type:
 *   SIP (Submission) — payload master(s) + descriptive XML + bag-info
 *   AIP (Archival)   — SIP content + PREMIS event export + fixity manifest
 *   DIP (Dissemination) — access derivatives only (no master) + descriptive
 *
 * Records into `preservation_package` + `preservation_package_object` +
 * `preservation_package_event` so the Admin → Preservation → Packages
 * view picks them up. Emits PREMIS events into `preservation_event`.
 *
 * Callers:
 *   - IngestService::commit() (wizard) — per-batch packages
 *   - ProcessScanFile::stagePackaging() (scanner) — per-file packages
 *   - On-demand from an admin "Build package" button (future)
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgIngest\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OaisPackagerService
{
    public const TYPE_SIP = 'sip';
    public const TYPE_AIP = 'aip';
    public const TYPE_DIP = 'dip';

    /**
     * Build a package for one information object.
     *
     * @param int    $ioId         information_object.id
     * @param string $type         'sip' | 'aip' | 'dip'
     * @param array  $options      'export_path' override, 'originator' string, 'retention' string
     *
     * @return int preservation_package.id
     *
     * @throws \RuntimeException on unrecoverable failure.
     */
    public function buildPackage(int $ioId, string $type, array $options = []): int
    {
        if (!in_array($type, [self::TYPE_SIP, self::TYPE_AIP, self::TYPE_DIP], true)) {
            throw new \RuntimeException("Unknown package type: {$type}");
        }

        $io = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('slug as sl', 'sl.object_id', '=', 'io.id')
            ->where('io.id', $ioId)
            ->select('io.id', 'io.identifier', 'i18n.title', 'i18n.scope_and_content',
                     'i18n.extent_and_medium', 'sl.slug')
            ->first();
        if (!$io) {
            throw new \RuntimeException("Information object {$ioId} not found");
        }

        $dos = DB::table('digital_object')->where('object_id', $ioId)->get();
        if ($dos->isEmpty()) {
            throw new \RuntimeException("IO {$ioId} has no digital objects — nothing to package");
        }

        $uuid = (string) Str::uuid();
        $packagesRoot = rtrim(config('heratio.packages_path'), '/');
        $workDir = $packagesRoot . '/' . $uuid;

        // Resolution order for the export directory:
        //   1. options['export_path']       (explicit per-call override)
        //   2. ingest_<type>_path setting   (operator-configured global default)
        //   3. {packages_path}/exports      (built-in fallback)
        // The settings layer is what wires ingest_aip_path / ingest_sip_path /
        // ingest_dip_path - issue #71's path-keys.
        $settingKey = 'ingest_' . strtolower($type) . '_path';
        $configuredPath = (string) \AhgCore\Services\AhgSettingsService::get($settingKey, '');
        $exportDir = $options['export_path']
            ?? ($configuredPath !== '' ? $configuredPath : ($packagesRoot . '/exports'));
        if (!is_dir($workDir) && !@mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            throw new \RuntimeException("Cannot create work dir: {$workDir}");
        }
        if (!is_dir($exportDir) && !@mkdir($exportDir, 0775, true) && !is_dir($exportDir)) {
            throw new \RuntimeException("Cannot create export dir: {$exportDir}");
        }

        // Bag structure: <workDir>/bagit.txt, bag-info.txt, manifest-sha256.txt,
        // tagmanifest-sha256.txt, data/ subdir.
        $dataDir = $workDir . '/data';
        @mkdir($dataDir, 0775, true);

        $includeMaster = $type !== self::TYPE_DIP;     // DIP = access derivatives only
        $includeDerivatives = $type !== self::TYPE_SIP; // SIP = what was submitted (master only)
        $includePremis = $type === self::TYPE_AIP;     // AIP includes full preservation record

        $included = $this->copyObjectsIntoBag($dos, $dataDir, $includeMaster, $includeDerivatives);
        $this->writeDescriptive($io, $dataDir . '/descriptive');
        if ($includePremis) {
            $this->writePremisEvents($ioId, $dataDir . '/premis');
            $this->writeFixityManifest($included, $dataDir . '/fixity');
        }

        // BagIt declaration + bag-info
        file_put_contents($workDir . '/bagit.txt', "BagIt-Version: 1.0\nTag-File-Character-Encoding: UTF-8\n");
        $this->writeBagInfo($workDir . '/bag-info.txt', $io, $type, $options);

        // Manifest (of data/ files) and tag-manifest (of tag files)
        $this->writeManifest($workDir, 'manifest-sha256.txt', $this->relativeTree($dataDir, $workDir));
        $this->writeManifest($workDir, 'tagmanifest-sha256.txt', [
            'bagit.txt', 'bag-info.txt', 'manifest-sha256.txt',
        ]);

        // Zip → export_path/<uuid>.zip
        $exportPath = $exportDir . '/' . $uuid . '.zip';
        $this->zipBag($workDir, $exportPath);
        $exportChecksum = hash_file('sha256', $exportPath);
        $totalSize = array_sum(array_column($included, 'size'));

        // Persist to preservation_package
        $name = sprintf('%s — %s [%s]', strtoupper($type), $io->identifier ?: 'Untitled', substr($uuid, 0, 8));
        $packageId = DB::table('preservation_package')->insertGetId([
            'uuid' => $uuid,
            'name' => mb_substr($name, 0, 255),
            'description' => "Auto-built {$type} for IO #{$ioId}",
            'package_type' => $type,
            'status' => 'exported',
            'package_format' => 'bagit',
            'bagit_version' => '1.0',
            'object_count' => count($included),
            'total_size' => $totalSize,
            'manifest_algorithm' => 'sha256',
            'package_checksum' => $exportChecksum,
            'source_path' => $workDir,
            'export_path' => $exportPath,
            'originator' => mb_substr((string) ($options['originator'] ?? 'heratio-ingest'), 0, 255),
            'retention_period' => $options['retention'] ?? null,
            'information_object_id' => $ioId,
            'created_at' => now(),
            'created_by' => $options['created_by'] ?? null,
            'built_at' => now(),
            'exported_at' => now(),
            'metadata' => json_encode(['object_count' => count($included), 'payload_bytes' => $totalSize]),
        ]);

        foreach ($included as $seq => $row) {
            DB::table('preservation_package_object')->insert([
                'package_id' => $packageId,
                'digital_object_id' => $row['do_id'],
                'relative_path' => $row['rel'],
                'file_name' => basename($row['rel']),
                'file_size' => $row['size'],
                'checksum_algorithm' => 'sha256',
                'checksum_value' => $row['sha'],
                'mime_type' => $row['mime'],
                'puid' => $row['puid'],
                'object_role' => $row['role'],
                'sequence' => $seq,
            ]);
        }

        DB::table('preservation_package_event')->insert([
            'package_id' => $packageId,
            'event_type' => 'packageBuilt',
            'event_datetime' => now(),
            'event_detail' => "Built {$type} package ({$exportPath})",
            'event_outcome' => 'success',
            'agent_type' => 'system',
            'agent_value' => 'OaisPackagerService',
        ]);

        // Also emit a PREMIS event per package type so the IO's event chain reflects it.
        $this->emitPremis($ioId, $type, $packageId, $exportPath, $exportChecksum);

        return $packageId;
    }

    // ----------------------------------------------------------------
    // Package assembly
    // ----------------------------------------------------------------

    /**
     * Copy masters and/or derivatives into data/payload/ and data/access/.
     * Returns a list describing each copied file for manifest + DB rows.
     */
    protected function copyObjectsIntoBag(\Illuminate\Support\Collection $dos, string $dataDir, bool $includeMaster, bool $includeDerivatives): array
    {
        $payloadDir = $dataDir . '/payload';
        $accessDir = $dataDir . '/access';
        $included = [];

        foreach ($dos as $do) {
            $srcPath = $this->resolveDoSourcePath($do);
            if (!$srcPath || !is_file($srcPath)) { continue; }

            // Master = no parent_id. Derivatives = parent_id != null.
            $isMaster = empty($do->parent_id);
            $isDerivative = !$isMaster;

            if ($isMaster && !$includeMaster) { continue; }
            if ($isDerivative && !$includeDerivatives) { continue; }

            $targetDir = $isMaster ? $payloadDir : $accessDir;
            if (!is_dir($targetDir)) { @mkdir($targetDir, 0775, true); }
            $targetPath = $targetDir . '/' . $do->name;
            if (!@copy($srcPath, $targetPath)) { continue; }

            $rel = ($isMaster ? 'data/payload/' : 'data/access/') . $do->name;
            $included[] = [
                'do_id' => $do->id,
                'rel' => $rel,
                'size' => filesize($targetPath) ?: 0,
                'sha' => hash_file('sha256', $targetPath),
                'mime' => $do->mime_type,
                'puid' => $this->lookupPuid($do->mime_type),
                'role' => $isMaster ? 'payload' : 'access_derivative',
            ];
        }
        return $included;
    }

    protected function resolveDoSourcePath(object $do): ?string
    {
        $uploads = rtrim(config('heratio.uploads_path'), '/');
        // Canonical: {uploads}/<io_id>/<name>
        $candidate = $uploads . '/' . $do->object_id . '/' . $do->name;
        if (is_file($candidate)) { return $candidate; }
        // AtoM legacy: path field + name
        if (!empty($do->path)) {
            $alt = $uploads . '/' . ltrim($do->path, '/') . $do->name;
            if (is_file($alt)) { return $alt; }
        }
        return null;
    }

    protected function lookupPuid(?string $mime): ?string
    {
        if (!$mime) { return null; }
        return DB::table('preservation_format')->where('mime_type', $mime)->value('puid');
    }

    // ----------------------------------------------------------------
    // Descriptive XML (Dublin Core for now — sector-specific export later)
    // ----------------------------------------------------------------

    protected function writeDescriptive(object $io, string $dir): void
    {
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $ident = htmlspecialchars($io->identifier ?? '', ENT_XML1);
        $title = htmlspecialchars($io->title ?? 'Untitled', ENT_XML1);
        $scope = htmlspecialchars($io->scope_and_content ?? '', ENT_XML1);
        $extent = htmlspecialchars($io->extent_and_medium ?? '', ENT_XML1);
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<dc:record xmlns:dc="http://purl.org/dc/elements/1.1/"
           xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:identifier>{$ident}</dc:identifier>
  <dc:title>{$title}</dc:title>
  <dc:description>{$scope}</dc:description>
  <dcterms:extent>{$extent}</dcterms:extent>
  <dcterms:isPartOf>heratio:io/{$io->id}</dcterms:isPartOf>
</dc:record>

XML;
        $name = ($io->identifier ? preg_replace('/[^a-zA-Z0-9_.-]/', '_', $io->identifier) : 'io-' . $io->id);
        file_put_contents($dir . '/' . $name . '.dc.xml', $xml);
    }

    // ----------------------------------------------------------------
    // AIP: PREMIS event export + fixity manifest
    // ----------------------------------------------------------------

    protected function writePremisEvents(int $ioId, string $dir): void
    {
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $events = DB::table('preservation_event')
            ->where('information_object_id', $ioId)
            ->orderBy('id')
            ->get();

        $body = "";
        foreach ($events as $e) {
            $detail = htmlspecialchars($e->event_detail ?? '', ENT_XML1);
            $outcome = htmlspecialchars($e->event_outcome ?? 'unknown', ENT_XML1);
            $agent = htmlspecialchars($e->linking_agent_value ?? 'heratio', ENT_XML1);
            $type = htmlspecialchars($e->event_type, ENT_XML1);
            $body .= "  <premis:event>\n";
            $body .= "    <premis:eventIdentifier><premis:eventIdentifierType>heratio:event</premis:eventIdentifierType><premis:eventIdentifierValue>{$e->id}</premis:eventIdentifierValue></premis:eventIdentifier>\n";
            $body .= "    <premis:eventType>{$type}</premis:eventType>\n";
            $body .= "    <premis:eventDateTime>{$e->event_datetime}</premis:eventDateTime>\n";
            $body .= "    <premis:eventDetail>{$detail}</premis:eventDetail>\n";
            $body .= "    <premis:eventOutcome>{$outcome}</premis:eventOutcome>\n";
            $body .= "    <premis:linkingAgentIdentifier><premis:linkingAgentIdentifierType>system</premis:linkingAgentIdentifierType><premis:linkingAgentIdentifierValue>{$agent}</premis:linkingAgentIdentifierValue></premis:linkingAgentIdentifier>\n";
            $body .= "  </premis:event>\n";
        }
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<premis:premis xmlns:premis=\"http://www.loc.gov/premis/v3\" version=\"3.0\">\n{$body}</premis:premis>\n";
        file_put_contents($dir . '/events.xml', $xml);
    }

    protected function writeFixityManifest(array $included, string $dir): void
    {
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $lines = [];
        foreach ($included as $row) {
            $lines[] = sprintf('%s  %s', $row['sha'], $row['rel']);
        }
        file_put_contents($dir . '/payload-sha256.txt', implode("\n", $lines) . "\n");
    }

    // ----------------------------------------------------------------
    // BagIt manifest + bag-info + zip
    // ----------------------------------------------------------------

    protected function writeBagInfo(string $path, object $io, string $type, array $options): void
    {
        $lines = [
            'Source-Organization: ' . ($options['originator'] ?? 'The Archive and Heritage Group'),
            'Contact-Name: heratio ingest',
            'Bagging-Date: ' . now()->toDateString(),
            'External-Identifier: ' . ($io->identifier ?: ('heratio:io/' . $io->id)),
            'Internal-Sender-Identifier: heratio:io/' . $io->id,
            'Package-Type: ' . strtoupper($type),
            'Heratio-Version: 1.0',
        ];
        if (!empty($options['retention'])) {
            $lines[] = 'Retention-Period: ' . $options['retention'];
        }
        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    /**
     * Write a manifest-<alg>.txt file from a list of paths relative to $workDir.
     */
    protected function writeManifest(string $workDir, string $manifestName, array $relPaths): void
    {
        $out = [];
        foreach ($relPaths as $rel) {
            $abs = $workDir . '/' . $rel;
            if (!is_file($abs)) { continue; }
            $sha = hash_file('sha256', $abs);
            $out[] = "{$sha}  {$rel}";
        }
        file_put_contents($workDir . '/' . $manifestName, implode("\n", $out) . "\n");
    }

    /**
     * Recursively list files under $dir, returning paths relative to $baseDir
     * (i.e. "data/payload/foo.tiff").
     */
    protected function relativeTree(string $dir, string $baseDir): array
    {
        $out = [];
        if (!is_dir($dir)) { return $out; }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if (!$f->isFile()) { continue; }
            $out[] = substr($f->getPathname(), strlen($baseDir) + 1);
        }
        sort($out);
        return $out;
    }

    protected function zipBag(string $workDir, string $outZip): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($outZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Cannot open zip for write: {$outZip}");
        }
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($workDir, \FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            if (!$f->isFile()) { continue; }
            $abs = $f->getPathname();
            $rel = substr($abs, strlen($workDir) + 1);
            $zip->addFile($abs, $rel);
        }
        $zip->close();
    }

    // ----------------------------------------------------------------
    // PREMIS event emission for the package itself
    // ----------------------------------------------------------------

    protected function emitPremis(int $ioId, string $type, int $packageId, string $exportPath, string $checksum): void
    {
        $eventType = match ($type) {
            self::TYPE_SIP => 'accession (SIP)',
            self::TYPE_AIP => 'preservation (AIP)',
            self::TYPE_DIP => 'dissemination (DIP)',
            default => 'packaging',
        };
        DB::table('preservation_event')->insert([
            'information_object_id' => $ioId,
            'event_type' => $eventType,
            'event_datetime' => now(),
            'event_detail' => "Built {$type} package #{$packageId} at {$exportPath}",
            'event_outcome' => 'success',
            'event_outcome_detail' => json_encode(['package_id' => $packageId, 'checksum' => $checksum]),
            'linking_agent_type' => 'system',
            'linking_agent_value' => 'OaisPackagerService',
            'linking_object_type' => 'preservation_package',
            'linking_object_value' => (string) $packageId,
            'created_at' => now(),
        ]);
    }
}
