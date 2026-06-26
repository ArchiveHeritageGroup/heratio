<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Controllers;

use AhgRdm\Services\DatasetService;
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
        ]);
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
