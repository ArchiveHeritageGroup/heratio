<?php

/**
 * PortableReleaseService - numbered, frozen, citable versions of a Portable
 * Export (#1586).
 *
 *   freeze    copy the package, hash every file, write manifest + changelog
 *   approve   an archivist picks the deposit target; nothing leaves before this
 *   publish   DOIs as drafts, then the deposit, then the DOIs made findable
 *
 * Findable DOIs and published external deposits are permanent, so they come
 * last, after the steps that can still fail harmlessly. Each step is idempotent
 * and a failed publish stays 'approved' with its error, ready to retry.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgPortableExport\Services;

use AhgDoiManage\Services\ReleaseDoiService;
use AhgPortableExport\Services\Deposit\DepositDriver;
use AhgPortableExport\Services\Deposit\InstitutionDepositDriver;
use AhgPortableExport\Services\Deposit\ZenodoDepositDriver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortableReleaseService
{
    public const FROZEN = 'frozen';
    public const APPROVED = 'approved';
    public const RELEASED = 'released';
    public const ABANDONED = 'abandoned';

    /** Longer than FreezeReleaseJob's timeout, so a live job is never declared dead. */
    public const FREEZE_STALE_SECONDS = 2000;

    private const JSON = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function __construct(
        private ReleaseDoiService $doi,
        private PackageInspector $inspector,
        private ReleaseGate $gate,
    ) {}

    public function createSeries(int $exportId, array $data, int $userId): int
    {
        $export = DB::table('portable_export')->where('id', $exportId)->first();
        if (! $export) {
            throw new ReleaseException('Export not found.', ['The chosen export does not exist.']);
        }

        return (int) DB::table('portable_release_series')->insertGetId([
            'series_key' => bin2hex(random_bytes(6)),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'creator_name' => $data['creator_name'],
            'publisher' => $data['publisher'],
            'license' => $data['license'] ?? null,
            'repository_id' => $this->owningRepository($export),
            'scope_hash' => $this->scopeHash($export),
            'scope_type' => $export->scope_type,
            'scope_slug' => $export->scope_slug,
            'scope_repository_id' => $export->scope_repository_id,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function addCorrection(int $seriesId, array $data, int $userId): int
    {
        return (int) DB::table('portable_release_correction')->insertGetId([
            'series_id' => $seriesId,
            'correction_type' => $data['correction_type'],
            'record_ref' => $data['record_ref'] ?? null,
            'field_name' => $data['field_name'] ?? null,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'reason' => $data['reason'],
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Record a freeze for the queue. One at a time per series. */
    public function requestFreeze(int $seriesId, int $exportId, int $userId, ?string $notes = null): int
    {
        return DB::transaction(function () use ($seriesId, $exportId, $userId, $notes) {
            if (! DB::table('portable_release_series')->where('id', $seriesId)->lockForUpdate()->exists()) {
                throw new ReleaseException('Freeze refused.', ['The series does not exist.']);
            }
            $cutoff = now()->subSeconds(self::FREEZE_STALE_SECONDS);
            $stale = DB::table('portable_release_freeze_request')->where('series_id', $seriesId)
                ->whereIn('status', ['queued', 'running'])
                ->whereRaw('COALESCE(started_at, created_at) < ?', [$cutoff])->pluck('id');
            foreach ($stale as $id) {
                $this->failFreezeRequest((int) $id, 'The freeze stopped without finishing. Try again.');
            }
            if (DB::table('portable_release_freeze_request')->where('series_id', $seriesId)->whereIn('status', ['queued', 'running'])->exists()) {
                throw new ReleaseException('Freeze refused.', ['Another version of this series is being frozen.']);
            }

            return (int) DB::table('portable_release_freeze_request')->insertGetId([
                'series_id' => $seriesId,
                'export_id' => $exportId,
                'release_notes' => $notes,
                'status' => 'queued',
                'requested_by' => $userId,
                'created_at' => now(),
            ]);
        });
    }

    /** The queued half of a freeze. Records its own outcome on the request row. */
    public function runFreezeRequest(int $requestId): void
    {
        $claimed = DB::table('portable_release_freeze_request')->where('id', $requestId)->where('status', 'queued')
            ->update(['status' => 'running', 'started_at' => now()]);
        if ($claimed !== 1) {
            return;
        }
        $request = DB::table('portable_release_freeze_request')->where('id', $requestId)->first();
        try {
            $releaseId = $this->freeze((int) $request->series_id, (int) $request->export_id, (int) $request->requested_by, $request->release_notes);
            DB::table('portable_release_freeze_request')->where('id', $requestId)
                ->update(['status' => 'done', 'release_id' => $releaseId, 'finished_at' => now()]);
        } catch (ReleaseException $e) {
            $this->recordFreezeFailure($requestId, $e->failures ?: [$e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);
            $this->recordFreezeFailure($requestId, ['The freeze failed unexpectedly. See the application log.']);
        }
    }

    /** For a job the worker gave up on: its finally blocks never ran, so the lock is cleared here. */
    public function failFreezeRequest(int $requestId, string $message): void
    {
        $request = DB::table('portable_release_freeze_request')->where('id', $requestId)->first();
        if (! $request || ! in_array($request->status, ['queued', 'running'], true)) {
            return;
        }
        $this->recordFreezeFailure($requestId, [$message]);
        if ($request->status === 'running') {
            Cache::lock('portable-release-freeze:'.$request->series_id)->forceRelease();
        }
    }

    private function recordFreezeFailure(int $requestId, array $failures): void
    {
        DB::table('portable_release_freeze_request')->where('id', $requestId)->whereIn('status', ['queued', 'running'])->update([
            'status' => 'failed',
            'failures' => json_encode(array_slice(array_values($failures), 0, 50), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'finished_at' => now(),
        ]);
    }

    /**
     * Give up on a version that has not gone public, so the series can move on.
     * The row and its number stay: numbers are never reused. Its corrections go
     * back to the pending log for the next version.
     */
    public function abandon(int $releaseId, string $reason, int $userId): void
    {
        $lock = Cache::lock('portable-release-publish:'.$releaseId, 7200);
        if (! $lock->get()) {
            throw new ReleaseException('Abandon refused.', ['This version is being published right now.']);
        }
        try {
            $this->abandonLocked($releaseId, $reason, $userId);
        } finally {
            $lock->release();
        }
    }

    private function abandonLocked(int $releaseId, string $reason, int $userId): void
    {
        $release = $this->release($releaseId);
        if (! in_array($release->status, [self::FROZEN, self::APPROVED], true)) {
            throw new ReleaseException('Abandon refused.', ['Only a frozen or approved version that has not been released can be abandoned.']);
        }
        if ($release->status === self::APPROVED) {
            $this->assertNothingPublic($release);
        }

        DB::transaction(function () use ($release, $reason, $userId) {
            $claimed = DB::table('portable_release')->where('id', $release->id)->where('status', $release->status)
                ->update(['status' => self::ABANDONED, 'last_error' => null, 'updated_at' => now()]);
            if ($claimed !== 1) {
                throw new ReleaseException('Abandon refused.', ['This version changed just now. Reload the page.']);
            }
            DB::table('portable_release_abandonment')->insert([
                'release_id' => $release->id,
                'previous_status' => $release->status,
                'reason' => $reason,
                'abandoned_by' => $userId,
                'abandoned_at' => now(),
            ]);
            DB::table('portable_release_correction')->where('release_id', $release->id)->update(['release_id' => null]);
            (new \AhgAuditTrail\Services\AuditLogger)->logUpdate('portable_release', (int) $release->id,
                ['status' => $release->status], ['status' => self::ABANDONED],
                ['series_id' => (int) $release->series_id, 'version' => (int) $release->version_number, 'reason' => $reason]);
        });
    }

    /** A findable DOI or a published deposit is permanent; an unpublished draft deposit is removed. */
    private function assertNothingPublic(object $release): void
    {
        if ($release->doi_state === 'findable') {
            throw new ReleaseException('Abandon refused.', ['The version DOI is already findable and cannot be withdrawn.']);
        }
        $series = DB::table('portable_release_series')->where('id', $release->series_id)->first();
        if ($release->doi_state === 'draft') {
            $remote = $this->doi->remoteState($series->repository_id !== null ? (int) $series->repository_id : null, $release->version_doi);
            if (! $remote['ok']) {
                throw new ReleaseException('Abandon refused.', ['DataCite could not confirm the version DOI is still a draft. Try again later.']);
            }
            if (! in_array($remote['state'], [null, 'draft'], true)) {
                throw new ReleaseException('Abandon refused.', ['The version DOI is already public at DataCite and cannot be withdrawn.']);
            }
        }

        $deposit = DB::table('portable_release_deposit')->where('release_id', $release->id)->first();
        if (! $deposit) {
            return;
        }
        $target = DB::table('portable_release_target')->where('id', $deposit->target_id)->first();
        if (! $target) {
            if (! empty($deposit->external_id)) {
                throw new ReleaseException('Abandon refused.', ['The deposit target no longer exists, so the deposit cannot be confirmed as unpublished.']);
            }

            return;
        }
        if ($deposit->status === 'deposited' && $target->target_type !== 'institution') {
            throw new ReleaseException('Abandon refused.', ['This version has already been deposited externally.']);
        }
        $this->driver($target)->discard($target, $deposit);
    }

    /** Create the next numbered version from a completed export and freeze it. */
    public function freeze(int $seriesId, int $exportId, int $userId, ?string $notes = null): int
    {
        $lock = Cache::lock('portable-release-freeze:'.$seriesId, 3600);
        if (! $lock->get()) {
            throw new ReleaseException('Freeze refused.', ['Another version of this series is being frozen.']);
        }
        try {
            return $this->freezeLocked($seriesId, $exportId, $userId, $notes);
        } finally {
            $lock->release();
        }
    }

    private function freezeLocked(int $seriesId, int $exportId, int $userId, ?string $notes): int
    {
        $series = DB::table('portable_release_series')->where('id', $seriesId)->first();
        $export = DB::table('portable_export')->where('id', $exportId)->first();
        if (! $series || ! $export) {
            throw new ReleaseException('Freeze refused.', ['The series or export does not exist.']);
        }
        // This series' lock is held, so any temporary folder is from a killed worker.
        $root = rtrim((string) config('heratio.storage_path'), '/').'/portable-releases/'.$series->series_key;
        foreach (glob($root.'/.tmp-*', GLOB_ONLYDIR) ?: [] as $stale) {
            $this->removeDir($stale);
        }
        if (! hash_equals($series->scope_hash, $this->scopeHash($export))) {
            throw new ReleaseException('Freeze refused.', ['This export covers a different scope from the series.']);
        }
        if (empty($export->output_path) || ! file_exists($export->output_path)) {
            throw new ReleaseException('Freeze refused.', ['The export file is no longer on disk. Build a new export.']);
        }
        if (DB::table('portable_release')->where('series_id', $seriesId)->where('export_id', $exportId)->exists()) {
            throw new ReleaseException('Freeze refused.', ['This export has already been used for a version of this series. Build a new export.']);
        }

        $latest = DB::table('portable_release')->where('series_id', $seriesId)
            ->where('status', '!=', self::ABANDONED)->orderByDesc('version_number')->first();
        if ($latest && $latest->status !== self::RELEASED && ! $this->isStranded($latest)) {
            throw new ReleaseException('Freeze refused.', ['Version '.$latest->version_number.' must be released or abandoned first.']);
        }
        // A stranded version never became a release, so changes are listed from the last real one.
        $previous = $latest && $latest->status !== self::RELEASED
            ? $this->releasedBefore($seriesId, (int) $latest->version_number) : $latest;

        $config = $this->doi->configFor($series->repository_id !== null ? (int) $series->repository_id : null);
        if ($problem = $this->doi->unconfiguredResult($config, 'registration')) {
            throw new ReleaseException('Freeze refused.', [$problem['error']]);
        }

        // Counted over every row: an abandoned version keeps its number.
        $version = (int) DB::table('portable_release')->where('series_id', $seriesId)->max('version_number') + 1;
        $conceptDoi = $series->concept_doi ?: $this->doi->conceptDoi($config, $series->series_key);
        $versionDoi = $this->doi->versionDoi($conceptDoi, $version);

        if (! is_dir($root) && ! @mkdir($root, 0775, true) && ! is_dir($root)) {
            throw new ReleaseException('Freeze failed.', ['The release storage folder could not be created.']);
        }
        $tmp = $root.'/.tmp-'.Str::random(12);
        $final = $root.'/v'.$version.'-'.Str::lower(Str::random(8));
        mkdir($tmp, 0775);

        try {
            $package = $tmp.'/package.zip';
            $this->copyPackage((string) $export->output_path, $package);
            $inspection = $this->inspector->inspect($package);

            $failures = $this->gate->checkPackage($export, $inspection);
            if ($failures !== []) {
                throw new ReleaseException('This package did not pass the disclosure checks.', $failures);
            }
            if ($latest && $series->scope_type === 'clipboard') {
                $this->assertSameSelection($latest, $inspection['records']);
            }

            $corrections = DB::table('portable_release_correction')
                ->where('series_id', $seriesId)->whereNull('release_id')->orderBy('id')->get();
            $previousIndex = $previous ? $this->readIndex($previous) : null;
            $changes = $this->diff($previousIndex['records'] ?? null, $inspection['records'], $corrections);
            $changelog = $this->renderChangelog($series, $version, $previous, $changes, $notes);
            file_put_contents($tmp.'/CHANGELOG.md', $changelog);

            $disclosure = json_decode((string) $export->disclosure_summary, true);
            $packageSha = hash_file('sha256', $package);
            $frozenAt = now();
            $manifest = [
                'schema' => 'heratio-portable-release/1',
                'series' => ['key' => $series->series_key, 'title' => $series->title, 'concept_doi' => $conceptDoi],
                'version' => $version,
                'version_doi' => $versionDoi,
                'previous_version_doi' => $previous->version_doi ?? null,
                'frozen_at' => $frozenAt->toIso8601String(),
                'package' => ['file' => 'package.zip', 'sha256' => $packageSha, 'bytes' => filesize($package)],
                'changelog_sha256' => hash('sha256', $changelog),
                'record_count' => count($inspection['records']),
                'disclosure' => [
                    'records_in_scope' => $disclosure['records_in_scope'] ?? null,
                    'records_included' => $disclosure['records_included'] ?? null,
                    'withheld' => $disclosure['withheld'] ?? [],
                ],
                'files' => $inspection['files'],
            ];
            $manifestJson = json_encode($manifest, self::JSON);
            file_put_contents($tmp.'/release-manifest.json', $manifestJson);

            // Private to this system: used for the next diff and to re-check access. Never deposited.
            file_put_contents($tmp.'/record-index.json', json_encode([
                'records' => $inspection['records'],
                'asset_record_ids' => $inspection['asset_record_ids'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            // What every public request re-checks, kept apart so it never loads the fingerprints.
            file_put_contents($tmp.'/record-ids.json', json_encode([
                'record_ids' => array_keys($inspection['records']),
                'asset_record_ids' => $inspection['asset_record_ids'],
            ]));

            file_put_contents($tmp.'/SHA256SUMS',
                $packageSha."  package.zip\n"
                .hash('sha256', $manifestJson)."  release-manifest.json\n"
                .hash('sha256', $changelog)."  CHANGELOG.md\n");

            foreach (['package.zip', 'CHANGELOG.md', 'release-manifest.json', 'record-index.json', 'record-ids.json', 'SHA256SUMS'] as $f) {
                chmod($tmp.'/'.$f, 0444);
            }
            if (! rename($tmp, $final)) {
                throw new ReleaseException('Freeze failed.', ['The frozen version could not be moved into place.']);
            }
            $tmp = $final;
            chmod($final, 0555);

            return DB::transaction(function () use ($series, $seriesId, $exportId, $version, $conceptDoi, $versionDoi, $final, $packageSha, $package, $manifestJson, $changelog, $inspection, $changes, $notes, $userId, $frozenAt, $corrections) {
                if (empty($series->concept_doi)) {
                    DB::table('portable_release_series')->where('id', $seriesId)->update(['concept_doi' => $conceptDoi, 'updated_at' => now()]);
                }
                $id = (int) DB::table('portable_release')->insertGetId([
                    'series_id' => $seriesId,
                    'version_number' => $version,
                    'export_id' => $exportId,
                    'status' => self::FROZEN,
                    'frozen_path' => $final,
                    'package_sha256' => $packageSha,
                    'package_bytes' => filesize($final.'/package.zip'),
                    'manifest_sha256' => hash('sha256', $manifestJson),
                    'changelog_sha256' => hash('sha256', $changelog),
                    'file_count' => count($inspection['files']),
                    'record_count' => count($inspection['records']),
                    'version_doi' => $versionDoi,
                    'changelog_json' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'release_notes' => $notes,
                    'frozen_by' => $userId,
                    'frozen_at' => $frozenAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('portable_release_correction')->whereIn('id', $corrections->pluck('id'))->update(['release_id' => $id]);

                return $id;
            });
        } catch (\Throwable $e) {
            $this->removeDir($tmp);
            throw $e;
        }
    }

    /** The archivist's approval. Nothing is sent anywhere until this has happened. */
    public function approve(int $releaseId, int $targetId, int $userId): void
    {
        $release = $this->release($releaseId);
        $series = DB::table('portable_release_series')->where('id', $release->series_id)->first();
        if ($release->status !== self::FROZEN) {
            throw new ReleaseException('Approval refused.', ['Only a frozen version awaiting approval can be approved.']);
        }

        $target = DB::table('portable_release_target')->where('id', $targetId)->where('is_active', 1)->first();
        if (! $target || ($target->repository_id !== null && (int) $target->repository_id !== (int) $series->repository_id)) {
            throw new ReleaseException('Approval refused.', ['That deposit target is not available to this series.']);
        }
        if ($target->target_type !== 'institution' && empty($series->license)) {
            throw new ReleaseException('Approval refused.', ['An external deposit needs a licence on the series.']);
        }

        $this->publicUrl($series); // refuses an address a DOI could never resolve to

        $failures = array_merge($this->verifyFrozen($release), $this->disclosureFailures($release));
        if ($failures !== []) {
            throw new ReleaseException('This version can no longer be approved.', $failures);
        }

        DB::transaction(function () use ($release, $targetId, $userId) {
            $claimed = DB::table('portable_release')->where('id', $release->id)->where('status', self::FROZEN)
                ->update(['status' => self::APPROVED, 'approved_by' => $userId, 'approved_at' => now(), 'last_error' => null, 'updated_at' => now()]);
            if ($claimed !== 1) {
                throw new ReleaseException('Approval refused.', ['This version was approved by someone else just now.']);
            }
            DB::table('portable_release_deposit')->insert([
                'release_id' => $release->id,
                'target_id' => $targetId,
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** @return array{ok:bool, error:?string} */
    public function publish(int $releaseId): array
    {
        $lock = Cache::lock('portable-release-publish:'.$releaseId, 7200);
        if (! $lock->get()) {
            return ['ok' => false, 'error' => 'This version is already being published.'];
        }
        try {
            // A job queued before the version was abandoned must leave no error on it.
            if (DB::table('portable_release')->where('id', $releaseId)->value('status') === self::ABANDONED) {
                return ['ok' => false, 'error' => 'This version was abandoned.'];
            }
            $this->publishLocked($releaseId);
            DB::table('portable_release')->where('id', $releaseId)->update(['last_error' => null]);

            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            $message = $e instanceof ReleaseException && $e->failures ? implode(' ', $e->failures) : $e->getMessage();
            $message = mb_substr($message, 0, 2000);
            DB::table('portable_release')->where('id', $releaseId)->update(['last_error' => $message, 'updated_at' => now()]);

            return ['ok' => false, 'error' => $message];
        } finally {
            $lock->release();
        }
    }

    private function publishLocked(int $releaseId): void
    {
        $release = $this->release($releaseId);
        if ($release->status === self::RELEASED) {
            return;
        }
        $deposit = DB::table('portable_release_deposit')->where('release_id', $releaseId)->first();
        if ($release->status !== self::APPROVED || ! $deposit || empty($deposit->approved_by)) {
            throw new ReleaseException('Publish refused.', ['This version has not been approved by an archivist.']);
        }
        if (DB::table('portable_release')->where('series_id', $release->series_id)->where('status', '!=', self::ABANDONED)
            ->where('version_number', '>', $release->version_number)->exists()) {
            throw new ReleaseException('Publish refused.', ['A later version has replaced this one, so it can no longer be published.']);
        }
        $failures = array_merge($this->verifyFrozen($release), $this->disclosureFailures($release));
        if ($failures !== []) {
            if ($release->doi_state === 'findable') {
                $failures[] = 'The version DOI is already public, so this version cannot be abandoned. Its public page stays up with the package withheld. To move on, freeze a new version from an export without these records.';
            }
            throw new ReleaseException('Publish refused.', $failures);
        }

        $series = DB::table('portable_release_series')->where('id', $release->series_id)->first();
        $repo = $series->repository_id !== null ? (int) $series->repository_id : null;
        $landing = $this->publicUrl($series, $release->version_number);
        $previous = $this->releasedBefore((int) $series->id, (int) $release->version_number);

        // 1. Drafts: proves credentials and metadata while everything is still reversible.
        if ($series->concept_doi_state === 'none') {
            $this->syncDoi($repo, $series->concept_doi, $this->conceptAttributes($series, $release), false);
            DB::table('portable_release_series')->where('id', $series->id)->update(['concept_doi_state' => 'draft', 'updated_at' => now()]);
        }
        if ($release->doi_state === 'none') {
            $this->syncDoi($repo, $release->version_doi, $this->versionAttributes($series, $release, $previous), false);
            DB::table('portable_release')->where('id', $releaseId)->update(['doi_state' => 'draft', 'updated_at' => now()]);
        }

        // 2. The approved deposit.
        if ($deposit->status !== 'deposited') {
            $target = DB::table('portable_release_target')->where('id', $deposit->target_id)->first();
            if (! $target || ! (int) $target->is_active) {
                throw new ReleaseException('Deposit refused.', ['The approved deposit target is no longer active.']);
            }
            try {
                $result = $this->driver($target)->deposit($target, $deposit, [
                'package' => $release->frozen_path.'/package.zip',
                'manifest' => $release->frozen_path.'/release-manifest.json',
                'changelog' => $release->frozen_path.'/CHANGELOG.md',
                'base_name' => 'portable-'.$series->series_key.'-v'.$release->version_number,
            ], [
                'title' => $series->title,
                'creator' => $series->creator_name,
                'description' => $series->description ?: $series->title,
                'version' => 'v'.$release->version_number,
                'doi' => $release->version_doi,
                'concept_doi' => $series->concept_doi,
                'previous_doi' => $previous->version_doi ?? null,
                'license' => $series->license,
                'date' => now()->toDateString(),
                'landing_url' => $landing,
            ], function (string $externalId) use ($deposit) {
                DB::table('portable_release_deposit')->where('id', $deposit->id)->update(['external_id' => $externalId, 'updated_at' => now()]);
                });
            } catch (\Throwable $e) {
                DB::table('portable_release_deposit')->where('id', $deposit->id)->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($e instanceof ReleaseException && $e->failures ? implode(' ', $e->failures) : 'The deposit failed.', 0, 2000),
                    'updated_at' => now(),
                ]);
                throw $e;
            }
            DB::table('portable_release_deposit')->where('id', $deposit->id)->update([
                'status' => 'deposited',
                'external_id' => $result['external_id'],
                'external_url' => $result['external_url'],
                'last_error' => null,
                'deposited_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Permanent: findable DOIs and the links between versions.
        $this->syncDoi($repo, $release->version_doi, $this->versionAttributes($series, $release, $previous), true);
        DB::table('portable_release')->where('id', $releaseId)->update(['doi_state' => 'findable', 'updated_at' => now()]);
        if ($previous) {
            $beforePrevious = $this->releasedBefore((int) $series->id, (int) $previous->version_number);
            $this->syncDoi($repo, $previous->version_doi, $this->versionAttributes($series, $previous, $beforePrevious, $release), true);
        }
        $this->syncDoi($repo, $series->concept_doi, $this->conceptAttributes($series, $release), true);

        DB::transaction(function () use ($series, $releaseId) {
            DB::table('portable_release_series')->where('id', $series->id)->update(['concept_doi_state' => 'findable', 'updated_at' => now()]);
            DB::table('portable_release')->where('id', $releaseId)->update(['status' => self::RELEASED, 'released_at' => now(), 'updated_at' => now()]);
        });
    }

    /** @return string[] what no longer matches the hashes recorded at freeze */
    public function verifyFrozen(object $release): array
    {
        $failures = [];
        foreach (['package.zip' => $release->package_sha256, 'release-manifest.json' => $release->manifest_sha256, 'CHANGELOG.md' => $release->changelog_sha256] as $file => $expected) {
            $path = $release->frozen_path.'/'.$file;
            if (! is_file($path) || ! hash_equals($expected, (string) hash_file('sha256', $path))) {
                $failures[] = 'The frozen '.$file.' is missing or has changed since it was frozen.';
            }
        }

        return $failures;
    }

    /** @return string[] why this frozen version may not be disclosed today */
    public function recheck(object $release): array
    {
        $raw = @file_get_contents($release->frozen_path.'/record-ids.json');
        $ids = $raw === false ? null : json_decode($raw, true);
        if (! is_array($ids['record_ids'] ?? null) || ! is_array($ids['asset_record_ids'] ?? null)) {
            return ['The frozen record index is missing or unreadable.'];
        }

        return $this->gate->checkRecords(array_map('intval', $ids['record_ids']), array_map('intval', $ids['asset_record_ids']));
    }

    /** Everything that stops this version going public, including what its frozen changelog names. */
    private function disclosureFailures(object $release): array
    {
        $failures = $this->recheck($release);
        if (! $this->publicChanges($release)['changelog_available']) {
            $failures[] = 'The frozen changelog names a withdrawn record that is no longer cleared for public disclosure.';
        }

        return $failures;
    }

    /**
     * The change list as it may be shown today. A withdrawn record that is no
     * longer disclosable is counted, never named. The frozen changelog file
     * cannot be rewritten, so it is held back if it names such a record.
     */
    public function publicChanges(object $release): array
    {
        $changes = json_decode((string) $release->changelog_json, true) ?: [];
        $removed = $changes['removed'] ?? [];
        $listable = array_flip($this->gate->disclosableIds(array_map('intval', array_column($removed, 'id'))));
        $changes['removed'] = array_values(array_filter($removed, fn ($r) => isset($listable[(int) $r['id']])));
        $dropped = count($removed) - count($changes['removed']);
        $changes['removed_unlisted'] = (int) ($changes['removed_unlisted'] ?? 0) + $dropped;
        $changes['changelog_available'] = $dropped === 0;

        return $changes;
    }

    /** Publishing stopped after the permanent DOI went public, and the version may not be disclosed now. */
    private function isStranded(object $release): bool
    {
        return $release->status === self::APPROVED && $release->doi_state === 'findable'
            && $this->disclosureFailures($release) !== [];
    }

    /** A clipboard series has no fixed scope, so a new version must keep at least half the last one's records. */
    private function assertSameSelection(object $latest, array $records): void
    {
        $base = $this->readIndex($latest)['records'] ?? null;
        if ($base === null) {
            throw new ReleaseException('Freeze refused.', ['The record index of version '.$latest->version_number.' cannot be read, so this export cannot be shown to cover the same scope.']);
        }
        if (count(array_intersect_key($base, $records)) * 2 < count($base)) {
            throw new ReleaseException('Freeze refused.', ['This export covers a different scope from the series: it keeps fewer than half of the records in version '.$latest->version_number.'. Start a new series for a different selection.']);
        }
    }

    /** The nearest earlier released version: abandoned numbers are skipped in the DOI chain. */
    private function releasedBefore(int $seriesId, int $version): ?object
    {
        return DB::table('portable_release')->where('series_id', $seriesId)->where('status', self::RELEASED)
            ->where('version_number', '<', $version)->orderByDesc('version_number')->first();
    }

    public function release(int $id): object
    {
        $row = DB::table('portable_release')->where('id', $id)->first();
        if (! $row) {
            throw new ReleaseException('Version not found.', ['That version does not exist.']);
        }

        return $row;
    }

    public function publicUrl(object $series, ?int $version = null): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $host = rtrim(trim(strtolower((string) parse_url($base, PHP_URL_HOST)), '[]'), '.');
        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isPrivate = $isIp
            ? ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
                || (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && (ip2long($host) & 0xFFC00000) === 0x64400000) // 100.64.0.0/10
            : ! str_contains($host, '.') || (bool) preg_match('/\.(local|localhost|internal|\d+)$/', $host);
        if ($host === '' || $isPrivate) {
            throw new ReleaseException('Publish refused.', ['APP_URL is not a public address, and a DOI registered against it could never resolve.']);
        }

        return $base.'/portable-release/'.$series->series_key.($version ? '/v'.$version : '');
    }

    /** @return array<string,string> */
    public function targetDrivers(): array
    {
        return ['institution' => InstitutionDepositDriver::class, 'zenodo' => ZenodoDepositDriver::class];
    }

    private function driver(object $target): DepositDriver
    {
        $class = $this->targetDrivers()[$target->target_type] ?? null;
        if (! $class) {
            throw new ReleaseException('Deposit refused.', ['No deposit driver exists for target type '.$target->target_type.'.']);
        }

        return app($class);
    }

    private function syncDoi(?int $repo, string $doi, array $attributes, bool $publish): void
    {
        $result = $this->doi->sync($repo, $doi, $attributes, $publish);
        if (! $result['ok']) {
            throw new ReleaseException('DOI registration failed.', ['DataCite refused '.$doi.': '.mb_substr((string) $result['error'], 0, 500)]);
        }
    }

    private function baseAttributes(object $series, string $url): array
    {
        $attrs = [
            'titles' => [['title' => $series->title]],
            'creators' => [['name' => $series->creator_name]],
            'publisher' => $series->publisher,
            'publicationYear' => (int) date('Y'),
            'types' => ['resourceTypeGeneral' => 'Dataset', 'resourceType' => 'Portable access copy'],
            'url' => $url,
        ];
        if (! empty($series->description)) {
            $attrs['descriptions'] = [['description' => $series->description, 'descriptionType' => 'Abstract']];
        }
        if (! empty($series->license)) {
            $attrs['rightsList'] = [['rights' => $series->license, 'rightsIdentifier' => $series->license]];
        }

        return $attrs;
    }

    private function versionAttributes(object $series, object $release, ?object $previous, ?object $next = null): array
    {
        $attrs = $this->baseAttributes($series, $this->publicUrl($series, (int) $release->version_number));
        $attrs['version'] = 'v'.$release->version_number;
        $attrs['publicationYear'] = (int) ($release->released_at ? date('Y', strtotime((string) $release->released_at)) : date('Y'));
        $related = [$this->relation($series->concept_doi, 'IsVersionOf')];
        if ($previous) {
            $related[] = $this->relation($previous->version_doi, 'IsNewVersionOf');
        }
        if ($next) {
            $related[] = $this->relation($next->version_doi, 'IsPreviousVersionOf');
        }
        $attrs['relatedIdentifiers'] = $related;

        return $attrs;
    }

    private function conceptAttributes(object $series, object $newest): array
    {
        $attrs = $this->baseAttributes($series, $this->publicUrl($series));
        $attrs['version'] = 'v'.$newest->version_number;
        $dois = DB::table('portable_release')->where('series_id', $series->id)
            ->where(fn ($q) => $q->where('status', self::RELEASED)->orWhere('id', $newest->id)->orWhere('doi_state', 'findable'))
            ->orderBy('version_number')->pluck('version_doi');
        $attrs['relatedIdentifiers'] = $dois->map(fn ($d) => $this->relation($d, 'HasVersion'))->all();

        return $attrs;
    }

    private function relation(string $doi, string $type): array
    {
        return ['relatedIdentifier' => $doi, 'relatedIdentifierType' => 'DOI', 'relationType' => $type];
    }

    private function diff(?array $old, array $new, $corrections): array
    {
        $added = $removed = $changed = [];
        if ($old !== null) {
            foreach ($new as $id => $rec) {
                if (! isset($old[$id])) {
                    $added[] = ['id' => $id, 'identifier' => $rec['identifier']];

                    continue;
                }
                $fields = [];
                foreach (array_unique(array_merge(array_keys($old[$id]['fields']), array_keys($rec['fields']))) as $f) {
                    if (($old[$id]['fields'][$f] ?? null) !== ($rec['fields'][$f] ?? null)) {
                        $fields[] = $f;
                    }
                }
                if ($fields !== []) {
                    sort($fields);
                    $changed[] = ['id' => $id, 'identifier' => $rec['identifier'], 'fields' => $fields];
                }
            }
            foreach ($old as $id => $rec) {
                if (! isset($new[$id])) {
                    $removed[] = ['id' => (int) $id, 'identifier' => $rec['identifier'] ?? null];
                }
            }
        }
        $listable = array_flip($this->gate->disclosableIds(array_column($removed, 'id')));
        $listed = array_values(array_filter($removed, fn ($r) => isset($listable[$r['id']])));

        return [
            'first_release' => $old === null,
            'added' => $added,
            'removed' => $listed,
            'removed_unlisted' => count($removed) - count($listed),
            'changed' => $changed,
            'corrections' => $corrections->map(fn ($c) => [
                'type' => $c->correction_type,
                'record_ref' => $c->record_ref,
                'field' => $c->field_name,
                'old_value' => $c->old_value,
                'new_value' => $c->new_value,
                'reason' => $c->reason,
            ])->values()->all(),
        ];
    }

    private function renderChangelog(object $series, int $version, ?object $previous, array $c, ?string $notes): string
    {
        $line = fn ($v) => trim(preg_replace('/\s+/u', ' ', (string) $v));
        $ref = fn ($r) => '#'.$r['id'].($r['identifier'] ? ' ('.$line($r['identifier']).')' : '');
        $labels = \AhgCore\Support\DropdownOptions::resolve('portable_release_correction_type', []);

        $out = '# '.$line($series->title).' - v'.$version."\n\n";
        $out .= $previous ? 'Changes from v'.$previous->version_number." (".$previous->version_doi.").\n\n" : "First release.\n\n";
        if ($notes) {
            $out .= "## Release notes\n\n".trim($notes)."\n\n";
        }
        if (! $c['first_release']) {
            $unlisted = (int) $c['removed_unlisted'];
            $out .= '## Summary'."\n\n".'- Added: '.count($c['added'])."\n- Withdrawn: ".(count($c['removed']) + $unlisted).($unlisted ? ' ('.$unlisted.' not listed)' : '')."\n- Changed: ".count($c['changed'])."\n\n";
            foreach (['Withdrawn records' => $c['removed'], 'Added records' => $c['added']] as $heading => $rows) {
                if ($rows) {
                    $out .= '## '.$heading."\n\n".implode("\n", array_map(fn ($r) => '- '.$ref($r), $rows))."\n\n";
                }
            }
            if ($c['changed']) {
                $out .= "## Changed records\n\n".implode("\n", array_map(fn ($r) => '- '.$ref($r).': '.implode(', ', $r['fields']), $c['changed']))."\n\n";
            }
        }
        if ($c['corrections']) {
            $out .= "## Corrections\n\n";
            foreach ($c['corrections'] as $x) {
                $out .= '- **'.$line($labels[$x['type']] ?? $x['type']).'**'
                    .($x['record_ref'] ? ' - '.$line($x['record_ref']) : '')
                    .($x['field'] ? ' - '.$line($x['field']) : '')
                    .(($x['old_value'] ?? '') !== '' || ($x['new_value'] ?? '') !== '' ? ': was "'.$line($x['old_value']).'", now "'.$line($x['new_value']).'"' : '')
                    .'. '.$line($x['reason'])."\n";
            }
            $out .= "\n";
        }

        return $out;
    }

    private function readIndex(object $release): ?array
    {
        $raw = @file_get_contents($release->frozen_path.'/record-index.json');
        $index = $raw === false ? null : json_decode($raw, true);

        return is_array($index) && is_array($index['records'] ?? null) ? $index : null;
    }

    /** Decides which institution's DataCite account the series uses. */
    private function owningRepository(object $export): ?int
    {
        if ($export->scope_repository_id !== null) {
            return (int) $export->scope_repository_id;
        }
        if ($export->scope_type !== 'fonds' || empty($export->scope_slug)) {
            return null;
        }
        $repo = DB::table('slug')->join('information_object as io', 'io.id', '=', 'slug.object_id')
            ->where('slug.slug', $export->scope_slug)->value('io.repository_id');

        return $repo !== null ? (int) $repo : null;
    }

    private function scopeHash(object $export): string
    {
        // A clipboard scope is its item list, which legitimately changes between
        // versions (that is what a withdrawal is), so only its type is hashed.
        // assertSameSelection() compares the records themselves at freeze.
        return hash('sha256', json_encode([$export->scope_type, $export->scope_slug,
            $export->scope_repository_id === null ? null : (int) $export->scope_repository_id]));
    }

    /** A folder export is zipped, a zip is copied; either way the release owns its own bytes. */
    private function copyPackage(string $source, string $dest): void
    {
        if (is_file($source)) {
            if (! copy($source, $dest)) {
                throw new ReleaseException('Freeze failed.', ['The package could not be copied.']);
            }

            return;
        }

        $base = realpath($source);
        $zip = new \ZipArchive;
        if ($base === false || $zip->open($dest, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) {
            throw new ReleaseException('Freeze failed.', ['The package folder could not be read.']);
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isLink()) {
                $zip->unchangeAll();
                $zip->close();
                throw new ReleaseException('This package did not pass the disclosure checks.', ['The package folder contains a symbolic link: '.substr($f->getPathname(), strlen($base) + 1)]);
            }
            if ($f->isFile()) {
                $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($base) + 1));
            }
        }
        if (! $zip->close()) {
            throw new ReleaseException('Freeze failed.', ['The package could not be zipped.']);
        }
    }

    /** Only ever called on a directory this service created in this request. */
    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        @chmod($dir, 0775);
        foreach (new \FilesystemIterator($dir) as $f) {
            @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
