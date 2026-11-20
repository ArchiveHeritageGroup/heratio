<?php

/**
 * MediaCheckoutService - Heratio ahg-core
 *
 * heratio#1757 / #1758: check a record's master file out for editing in any
 * outside tool, and check the edited file back in.
 *
 *  - Check-out locks the master to one user (time and optional note) and
 *    offers the master plus a sidecar (XMP or JSON-LD) carrying the record's
 *    identifier, title, dates, rights and the current version number.
 *  - While it is checked out nobody else can replace the master: the guard
 *    sits in FileVersionService::replace(), the one path every replace and
 *    restore goes through, and in the edit form's replace.
 *  - Check-in stores the edited file as a new numbered version through
 *    FileVersionService (heratio#1759), with the check-in note, and releases
 *    the lock. Only the user who checked it out, or an administrator.
 *  - Cancel releases the lock without a new version (owner or administrator).
 *
 * Every step is written to the audit log and, when the preservation package
 * is installed, as a PREMIS event.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MediaCheckoutService
{
    public const TABLE = 'ahg_media_checkout';

    public const CHECKED_OUT = 'checked_out';

    public const CHECKED_IN = 'checked_in';

    public const CANCELLED = 'cancelled';

    public static function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /** The open check-out of a digital object, with the holder's username, or null. */
    public static function active(int $digitalObjectId): ?object
    {
        if (! self::available()) {
            return null;
        }

        return DB::table(self::TABLE.' as c')->leftJoin('user as u', 'u.id', '=', 'c.user_id')
            ->where('c.active_digital_object_id', $digitalObjectId)
            ->first(['c.*', 'u.username']);
    }

    /** Every open check-out, newest first, for the administrators' list. */
    public static function allActive(): \Illuminate\Support\Collection
    {
        if (! self::available()) {
            return collect();
        }

        return DB::table(self::TABLE.' as c')
            ->leftJoin('user as u', 'u.id', '=', 'c.user_id')
            ->leftJoin('digital_object as d', 'd.id', '=', 'c.digital_object_id')
            ->leftJoin('slug as s', 's.object_id', '=', 'c.information_object_id')
            ->leftJoin('information_object_i18n as i', function ($j) {
                $j->on('i.id', '=', 'c.information_object_id')->where('i.culture', '=', app()->getLocale());
            })
            ->whereNotNull('c.active_digital_object_id')
            ->orderByDesc('c.checked_out_at')
            ->get(['c.*', 'u.username', 'd.name as file_name', 's.slug', 'i.title']);
    }

    /**
     * Refuse a change to the master when someone other than the signed-in
     * user has it checked out. Called by FileVersionService::replace().
     */
    public static function assertNotHeldByOther(int $digitalObjectId): void
    {
        $lock = self::active($digitalObjectId);
        if ($lock && (int) $lock->user_id !== (int) auth()->id()) {
            throw new MediaCheckedOutException($lock);
        }
    }

    /** Check the master out to the signed-in user. */
    public static function checkout(object $master, ?string $note = null): object
    {
        $userId = (int) auth()->id();
        if (! self::available() || $userId <= 0) {
            throw new \RuntimeException(__('Check-out is not available.'));
        }
        if ($lock = self::active((int) $master->id)) {
            throw new MediaCheckedOutException($lock);
        }
        $path = DigitalObjectService::resolveDiskPath($master);
        if (! $path || ! is_file($path)) {
            throw new \RuntimeException(__('The current master file was not found on disk.'));
        }

        try {
            $id = DB::table(self::TABLE)->insertGetId([
                'digital_object_id' => (int) $master->id,
                'active_digital_object_id' => (int) $master->id,
                'information_object_id' => $master->object_id ? (int) $master->object_id : null,
                'user_id' => $userId,
                'status' => self::CHECKED_OUT,
                'note' => self::clip($note),
                'version_at_checkout' => self::currentVersion((int) $master->id),
                'sha256_at_checkout' => @hash_file('sha256', $path) ?: null,
                'checked_out_at' => now(),
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Someone else checked it out between the check above and the insert.
            throw new MediaCheckedOutException(self::active((int) $master->id));
        }
        $row = self::active((int) $master->id);
        self::record($master, 'media_checkout', 'checkout', 'Checked out for editing (version '.$row->version_at_checkout.')', [
            'checkout_id' => $id, 'version' => $row->version_at_checkout, 'note' => $row->note,
        ]);

        return $row;
    }

    /**
     * Check the edited file ($newFile, a local path) back in as a new version.
     * Returns the new current version row.
     */
    public static function checkin(object $master, string $newFile, ?string $note = null, bool $major = false, ?string $newName = null): object
    {
        $lock = self::active((int) $master->id);
        if (! $lock) {
            throw new \RuntimeException(__('This file is not checked out.'));
        }
        self::assertOwnerOrAdmin($lock);
        $current = DigitalObjectService::resolveDiskPath($master);
        if (! $current || ! is_file($current)) {
            throw new \RuntimeException(__('The current master file was not found on disk.'));
        }
        $note = self::clip($note);
        $versionNote = $note !== null ? __('Checked in: :note', ['note' => $note]) : __('Checked in');

        $version = DB::transaction(function () use ($master, $lock, $current, $newFile, $note, $versionNote, $major, $newName) {
            // Release first so the replace guard lets an administrator check in
            // someone else's file; a failed replace rolls the release back.
            self::close($lock, self::CHECKED_IN, $note);
            $version = FileVersionService::replace($master, $current, $newFile, $major, $versionNote, $newName);
            if (! $version) {
                throw new \RuntimeException(__('File versions are not available, so the file cannot be checked in.'));
            }
            DB::table(self::TABLE)->where('id', $lock->id)->update(['file_version_id' => $version->id]);

            return $version;
        });

        $v = $version->version_major.'.'.$version->version_minor;
        self::record($master, 'media_checkin', 'checkin', 'Checked in as version '.$v.' (checked out at version '.$lock->version_at_checkout.')', [
            'checkout_id' => (int) $lock->id, 'checked_out_by' => (int) $lock->user_id, 'version' => $v,
            'file_version_id' => (int) $version->id, 'note' => $note,
        ]);

        return $version;
    }

    /** Release the lock without a new version. */
    public static function cancel(object $master, ?string $note = null): void
    {
        $lock = self::active((int) $master->id);
        if (! $lock) {
            throw new \RuntimeException(__('This file is not checked out.'));
        }
        self::assertOwnerOrAdmin($lock);
        $note = self::clip($note);
        self::close($lock, self::CANCELLED, $note);
        self::record($master, 'media_checkout_cancel', 'checkout cancelled', 'Check-out cancelled, no new version', [
            'checkout_id' => (int) $lock->id, 'checked_out_by' => (int) $lock->user_id, 'note' => $note,
        ]);
    }

    /** The current version number, "1.0" when the master has never been replaced. */
    public static function currentVersion(int $digitalObjectId): string
    {
        $v = FileVersionService::available()
            ? DB::table(FileVersionService::TABLE)->where('digital_object_id', $digitalObjectId)->where('is_current', 1)->first(['version_major', 'version_minor'])
            : null;

        return $v ? $v->version_major.'.'.$v->version_minor : '1.0';
    }

    /**
     * The sidecar as a JSON-LD document in the XMP data model (the shape
     * XmpJsonLd reads and writes): dc:identifier, dc:title, dc:date,
     * dc:rights, xmpRights:WebStatement, and xmpMM:DocumentID / InstanceID /
     * VersionID for the file, the check-out and the current version.
     */
    public static function sidecar(object $master, ?object $lock = null): array
    {
        $ioId = (int) ($master->object_id ?? 0);
        $io = $ioId ? DB::table('information_object')->where('id', $ioId)->first(['identifier', 'source_culture']) : null;
        $culture = $io->source_culture ?? app()->getLocale();
        $title = $ioId ? (DB::table('information_object_i18n')->where('id', $ioId)->where('culture', $culture)->value('title')
            ?? DB::table('information_object_i18n')->where('id', $ioId)->whereNotNull('title')->value('title')) : null;

        $dates = [];
        if ($ioId) {
            $events = DB::table('event as e')
                ->leftJoin('event_i18n as ei', fn ($j) => $j->on('ei.id', '=', 'e.id')->where('ei.culture', '=', $culture))
                ->where('e.object_id', $ioId)->orderBy('e.id')->get(['e.start_date', 'e.end_date', 'ei.date']);
            foreach ($events as $e) {
                foreach ([$e->start_date, $e->end_date] as $d) {
                    if ($d && ! in_array($d, $dates, true)) {
                        $dates[] = (string) $d;
                    }
                }
            }
            // Only a free-text date (for example "circa 1950"): send it as given.
            if ($dates === []) {
                $dates = $events->pluck('date')->filter()->map(fn ($d) => (string) $d)->unique()->values()->all();
            }
        }

        $rights = [];
        $webStatement = null;
        if ($ioId && class_exists(\AhgApi\Services\RightsBlockService::class)) {
            try {
                foreach ((app(\AhgApi\Services\RightsBlockService::class)->forObject($ioId, true, $culture)['rules'] ?? []) as $r) {
                    foreach (['statement', 'licence'] as $k) {
                        if (! empty($r[$k]['label'])) {
                            $rights[] = (string) $r[$k]['label'];
                        }
                        $webStatement ??= $r[$k]['iri'] ?? null;
                    }
                    if (! empty($r['holder']['name'])) {
                        $rights[] = __('Rights holder: :name', ['name' => $r['holder']['name']]);
                    }
                }
            } catch (\Throwable) {
                // a sidecar without rights is better than no check-out
            }
        }

        $doc = [
            '@context' => [
                'dc' => 'http://purl.org/dc/elements/1.1/',
                'xmpMM' => 'http://ns.adobe.com/xap/1.0/mm/',
                'xmpRights' => 'http://ns.adobe.com/xap/1.0/rights/',
            ],
            '@id' => 'urn:heratio:digital-object:'.(int) $master->id,
        ];
        if ($io && (string) $io->identifier !== '') {
            $doc['dc:identifier'] = (string) $io->identifier;
        }
        if ($title) {
            $doc['dc:title'] = ['@type' => 'rdf:Alt', 'rdf:_1' => ['@value' => (string) $title, '@language' => 'x-default']];
        }
        if ($dates !== []) {
            $doc['dc:date'] = ['@list' => $dates];
        }
        if ($rights !== []) {
            $doc['dc:rights'] = ['@type' => 'rdf:Alt', 'rdf:_1' => ['@value' => implode('; ', array_unique($rights)), '@language' => 'x-default']];
        }
        if ($webStatement) {
            $doc['xmpRights:WebStatement'] = (string) $webStatement;
        }
        $doc['xmpMM:DocumentID'] = 'urn:heratio:digital-object:'.(int) $master->id;
        if ($lock) {
            $doc['xmpMM:InstanceID'] = 'urn:heratio:checkout:'.(int) $lock->id;
        }
        $doc['xmpMM:VersionID'] = $lock->version_at_checkout ?? self::currentVersion((int) $master->id);

        return $doc;
    }

    /** The sidecar as an XMP packet (RDF/XML). */
    public static function sidecarXmp(object $master, ?object $lock = null): string
    {
        $doc = self::sidecar($master, $lock);
        if (class_exists(\AhgMetadataExtraction\Xmp\XmpJsonLd::class)) {
            return \AhgMetadataExtraction\Xmp\XmpJsonLd::toRdfXml($doc);
        }
        throw new \RuntimeException(__('XMP sidecars need the metadata extraction package.'));
    }

    private static function assertOwnerOrAdmin(object $lock): void
    {
        if ((int) $lock->user_id !== (int) auth()->id() && ! AclService::isAdministrator(auth()->user())) {
            throw new MediaCheckedOutException($lock);
        }
    }

    private static function close(object $lock, string $status, ?string $note): void
    {
        DB::table(self::TABLE)->where('id', $lock->id)->update([
            'status' => $status,
            'active_digital_object_id' => null,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
            'close_note' => $note,
        ]);
    }

    private static function clip(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, 1000);
    }

    /** One audit row of its own, and a PREMIS event when preservation is installed. */
    private static function record(object $master, string $action, string $premisType, string $detail, array $data): void
    {
        \AhgCore\Support\AuditLog::captureSecondaryMutation(
            (int) ($master->object_id ?: $master->id),
            $master->object_id ? 'information_object' : 'digital_object',
            $action,
            ['data' => ['digital_object_id' => (int) $master->id, 'name' => $master->name ?? null] + $data]
        );
        if (! class_exists(\AhgPreservation\Services\PreservationService::class)) {
            return;
        }
        try {
            app(\AhgPreservation\Services\PreservationService::class)->logEvent((int) $master->id, ($master->object_id ?? null) ? (int) $master->object_id : null, $premisType, $detail, 'success');
        } catch (\Throwable) {
            // an event is a record of the change, not a condition for it
        }
    }
}
