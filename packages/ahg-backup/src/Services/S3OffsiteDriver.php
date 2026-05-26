<?php

/**
 * S3OffsiteDriver - S3-compatible off-site replication driver
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

namespace AhgBackup\Services;

use RuntimeException;

/**
 * S3-compatible off-site driver. Designed to work against AWS S3, Wasabi,
 * Backblaze B2 (S3 API), MinIO, DigitalOcean Spaces, etc. by honouring an
 * optional `endpoint` config key. SDK is loaded reflectively so the
 * operator only needs `aws/aws-sdk-php` installed when this driver is
 * actually selected - bare Heratio installs do NOT pull the SDK as a hard
 * dependency.
 *
 * Issue #671 Phase 3.
 */
class S3OffsiteDriver implements OffsiteDriverInterface
{
    /** @var array<string,mixed> */
    private array $config;

    /** @var mixed lazily instantiated Aws\S3\S3Client */
    private $client = null;

    /**
     * @param array<string,mixed> $config keys: bucket, region, endpoint,
     *   key, secret, prefix, use_path_style_endpoint
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        if (empty($config['bucket'])) {
            throw new RuntimeException('S3OffsiteDriver: backup.offsite.s3.bucket is required.');
        }
    }

    public function name(): string
    {
        return 's3';
    }

    public function push(string $localPath): array
    {
        if (!is_file($localPath)) {
            throw new RuntimeException("S3OffsiteDriver: local file not found: {$localPath}");
        }

        $size = (int) filesize($localPath);
        $sha = hash_file('sha256', $localPath);
        if ($sha === false) {
            throw new RuntimeException("S3OffsiteDriver: failed to hash {$localPath}");
        }

        $remoteKey = $this->keyFor($localPath);
        $client = $this->client();

        try {
            // We pass the SHA-256 in metadata so verify() can compare
            // without a full re-download where the bucket has integrity
            // checksums disabled. Real S3 also accepts the
            // x-amz-checksum-sha256 header; we set metadata as the
            // portable fallback.
            $client->putObject([
                'Bucket'      => $this->config['bucket'],
                'Key'         => $remoteKey,
                'SourceFile'  => $localPath,
                'Metadata'    => ['sha256' => $sha],
                'ContentType' => 'application/gzip',
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('S3OffsiteDriver: putObject failed: '.$e->getMessage(), 0, $e);
        }

        return [
            'remote_path' => $remoteKey,
            'size_bytes'  => $size,
            'sha256'      => $sha,
        ];
    }

    public function verify(string $remotePath, string $expectedSha256): bool
    {
        $client = $this->client();
        try {
            $head = $client->headObject([
                'Bucket' => $this->config['bucket'],
                'Key'    => $remotePath,
            ]);
        } catch (\Throwable $e) {
            return false;
        }

        // headObject returns Aws\Result; metadata key is lowercased.
        $meta = (array) ($head['Metadata'] ?? []);
        $remoteSha = $meta['sha256'] ?? ($meta['Sha256'] ?? null);

        if ($remoteSha && hash_equals(strtolower($expectedSha256), strtolower((string) $remoteSha))) {
            return true;
        }

        // Fallback: pull the object to a temp file and rehash. Slower,
        // but the only honest answer for buckets where putObject metadata
        // was lost (e.g. provider stripped it, or object was overwritten
        // outside Heratio).
        $tmp = tempnam(sys_get_temp_dir(), 'ahg-backup-verify-');
        if ($tmp === false) {
            return false;
        }
        try {
            if (!$this->pull($remotePath, $tmp)) {
                return false;
            }
            $actual = hash_file('sha256', $tmp);
            return $actual !== false && hash_equals($expectedSha256, $actual);
        } finally {
            @unlink($tmp);
        }
    }

    public function pull(string $remotePath, string $localPath): bool
    {
        $client = $this->client();
        try {
            $client->getObject([
                'Bucket' => $this->config['bucket'],
                'Key'    => $remotePath,
                'SaveAs' => $localPath,
            ]);
            return is_file($localPath);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function list(?\DateTimeInterface $since = null): array
    {
        $client = $this->client();
        $prefix = (string) ($this->config['prefix'] ?? '');
        $out = [];

        try {
            $token = null;
            do {
                $args = [
                    'Bucket' => $this->config['bucket'],
                    'Prefix' => $prefix,
                ];
                if ($token !== null) {
                    $args['ContinuationToken'] = $token;
                }
                $res = $client->listObjectsV2($args);
                foreach (($res['Contents'] ?? []) as $obj) {
                    $modified = $obj['LastModified'] ?? null;
                    if ($since !== null && $modified instanceof \DateTimeInterface && $modified < $since) {
                        continue;
                    }
                    $key = (string) ($obj['Key'] ?? '');
                    if ($key === '') {
                        continue;
                    }
                    $out[$key] = [
                        'size'          => (int) ($obj['Size'] ?? 0),
                        'last_modified' => $modified instanceof \DateTimeInterface
                            ? $modified->format('c')
                            : (string) $modified,
                        'sha256'        => null,
                    ];
                }
                $token = $res['IsTruncated'] ?? false ? ($res['NextContinuationToken'] ?? null) : null;
            } while ($token);
        } catch (\Throwable $e) {
            throw new RuntimeException('S3OffsiteDriver: list failed: '.$e->getMessage(), 0, $e);
        }

        return $out;
    }

    private function keyFor(string $localPath): string
    {
        $prefix = trim((string) ($this->config['prefix'] ?? ''), '/');
        $basename = basename($localPath);
        return $prefix === '' ? $basename : ($prefix.'/'.$basename);
    }

    /**
     * Lazy-load the AWS SDK. We tolerate the SDK being absent so bare
     * installs (drivers other than s3) do not need to require ~30MB of
     * vendor bloat. Operators who select the s3 driver MUST install
     * `aws/aws-sdk-php` via composer.
     */
    private function client()
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!class_exists('\\Aws\\S3\\S3Client')) {
            throw new RuntimeException(
                'S3OffsiteDriver requires the aws/aws-sdk-php package. '.
                'Run `composer require aws/aws-sdk-php` in the Heratio root, '.
                'then retry `php artisan backup:replicate`.'
            );
        }

        $params = [
            'version' => 'latest',
            'region'  => $this->config['region'] ?? 'us-east-1',
            'credentials' => [
                'key'    => (string) ($this->config['key'] ?? ''),
                'secret' => (string) ($this->config['secret'] ?? ''),
            ],
        ];
        if (!empty($this->config['endpoint'])) {
            $params['endpoint'] = $this->config['endpoint'];
        }
        if (!empty($this->config['use_path_style_endpoint'])) {
            $params['use_path_style_endpoint'] = true;
        }

        $cls = '\\Aws\\S3\\S3Client';
        $this->client = new $cls($params);
        return $this->client;
    }
}
