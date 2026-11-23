<?php

namespace AhgPortableExport\Services;

use AhgCore\Services\DisclosureGate;
use AhgCore\Services\TermProtocolService;
use Illuminate\Support\Facades\Schema;

/**
 * Decides whether a package may become, or remain, a public release.
 *
 * The export worker's gates ran when the package was built, and records change
 * afterwards, so every record is judged again against today's state through the
 * central DisclosureGate. That gate is also wider than the worker's: it covers
 * embargo and security classification too.
 */
class ReleaseGate
{
    /** @return string[] empty when the package passed */
    public function checkPackage(object $export, array $inspection): array
    {
        $failures = $inspection['problems'];

        if (($export->status ?? '') !== 'complete') {
            $failures[] = 'The export has not completed.';
        }

        if (! self::isPublicKind($export)) {
            $failures[] = 'This export is a private or editable package (a researcher copy, or not read-only) and cannot become a public release.';
        }

        // Anonymous viewers never receive originals, and a deposit cannot be recalled.
        $masters = array_filter(array_column($inspection['files'], 'path'), fn ($p) => str_starts_with($p, 'assets/master/'));
        if (! empty($export->include_masters) || $masters !== []) {
            $failures[] = 'The package was built with master files. Build a new export without masters.';
        }

        $summary = json_decode((string) ($export->disclosure_summary ?? ''), true);
        $packaged = $inspection['disclosure'];
        if (! is_array($summary) || ! is_array($packaged)) {
            $failures[] = 'The package has no disclosure summary, so it cannot be shown to have passed the checks. Build a new export.';
        } else {
            if (! empty($summary['included_unpublished']) || ! empty($packaged['included_unpublished'])) {
                $failures[] = 'The package was built with the include-unpublished override.';
            }
            if ((int) ($summary['records_included'] ?? -1) !== count($inspection['records'])
                || (int) ($packaged['records_included'] ?? -1) !== count($inspection['records'])) {
                $failures[] = 'The record count does not match the disclosure summary.';
            }
        }

        if ($inspection['records'] === []) {
            $failures[] = 'The package contains no records.';
        }

        $outside = array_diff($inspection['asset_record_ids'], array_keys($inspection['records']));
        if ($outside !== []) {
            $failures[] = 'The package holds files for records it does not list: '.$this->ids($outside);
        }

        return array_merge($failures, $this->checkRecords(array_keys($inspection['records']), $inspection['asset_record_ids']));
    }

    /**
     * @param  int[]  $recordIds
     * @param  int[]  $assetRecordIds  records whose files are in the package
     * @return string[]
     */
    public function checkRecords(array $recordIds, array $assetRecordIds): array
    {
        // A fresh instance: DisclosureGate memoises, and a queue worker outlives a request.
        $gate = new DisclosureGate;
        $failures = [];

        $blocked = array_diff($recordIds, $gate->filterIds($recordIds));
        if ($blocked !== []) {
            $failures[] = count($blocked).' record(s) are not cleared for public disclosure (unpublished, ICIP/TK, ODRL, embargo or classification): '.$this->ids($blocked);
        }

        $protocol = array_intersect($recordIds, TermProtocolService::restrictedRecordIds());
        if ($protocol !== []) {
            $failures[] = count($protocol).' record(s) carry a restricted community protocol: '.$this->ids($protocol);
        }

        $redacted = array_intersect($assetRecordIds, $gate->redactedIds());
        if ($redacted !== []) {
            $failures[] = count($redacted).' record(s) have PII redactions but their original files or file names are in the package: '.$this->ids($redacted);
        }

        return $failures;
    }

    /**
     * @param  int[]  $ids
     * @return int[] those that may be named publicly today
     */
    public function disclosableIds(array $ids): array
    {
        return array_values(array_diff((new DisclosureGate)->filterIds($ids), TermProtocolService::restrictedRecordIds()));
    }

    /**
     * Whether an anonymous share link may serve this package today. Records
     * change after a build, so each one is judged again. A package that cannot
     * be read is refused. The reason names no record.
     */
    public function shareRefusal(object $export): ?string
    {
        $ids = app(PackageInspector::class)->recordIds((string) ($export->output_path ?? ''));
        if ($ids === null) {
            return 'This package could not be checked, so it cannot be served through a public share link.';
        }
        $gate = new DisclosureGate;
        if (array_diff($ids, $gate->filterIds($ids)) !== [] || array_intersect($ids, TermProtocolService::restrictedRecordIds()) !== []) {
            return 'This package holds records that are not cleared for public access, so it cannot be served through a public share link.';
        }

        return null;
    }

    /** A researcher's offline copy carries a live sync token and their name. */
    public static function isPublicKind(object $export): bool
    {
        return empty($export->sync_token) && empty($export->researcher_user_id) && empty($export->group_source)
            && ($export->mode ?? null) === 'read_only';
    }

    /** The same rule as isPublicKind and the masters check, for export pick lists. */
    public static function whereReleasable($query)
    {
        $query->where('mode', 'read_only')->where(fn ($q) => $q->whereNull('include_masters')->orWhere('include_masters', 0));
        foreach (['sync_token', 'group_source'] as $column) {
            if (Schema::hasColumn('portable_export', $column)) {
                $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));
            }
        }
        if (Schema::hasColumn('portable_export', 'researcher_user_id')) {
            $query->where(fn ($q) => $q->whereNull('researcher_user_id')->orWhere('researcher_user_id', 0));
        }

        return $query;
    }

    private function ids(array $ids): string
    {
        $ids = array_values($ids);

        return implode(', ', array_slice($ids, 0, 20)).(count($ids) > 20 ? ' and '.(count($ids) - 20).' more' : '');
    }
}
