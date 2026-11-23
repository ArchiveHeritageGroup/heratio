<?php

namespace AhgPortableExport\Services;

use ZipArchive;

/**
 * Reads a built Portable Export zip: SHA-256 of every file, the records it
 * carries and what it says about its own disclosure checks.
 */
class PackageInspector
{
    /** Tree positions are renumbered by unrelated edits, so they are not a change to a record. */
    private const IGNORED_FIELDS = ['lft', 'rgt'];

    /**
     * @return array{files:array, records:array, asset_record_ids:int[], disclosure:?array, problems:string[]}
     */
    public function inspect(string $zipPath): array
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new ReleaseException('The package could not be opened.', ['The package is not a readable zip file.']);
        }

        $files = [];
        $problems = [];
        $assetIds = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with($name, '/')) {
                    continue;
                }
                if (! $this->isSafeName($name)) {
                    $problems[] = 'The package contains an unsafe path: '.$name;

                    continue;
                }
                $zip->getExternalAttributesIndex($i, $os, $attr);
                if ($os === ZipArchive::OPSYS_UNIX && (($attr >> 16) & 0170000) === 0120000) {
                    $problems[] = 'The package contains a symbolic link: '.$name;

                    continue;
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    $problems[] = 'A file in the package could not be read: '.$name;

                    continue;
                }
                $ctx = hash_init('sha256');
                $bytes = hash_update_stream($ctx, $stream);
                fclose($stream);
                $files[$name] = ['path' => $name, 'sha256' => hash_final($ctx), 'bytes' => $bytes];
                if (preg_match('#^assets/[^/]+/(\d+)/#', $name, $m) || preg_match('#^data/c2pa/io-(\d+)\.c2pa\.json$#', $name, $m)) {
                    $assetIds[(int) $m[1]] = true;
                }
            }

            // ponytail: decodes ios.json whole, as the bundle worker builds it whole.
            // Ceiling is memory on very large exports; upgrade path is a streaming parser.
            $ios = json_decode((string) $zip->getFromName('data/ios.json'), true);
            $disclosure = json_decode((string) $zip->getFromName('data/disclosure-summary.json'), true);
            $objects = json_decode((string) $zip->getFromName('data/digital_objects.json'), true);
            $sums = $zip->getFromName('SHA256SUMS');
        } finally {
            $zip->close();
        }

        $records = [];
        if (! is_array($ios)) {
            $problems[] = 'The package has no readable record list (data/ios.json).';
        } else {
            foreach ($ios as $io) {
                if (! is_array($io) || (int) ($io['id'] ?? 0) <= 0) {
                    $problems[] = 'The record list contains an entry with no valid id.';

                    continue;
                }
                $records[(int) $io['id']] = $this->fingerprint($io);
            }
        }

        // File names and paths are disclosure too, so a listed object counts as
        // carried by the package even when its file was not copied.
        if (! is_array($objects)) {
            $problems[] = 'The package has no readable digital object list (data/digital_objects.json).';
        } else {
            foreach ($objects as $object) {
                if (! is_array($object) || (int) ($object['object_id'] ?? 0) <= 0) {
                    $problems[] = 'The digital object list contains an entry with no valid record id.';

                    continue;
                }
                $assetIds[(int) $object['object_id']] = true;
            }
        }

        array_push($problems, ...$this->fixityProblems($sums, $files));

        ksort($files);

        return [
            'files' => array_values($files),
            'records' => $records,
            'asset_record_ids' => array_keys($assetIds),
            'disclosure' => is_array($disclosure) ? $disclosure : null,
            'problems' => $problems,
        ];
    }

    /**
     * Every asset must be listed and match. The worker does not list C2PA
     * sidecars, so one is accepted only beside an asset that is itself listed.
     *
     * @return string[]
     */
    private function fixityProblems(string|false $sums, array $files): array
    {
        if ($sums === false) {
            return ['The package has no SHA256SUMS file, so its fixity cannot be checked.'];
        }

        $problems = [];
        $listed = [];
        foreach (preg_split('/\R/', $sums, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            if (! preg_match('/^([a-f0-9]{64})\s+\*?(.+)$/', $line, $m)) {
                $problems[] = 'SHA256SUMS has a malformed line.';

                continue;
            }
            $listed[$m[2]] = true;
            if (($files[$m[2]]['sha256'] ?? null) !== $m[1]) {
                $problems[] = 'Fixity check failed for '.$m[2];
            }
        }

        $unlisted = [];
        foreach (array_keys($files) as $path) {
            if (! str_starts_with($path, 'assets/') || isset($listed[$path])) {
                continue;
            }
            if (str_ends_with($path, '.c2pa.json') && isset($listed[substr($path, 0, -strlen('.c2pa.json'))])) {
                continue;
            }
            $unlisted[] = $path;
        }
        if ($unlisted !== []) {
            $problems[] = count($unlisted).' asset(s) are not covered by SHA256SUMS: '.implode(', ', array_slice($unlisted, 0, 5));
        }

        return $problems;
    }

    /**
     * Identifier plus a short hash per field. Values are deliberately not kept:
     * the changelog is public and a corrected value may be the thing that had
     * to be removed.
     */
    private function fingerprint(array $io): array
    {
        $fields = [];
        foreach ($io as $field => $value) {
            if ($field === 'id' || in_array($field, self::IGNORED_FIELDS, true) || $value === null || $value === '') {
                continue;
            }
            $fields[$field] = substr(hash('sha256', json_encode($value, JSON_UNESCAPED_UNICODE)), 0, 16);
        }

        return ['identifier' => isset($io['identifier']) ? (string) $io['identifier'] : null, 'fields' => $fields];
    }

    private function isSafeName(string $name): bool
    {
        return ! str_starts_with($name, '/') && ! str_contains($name, '\\') && ! str_contains($name, "\0")
            && ! in_array('..', explode('/', $name), true);
    }
}
