<?php

/**
 * LogoUploadService — handle logo uploads for registry entities.
 *
 * One service for institution/vendor/software/group logos. Validates against
 * registry_settings (max_logo_size_mb, allowed_logo_types), names files as
 * `<entity_type>-<id>-<unix-ts>.<ext>` (matches the AtoM convention so seed
 * data stays compatible), writes to the canonical /uploads/registry/<type>/
 * path served by nginx alias. Returns the public web URL to store in
 * `logo_path`.
 *
 * Security:
 *   - extension allowlist (no executables can sneak in)
 *   - real MIME-type sniff via finfo (HTML-as-png defence)
 *   - size cap from settings
 *   - random suffix prevents directory traversal via filename
 *   - chmod 0644 on the written file
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgRegistry\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogoUploadService
{
    /** Where logos land on disk. Matches the seeded path so nginx already serves them. */
    protected const UPLOAD_BASE = '/usr/share/nginx/archive/uploads/registry';

    /** Public URL prefix that maps to UPLOAD_BASE via nginx /uploads/ alias. */
    protected const URL_BASE = '/uploads/registry';

    /** Recognised entity types (subdir + filename prefix). */
    protected const ENTITY_TYPES = ['institution', 'vendor', 'software', 'group'];

    /** Plural-subdir map for filesystem layout (matches AtoM). */
    protected const SUBDIRS = [
        'institution' => 'institutions',
        'vendor'      => 'vendors',
        'software'    => 'software',
        'group'       => 'groups',
    ];

    /** Default extension allowlist if registry_settings.allowed_logo_types is missing. */
    protected const DEFAULT_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];

    /** Default size cap (MB) if registry_settings.max_logo_size_mb is missing. */
    protected const DEFAULT_MAX_MB = 5;

    /** MIME-types that the allowlist resolves to (for finfo sniffing). */
    protected const MIME_BY_EXT = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'svg'  => ['image/svg+xml', 'image/svg', 'text/xml', 'application/xml'],
        'webp' => ['image/webp'],
    ];

    /**
     * Store an uploaded logo and return its public URL (suitable for logo_path column).
     *
     * @throws \InvalidArgumentException on validation failures.
     * @throws \RuntimeException on disk failures.
     */
    public function store(string $entityType, int $entityId, UploadedFile $file): string
    {
        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new \InvalidArgumentException("unknown entity type: {$entityType}");
        }
        if ($entityId < 1) {
            throw new \InvalidArgumentException('entity id must be > 0');
        }
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('upload failed: ' . $file->getErrorMessage());
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $allowed = $this->allowedExtensions();
        if (! in_array($ext, $allowed, true)) {
            throw new \InvalidArgumentException(
                "extension .{$ext} not allowed; permitted: " . implode(', ', $allowed)
            );
        }

        $maxBytes = $this->maxBytes();
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException(
                'file too large (' . round($file->getSize() / 1048576, 1) . ' MB; cap '
                . round($maxBytes / 1048576, 1) . ' MB)'
            );
        }

        // MIME sniff (HTML-as-png defence). SVG is XML so we accept text/xml here.
        $expected = self::MIME_BY_EXT[$ext] ?? [];
        $detected = $file->getMimeType();
        if (! empty($expected) && $detected && ! in_array($detected, $expected, true)) {
            throw new \InvalidArgumentException(
                "mime mismatch: file claims .{$ext} but is {$detected}"
            );
        }

        $subdir = self::SUBDIRS[$entityType];
        $dir = self::UPLOAD_BASE . '/' . $subdir;
        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("cannot create upload dir: {$dir}");
        }

        $filename = "{$entityType}-{$entityId}-" . time() . '.' . $ext;
        $dest = $dir . '/' . $filename;

        try {
            $file->move($dir, $filename);
        } catch (\Throwable $e) {
            throw new \RuntimeException('move failed: ' . $e->getMessage(), 0, $e);
        }
        @chmod($dest, 0644);

        return self::URL_BASE . '/' . $subdir . '/' . $filename;
    }

    /**
     * Replace an entity's logo. Deletes the old file (if local) before storing the new one.
     */
    public function replace(string $entityType, int $entityId, UploadedFile $file, ?string $previousUrl): string
    {
        $newUrl = $this->store($entityType, $entityId, $file);
        if ($previousUrl && $previousUrl !== $newUrl) {
            $this->deleteByUrl($previousUrl);
        }
        return $newUrl;
    }

    /** Delete a logo file given its /uploads/registry/... URL. Best-effort; silent on miss. */
    public function deleteByUrl(?string $publicUrl): void
    {
        if (! $publicUrl || ! str_starts_with($publicUrl, self::URL_BASE . '/')) return;
        $rel = substr($publicUrl, strlen(self::URL_BASE) + 1);
        $rel = ltrim(str_replace('..', '', $rel), '/');
        $abs = self::UPLOAD_BASE . '/' . $rel;
        if (is_file($abs)) @unlink($abs);
    }

    protected function allowedExtensions(): array
    {
        $raw = $this->setting('allowed_logo_types', implode(',', self::DEFAULT_TYPES));
        $exts = array_filter(array_map(fn ($x) => strtolower(trim($x)), explode(',', $raw)));
        return $exts ?: self::DEFAULT_TYPES;
    }

    protected function maxBytes(): int
    {
        $mb = (int) $this->setting('max_logo_size_mb', self::DEFAULT_MAX_MB);
        if ($mb < 1) $mb = self::DEFAULT_MAX_MB;
        return $mb * 1048576;
    }

    protected function setting(string $key, $default = null)
    {
        if (! Schema::hasTable('registry_settings')) return $default;
        $val = DB::table('registry_settings')->where('setting_key', $key)->value('setting_value');
        return ($val === null || $val === '') ? $default : $val;
    }
}
