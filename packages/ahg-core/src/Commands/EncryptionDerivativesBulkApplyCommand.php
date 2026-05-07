<?php

/**
 * EncryptionDerivativesBulkApplyCommand - Heratio
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

namespace AhgCore\Commands;

use AhgCore\Services\EncryptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Walks every digital_object row, resolves its on-disk path under
 * config('heratio.uploads_path'), and encrypts the file in place via
 * EncryptionService::encryptFile when encryption_encrypt_derivatives is
 * on. Idempotent: already-encrypted files are skipped via
 * isFileEncrypted's sentinel check.
 *
 * Closes #125 (the bulk-apply half of the derivative-encryption pipeline).
 * The streaming half (decrypt-on-read) lives in DigitalObjectController's
 * stream endpoint - operators must point file-serving routes at the
 * controller rather than nginx-direct when this is on.
 */
class EncryptionDerivativesBulkApplyCommand extends Command
{
    protected $signature = 'ahg:encryption-derivatives-bulk-apply
                            {--usage= : Limit to one usage_id (e.g. master, reference, thumbnail)}
                            {--dry-run : Count files without writing}';
    protected $description = 'Encrypt every digital_object derivative file when encryption_encrypt_derivatives is on.';

    public function handle(EncryptionService $svc): int
    {
        if (!$svc->shouldEncryptDerivatives()) {
            $this->error('encryption_encrypt_derivatives is off (or master encryption_enabled is off); refusing to run.');
            return self::FAILURE;
        }
        if (!Schema::hasTable('digital_object')) {
            $this->warn('digital_object table missing; nothing to do.');
            return self::SUCCESS;
        }

        $uploadsBase = (string) config('heratio.uploads_path');
        if ($uploadsBase === '') {
            $this->error('heratio.uploads_path config is empty; cannot resolve files.');
            return self::FAILURE;
        }
        $uploadsBase = rtrim($uploadsBase, '/');

        $query = DB::table('digital_object')->whereNotNull('path')->whereNotNull('name');
        if ($this->option('usage')) {
            $usage = $this->option('usage');
            // Resolve usage_id from the literal string when possible (numeric
            // passes through, names looked up via existing constants).
            if (is_numeric($usage)) {
                $query->where('usage_id', (int) $usage);
            } else {
                $usageMap = [
                    'master'    => \AhgCore\Services\DigitalObjectService::USAGE_MASTER ?? 1,
                    'reference' => 3,
                    'thumbnail' => 2,
                ];
                if (isset($usageMap[strtolower($usage)])) {
                    $query->where('usage_id', $usageMap[strtolower($usage)]);
                }
            }
        }

        $rows = $query->get(['id', 'path', 'name', 'usage_id']);
        $this->line('[derivatives-bulk-apply] candidates=' . $rows->count());

        $encrypted = 0;
        $alreadyEncrypted = 0;
        $missing = 0;
        $errors = 0;
        $dry = (bool) $this->option('dry-run');

        foreach ($rows as $r) {
            $local = self::resolveOnDiskPath($uploadsBase, (string) $r->path, (string) $r->name);
            if (!is_file($local)) {
                $missing++;
                continue;
            }
            if ($svc->isFileEncrypted($local)) {
                $alreadyEncrypted++;
                continue;
            }
            if ($dry) {
                $encrypted++; // Would-encrypt count under dry-run.
                continue;
            }
            if ($svc->encryptFile($local)) {
                $encrypted++;
            } else {
                $errors++;
            }
        }

        $this->line(sprintf(
            '[derivatives-bulk-apply] %s encrypted=%d already_encrypted=%d missing=%d errors=%d',
            $dry ? 'DRY-RUN' : 'live',
            $encrypted, $alreadyEncrypted, $missing, $errors
        ));
        return self::SUCCESS;
    }

    /**
     * Map digital_object's path (URI-style, /uploads/r/{ioId}/) + name
     * (filename) into an on-disk path under heratio.uploads_path.
     */
    private static function resolveOnDiskPath(string $uploadsBase, string $path, string $name): string
    {
        // Strip the leading /uploads/ prefix - that's the URL path, the
        // disk path is uploads_base + the rest.
        $rel = preg_replace('#^/uploads/#', '', $path);
        $rel = ltrim($rel, '/');
        return $uploadsBase . '/' . $rel . $name;
    }
}
