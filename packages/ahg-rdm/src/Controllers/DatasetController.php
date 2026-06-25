<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Controllers;

use AhgRdm\Services\DatasetService;
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
            'dataset'  => $dataset,
            'files'    => $this->service->files($id),
            'statuses' => $statuses,
        ]);
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
