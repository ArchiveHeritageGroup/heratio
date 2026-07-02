<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Controllers;

use AhgRdm\Services\ComplianceReportService;
use AhgRdm\Services\DashboardService;
use AhgRdm\Services\DatasetService;
use AhgRdm\Services\DmpLinkService;
use AhgRdm\Services\PopiaGateService;
use AhgRdm\Services\PopiaScanService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Research Dataset deposit (#1338). Thin controller over DatasetService - which
 * itself only orchestrates IngestService + InformationObjectService.
 */
class DatasetController extends Controller
{
    public function __construct(private DatasetService $service)
    {
    }

    /**
     * #1393 — resolve a dataset the current user is authorised to act on: an
     * admin, the dataset's creator, or the owner/collaborator of its research
     * project. 404 if it doesn't exist, 403 if it isn't theirs. Fail-closed —
     * every dataset write must go through this instead of a bare existence check.
     */
    private function authorizeDataset(int $id): object
    {
        $dataset = $this->service->get($id);
        abort_unless($dataset, 404);

        try {
            if (\Illuminate\Support\Facades\Auth::check()
                && \AhgCore\Services\AclService::canAdmin(\Illuminate\Support\Facades\Auth::id())) {
                return $dataset;
            }
        } catch (\Throwable $e) {
            // fall through to ownership checks
        }

        $userId = (int) (\Illuminate\Support\Facades\Auth::id() ?? 0);
        if ($userId > 0 && (int) ($dataset->created_by ?? 0) === $userId) {
            return $dataset;
        }

        $researcherId = (int) (DB::table('research_researcher')->where('user_id', $userId)->value('id') ?? 0);
        if ($researcherId > 0 && ! empty($dataset->project_id)) {
            $project = DB::table('research_project')->where('id', $dataset->project_id)->first();
            if ($project && (int) ($project->owner_id ?? 0) === $researcherId) {
                return $dataset;
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('research_project_collaborator')
                && DB::table('research_project_collaborator')
                    ->where('project_id', $dataset->project_id)
                    ->where('researcher_id', $researcherId)->exists()) {
                return $dataset;
            }
        }

        abort(403, 'You do not have access to this dataset.');
    }

    public function index()
    {
        // #1393 — a researcher sees only their own datasets; admins see all.
        $userId = (int) (\Illuminate\Support\Facades\Auth::id() ?? 0);
        $isAdmin = false;
        try {
            $isAdmin = \Illuminate\Support\Facades\Auth::check()
                && \AhgCore\Services\AclService::canAdmin($userId);
        } catch (\Throwable $e) {
            $isAdmin = false;
        }
        $researcherId = (int) (DB::table('research_researcher')->where('user_id', $userId)->value('id') ?? 0);

        return view('ahg-rdm::datasets.index', [
            'datasets' => $isAdmin ? $this->service->list() : $this->service->list($userId, $researcherId),
        ]);
    }

    /** Full RDM dashboard (#1337 Feature 3): KPI roll-up + charts + gate backlog.
     *  Filters (#1345): from / to (deposit date) + institution. */
    public function dashboard(Request $request)
    {
        $filters = array_filter($request->only(['from', 'to', 'institution']), fn ($v) => $v !== null && $v !== '');

        return view('ahg-rdm::datasets.dashboard', [
            'd'            => app(DashboardService::class)->overview($filters),
            'institutions' => app(ComplianceReportService::class)->institutions(),
            'filters'      => $filters,
        ]);
    }

    public function compliance(Request $request)
    {
        $filters = array_filter($request->only(['institution', 'verdict', 'disposition']));
        $svc = app(ComplianceReportService::class);

        return view('ahg-rdm::datasets.compliance', [
            'rows'         => $svc->rows($filters),
            'institutions' => $svc->institutions(),
            'summary'      => $svc->summary($filters),
            'filters'      => $filters,
        ]);
    }

    public function create()
    {
        return view('ahg-rdm::datasets.create', [
            'projects' => DB::table('research_project')->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:500',
            'description' => 'nullable|string',
            'project_id'  => 'nullable|integer|exists:research_project,id',
        ]);

        $id = $this->service->create(
            $data['title'],
            $data['description'] ?? null,
            $data['project_id'] ?? null,
            auth()->id()
        );

        return redirect()->route('rdm.datasets.show', $id)
            ->with('success', 'Dataset created. Deposit files below.');
    }

    public function show(int $id)
    {
        $dataset = $this->authorizeDataset($id); // #1393 ownership gate

        $statuses = DB::table('ahg_dropdown')
            ->where('taxonomy', 'dataset_status')
            ->orderBy('sort_order')
            ->get(['code', 'label', 'color']);

        return view('ahg-rdm::datasets.show', [
            'dataset'      => $dataset,
            'files'        => $this->service->files($id),
            'statuses'     => $statuses,
            'findings'     => DB::table('rdm_scan_finding')->where('dataset_id', $id)->orderByRaw("FIELD(category,'special_category','personal'), method")->get(),
            'gate'         => app(PopiaGateService::class)->gateStatus($id),
            'dispositions' => DB::table('ahg_dropdown')->where('taxonomy', 'rdm_disposition')->orderBy('sort_order')->get(['code', 'label', 'color']),
            'dmp'          => app(DmpLinkService::class)->context($dataset),
        ]);
    }

    /**
     * Link a Data Management Plan to the dataset (#1337 Feature 1). A non-empty
     * 'new_title' creates a fresh maDMP in the research portal and links it;
     * otherwise an existing 'dmp_id' from the dataset's project is linked.
     */
    public function linkDmp(Request $request, int $id)
    {
        $this->authorizeDataset($id); // #1393 ownership gate
        $svc = app(DmpLinkService::class);

        if (trim((string) $request->input('new_title')) !== '') {
            $request->validate([
                'new_title' => 'required|string|max:255',
                'funder'    => 'nullable|string|max:255',
            ]);
            $dmpId = $svc->createAndLink($id, [
                'title'  => $request->input('new_title'),
                'funder' => $request->input('funder'),
            ], (int) auth()->id());

            return redirect()->route('rdm.datasets.show', $id)->with(
                $dmpId ? 'success' : 'error',
                $dmpId
                    ? 'Data Management Plan created and linked. Complete its sections in the research portal.'
                    : 'Could not create a DMP - link this dataset to a research project first.'
            );
        }

        $request->validate(['dmp_id' => 'required|integer']);
        $ok = $svc->link($id, (int) $request->input('dmp_id'), (int) auth()->id());

        return redirect()->route('rdm.datasets.show', $id)->with(
            $ok ? 'success' : 'error',
            $ok ? 'Data Management Plan linked.' : "Could not link that plan - it must belong to this dataset's project."
        );
    }

    /** Detach the DMP from the dataset (the plan is left intact). */
    public function unlinkDmp(int $id)
    {
        $this->authorizeDataset($id); // #1393 ownership gate
        app(DmpLinkService::class)->unlink($id);

        return redirect()->route('rdm.datasets.show', $id)->with('success', 'Data Management Plan unlinked.');
    }

    public function resolveFinding(Request $request, int $id, int $fid)
    {
        $this->authorizeDataset($id); // #1393 ownership gate
        $request->validate([
            'decision' => 'required|in:confirm,dismiss',
            'note'     => 'nullable|string|max:500',
        ]);

        try {
            app(PopiaGateService::class)->resolveFinding($fid, $request->input('decision'), $request->input('note'), (int) auth()->id());
        } catch (\Throwable $e) {
            return redirect()->route('rdm.datasets.show', $id)->with('error', $e->getMessage());
        }

        return redirect()->route('rdm.datasets.show', $id)->with('success', 'Finding '.($request->input('decision') === 'dismiss' ? 'dismissed' : 'confirmed').'.');
    }

    public function setDisposition(Request $request, int $id)
    {
        $this->authorizeDataset($id); // #1393 ownership gate
        $request->validate([
            'disposition'   => 'required|in:restrict,embargo,de-identify,release',
            'embargo_until' => 'nullable|date',
        ]);

        try {
            $r = app(PopiaGateService::class)->setDisposition($id, $request->input('disposition'), (int) auth()->id(), $request->input('embargo_until'));
        } catch (\Throwable $e) {
            return redirect()->route('rdm.datasets.show', $id)->with('error', $e->getMessage());
        }

        $msg = "Disposition set to {$r['disposition']} (status {$r['status']}).";
        if (! empty($r['doi'])) {
            $msg .= " DOI: {$r['doi']}.";
        }
        if (! empty($r['embargo_until'])) {
            $msg .= " Embargo until {$r['embargo_until']}.";
        }
        if (! empty($r['policies'])) {
            $msg .= " {$r['policies']} access policies applied.";
        }

        return redirect()->route('rdm.datasets.show', $id)->with('success', $msg);
    }

    /** Public citable landing page (metadata + DOI citation + access status; no gated binaries). */
    public function landing(int $id)
    {
        $dataset = $this->authorizeDataset($id); // #1393 ownership gate

        $year = $dataset->created_at ? substr((string) $dataset->created_at, 0, 4) : date('Y');

        return view('ahg-rdm::datasets.landing', [
            'dataset'   => $dataset,
            'year'      => $year,
            'doiUrl'    => $dataset->doi ? 'https://doi.org/'.$dataset->doi : null,
            'fileCount' => $this->service->files($id)->count(),
            'dmp'       => app(DmpLinkService::class)->context($dataset),
        ]);
    }

    public function scan(int $id)
    {
        $this->authorizeDataset($id); // #1393 ownership gate

        // Queue it: the deterministic pass is instant but NER hits the gateway
        // and can exceed request limits, so run off-thread. Mark 'scanning' now
        // for immediate UI feedback; the worker sets the verdict when done.
        DB::table('rdm_dataset')->where('id', $id)->update(['status' => 'scanning', 'updated_at' => now()]);
        \AhgRdm\Jobs\ScanDatasetJob::dispatch($id);

        return redirect()->route('rdm.datasets.show', $id)->with(
            'success',
            'POPIA scan queued — deterministic PII first, then AI-suggested names. Refresh in a moment for the findings.'
        );
    }

    public function deposit(Request $request, int $id)
    {
        $this->authorizeDataset($id); // #1393 ownership gate

        $request->validate([
            'files'   => 'required|array|min:1',
            'files.*' => 'file|max:262144', // 256 MB per file (matches platform .user.ini)
        ]);

        try {
            $r = $this->service->deposit($id, $request->file('files'), (int) auth()->id());
        } catch (\Throwable $e) {
            return redirect()->route('rdm.datasets.show', $id)
                ->with('error', 'Deposit failed: '.$e->getMessage());
        }

        return redirect()->route('rdm.datasets.show', $id)
            ->with('success', "Deposited {$r['stored']} file(s)".($r['skipped'] ? ", skipped {$r['skipped']}" : '').'.');
    }
}
