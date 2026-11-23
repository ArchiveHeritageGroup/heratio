<?php

/**
 * PortableReleaseController - staff workflow and public citation pages for
 * versioned Portable Export releases (#1586).
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgPortableExport\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Support\DropdownOptions;
use AhgPortableExport\Jobs\FreezeReleaseJob;
use AhgPortableExport\Jobs\PublishReleaseJob;
use AhgPortableExport\Services\Deposit\ZenodoDepositDriver;
use AhgPortableExport\Services\PortableReleaseService;
use AhgPortableExport\Services\ReleaseException;
use AhgPortableExport\Services\ReleaseGate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PortableReleaseController extends Controller
{
    public function __construct(private PortableReleaseService $releases) {}

    public function index()
    {
        $this->authorizeStaff();

        return view('ahg-portable-export::releases.index', [
            'series' => DB::table('portable_release_series as s')
                ->leftJoin('portable_release as r', 'r.series_id', '=', 's.id')
                ->groupBy('s.id', 's.title', 's.concept_doi', 's.scope_type', 's.created_at')
                ->orderByDesc('s.created_at')
                ->select('s.id', 's.title', 's.concept_doi', 's.scope_type', 's.created_at', DB::raw('COUNT(r.id) as versions'), DB::raw('MAX(r.version_number) as newest'))
                ->get(),
            'exports' => $this->completeExports(),
            'targets' => DB::table('portable_release_target')->orderBy('name')
                ->get(['id', 'name', 'target_type', 'base_url', 'community', 'repository_id', 'is_active']),
            'targetTypes' => $this->targetTypes(),
            'defaultPublisher' => (string) (app(\AhgDoiManage\Services\DoiService::class)->configFor(null)->default_publisher ?? ''),
        ]);
    }

    public function storeSeries(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'export_id' => ['required', 'integer', Rule::exists('portable_export', 'id')->where('status', 'complete')->where(fn ($q) => ReleaseGate::whereReleasable($q))],
            'title' => 'required|string|max:500',
            'description' => 'nullable|string|max:10000',
            'creator_name' => 'required|string|max:500',
            'publisher' => 'required|string|max:500',
            'license' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9.+-]*$/'],
        ]);
        $id = $this->releases->createSeries((int) $data['export_id'], $data, (int) auth()->id());

        return redirect()->route('portable-export.releases.series', $id)->with('success', __('Release series created.'));
    }

    public function series(int $id)
    {
        $this->authorizeStaff();
        $series = DB::table('portable_release_series')->where('id', $id)->first();
        abort_unless($series, 404);

        return view('ahg-portable-export::releases.series', [
            'series' => $series,
            'versions' => DB::table('portable_release as r')
                ->leftJoin('portable_release_deposit as d', 'd.release_id', '=', 'r.id')
                ->leftJoin('portable_release_target as t', 't.id', '=', 'd.target_id')
                ->leftJoin('portable_release_abandonment as ab', 'ab.release_id', '=', 'r.id')
                ->where('r.series_id', $id)->orderByDesc('r.version_number')
                ->select('r.*', 'd.status as deposit_status', 'd.external_url', 'd.last_error as deposit_error', 't.name as target_name', 'ab.reason as abandon_reason', 'ab.abandoned_at')
                ->get(),
            'freezes' => DB::table('portable_release_freeze_request')->where('series_id', $id)->orderByDesc('id')->limit(5)->get(),
            'freezeLabels' => DropdownOptions::resolve('portable_release_freeze_status', []),
            'pending' => DB::table('portable_release_correction')->where('series_id', $id)->whereNull('release_id')->orderBy('id')->get(),
            'exports' => $this->completeExports(),
            'targets' => DB::table('portable_release_target')->where('is_active', 1)
                ->where(fn ($q) => $q->whereNull('repository_id')->orWhere('repository_id', $series->repository_id))
                ->orderBy('name')->get(['id', 'name', 'target_type']),
            'statusLabels' => DropdownOptions::resolve('portable_release_status', []),
            'depositLabels' => DropdownOptions::resolve('portable_release_deposit_status', []),
            'correctionTypes' => DropdownOptions::resolve('portable_release_correction_type', []),
        ]);
    }

    public function freeze(Request $request, int $id)
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'export_id' => ['required', 'integer', Rule::exists('portable_export', 'id')],
            'release_notes' => 'nullable|string|max:10000',
        ]);

        return $this->attempt($id, function () use ($id, $data) {
            $requestId = $this->releases->requestFreeze($id, (int) $data['export_id'], (int) auth()->id(), $data['release_notes'] ?? null);
            try {
                FreezeReleaseJob::dispatch($requestId);
            } catch (\Throwable $e) {
                report($e);
                $this->releases->failFreezeRequest($requestId, 'The freeze could not be queued.');
                throw new ReleaseException('Freeze failed.', ['The freeze could not be queued. Try again.']);
            }
        }, __('Freeze started. Check this page for the result; the version stays private until approved.'));
    }

    public function abandon(Request $request, int $id)
    {
        $this->authorizeStaff();
        $release = DB::table('portable_release')->where('id', $id)->first();
        abort_unless($release, 404);
        $data = $request->validate([
            'reason' => 'required|string|max:2000',
            'confirm_abandon' => 'accepted',
        ]);

        return $this->attempt((int) $release->series_id, fn () => $this->releases->abandon($id, $data['reason'], (int) auth()->id()),
            __('Version abandoned. Its number will not be reused and its corrections are pending again.'));
    }

    public function storeCorrection(Request $request, int $id)
    {
        $this->authorizeStaff();
        abort_unless(DB::table('portable_release_series')->where('id', $id)->exists(), 404);
        $data = $request->validate([
            'correction_type' => ['required', Rule::in(array_keys(DropdownOptions::resolve('portable_release_correction_type', [])))],
            'record_ref' => 'nullable|string|max:255',
            'field_name' => 'nullable|string|max:255',
            'old_value' => 'nullable|string|max:5000',
            'new_value' => 'nullable|string|max:5000',
            'reason' => 'required|string|max:5000',
        ]);
        $this->releases->addCorrection($id, $data, (int) auth()->id());

        return back()->with('success', __('Correction logged for the next version.'));
    }

    public function approve(Request $request, int $id)
    {
        $this->authorizeStaff();
        $release = DB::table('portable_release')->where('id', $id)->first();
        abort_unless($release, 404);
        $data = $request->validate([
            'target_id' => ['required', 'integer', Rule::exists('portable_release_target', 'id')],
            'confirm' => 'accepted',
        ]);

        return $this->attempt((int) $release->series_id, function () use ($id, $data) {
            $this->releases->approve($id, (int) $data['target_id'], (int) auth()->id());
            PublishReleaseJob::dispatch($id);
        }, __('Approved. Publishing has started; check this page for the result.'));
    }

    public function retry(int $id)
    {
        $this->authorizeStaff();
        $release = DB::table('portable_release')->where('id', $id)->first();
        abort_unless($release, 404);
        if ($release->status !== PortableReleaseService::APPROVED) {
            return back()->with('error', __('Only an approved version that has not finished publishing can be retried.'));
        }
        PublishReleaseJob::dispatch($id);

        return back()->with('success', __('Publishing restarted.'));
    }

    public function storeTarget(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'target_type' => ['required', Rule::in(array_keys($this->targetTypes()))],
            'base_url' => ['nullable', 'required_if:target_type,zenodo', 'string', 'max:255', function ($attr, $value, $fail) use ($request) {
                if ($request->input('target_type') === 'zenodo' && ! ZenodoDepositDriver::isAllowedUrl($value)) {
                    $fail(__('Use https://zenodo.org or https://sandbox.zenodo.org.'));
                }
            }],
            'api_token' => 'nullable|required_if:target_type,zenodo|string|max:500',
            'community' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_-]+$/'],
            'repository_id' => ['nullable', 'integer', Rule::exists('repository', 'id')],
        ]);
        $isZenodo = $data['target_type'] === 'zenodo';
        DB::table('portable_release_target')->insert([
            'name' => $data['name'],
            'target_type' => $data['target_type'],
            'base_url' => $isZenodo ? rtrim($data['base_url'], '/') : null,
            'api_token' => $isZenodo ? Crypt::encryptString($data['api_token']) : null,
            'community' => $isZenodo ? ($data['community'] ?? null) : null,
            'repository_id' => $data['repository_id'] ?? null,
            'is_active' => 1,
            'created_by' => (int) auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', __('Deposit target added.'));
    }

    public function toggleTarget(int $id)
    {
        $this->authorizeStaff();
        $target = DB::table('portable_release_target')->where('id', $id)->first();
        abort_unless($target, 404);
        DB::table('portable_release_target')->where('id', $id)->update(['is_active' => (int) ! $target->is_active, 'updated_at' => now()]);

        return back()->with('success', __('Deposit target updated.'));
    }

    /** The concept DOI's landing address: always the newest released version. */
    public function concept(string $key)
    {
        $this->abortIfDisabled();
        $series = $this->publicSeries($key);
        $newest = DB::table('portable_release')->where('series_id', $series->id)
            ->where('status', PortableReleaseService::RELEASED)->max('version_number');
        abort_unless($newest, 404);

        return redirect()->route('portable-release.landing', ['key' => $key, 'version' => $newest]);
    }

    public function landing(string $key, int $version)
    {
        $this->abortIfDisabled();
        $series = $this->publicSeries($key);
        $release = $this->publicRelease($series, $version);
        $withheld = $this->releases->recheck($release) !== [];
        $all = DB::table('portable_release')->where('series_id', $series->id)
            ->where('status', PortableReleaseService::RELEASED)->orderByDesc('version_number')
            ->get(['version_number', 'version_doi', 'released_at']);

        return view('ahg-portable-export::releases.landing', [
            'series' => $series,
            'release' => $release,
            'withheld' => $withheld,
            // The change list names records, so it is held back with the package.
            'changes' => $withheld ? null : json_decode((string) $release->changelog_json, true),
            'versions' => $all,
            'newest' => (int) $all->first()->version_number,
            'correctionTypes' => DropdownOptions::resolve('portable_release_correction_type', []),
        ]);
    }

    public function file(string $key, int $version, string $file)
    {
        $this->abortIfDisabled();
        $series = $this->publicSeries($key);
        $release = $this->publicRelease($series, $version);
        abort_if($this->releases->recheck($release) !== [], 403, 'This version is withheld pending review.');
        [$name, $as, $type] = match ($file) {
            'package' => ['package.zip', 'portable-'.$key.'-v'.$version.'.zip', 'application/zip'],
            'manifest' => ['release-manifest.json', 'release-manifest-v'.$version.'.json', 'application/json'],
            'changelog' => ['CHANGELOG.md', 'CHANGELOG-v'.$version.'.md', 'text/markdown; charset=UTF-8'],
        };

        // A size check only: hashing a multi-gigabyte package on every public
        // request would be a cheap way to load the server. Full hashes run at
        // approval and publish, and the published SHA-256 lets a reader verify.
        abort_unless(is_file($release->frozen_path.'/'.$name), 503, 'This version failed its integrity check.');
        abort_if($file === 'package' && filesize($release->frozen_path.'/package.zip') !== (int) $release->package_bytes, 503, 'This version failed its integrity check.');

        return response()->download($release->frozen_path.'/'.$name, $as, ['Content-Type' => $type, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function publicSeries(string $key): object
    {
        $series = DB::table('portable_release_series')->where('series_key', $key)->first();
        abort_unless($series, 404);

        return $series;
    }

    private function publicRelease(object $series, int $version): object
    {
        $release = DB::table('portable_release')->where('series_id', $series->id)
            ->where('version_number', $version)->where('status', PortableReleaseService::RELEASED)->first();
        abort_unless($release, 404);

        return $release;
    }

    private function attempt(int $seriesId, callable $action, string $success)
    {
        try {
            $action();
        } catch (ReleaseException $e) {
            return redirect()->route('portable-export.releases.series', $seriesId)
                ->with('error', $e->getMessage())->with('release_failures', $e->failures);
        }

        return redirect()->route('portable-export.releases.series', $seriesId)->with('success', $success);
    }

    private function completeExports()
    {
        return ReleaseGate::whereReleasable(DB::table('portable_export')->where('status', 'complete'))->orderByDesc('id')->limit(50)
            ->get(['id', 'title', 'scope_type', 'total_descriptions', 'completed_at']);
    }

    /** Dropdown values that also have a working driver. */
    private function targetTypes(): array
    {
        return array_intersect_key(DropdownOptions::resolve('portable_release_target_type', []), $this->releases->targetDrivers());
    }

    /**
     * Administrators only. The route's 'admin' gate also admits editors, including
     * repository-scoped ones, and this workflow registers permanent DOIs, holds
     * repository credentials and sends packages off site.
     */
    private function authorizeStaff(): void
    {
        $this->abortIfDisabled();
        abort_unless(AclService::isAdministrator((object) ['id' => (int) auth()->id()]), 403);
    }

    private function abortIfDisabled(): void
    {
        $v = DB::table('ahg_settings')->where('setting_key', 'portable_export_enabled')->value('setting_value');
        abort_if($v !== null && $v !== '' && ! in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true), 404);
    }
}
