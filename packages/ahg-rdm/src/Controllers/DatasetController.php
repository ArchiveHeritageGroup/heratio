<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Controllers;

use AhgRdm\Services\ComplianceReportService;
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

    public function index()
    {
        return view('ahg-rdm::datasets.index', [
            'datasets' => $this->service->list(),
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
        $dataset = $this->service->get($id);
        abort_unless($dataset, 404);

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
        abort_unless($this->service->get($id), 404);
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
        abort_unless($this->service->get($id), 404);
        app(DmpLinkService::class)->unlink($id);

        return redirect()->route('rdm.datasets.show', $id)->with('success', 'Data Management Plan unlinked.');
    }

    public function resolveFinding(Request $request, int $id, int $fid)
    {
        abort_unless($this->service->get($id), 404);
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
        abort_unless($this->service->get($id), 404);
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
        $dataset = $this->service->get($id);
        abort_unless($dataset, 404);

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
        abort_unless($this->service->get($id), 404);

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
        abort_unless($this->service->get($id), 404);

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
