<?php

namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Services\ExtendedRightsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgRightsPlugin/
 */
class ExtendedRightsController extends Controller
{
    protected ExtendedRightsService $service;

    public function __construct()
    {
        $this->service = new ExtendedRightsService();
    }

    /**
     * Show rights for this IO (both standard rights and extended).
     */
    public function add(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Standard rights via relation table
        $rights = $this->service->getRightsForObject($io->id, $culture);

        // Extended rights
        $extendedRights = $this->service->getExtendedRights($io->id);

        // Current primary extended right
        $currentRights = $extendedRights->firstWhere('is_primary', 1);

        // TK labels for current primary
        $currentTkLabels = [];
        if ($currentRights) {
            $currentTkLabels = $this->service->getTkLabelsForRights($currentRights->id)
                ->pluck('id')
                ->toArray();
        }

        // Form data
        $rightsStatements = $this->service->getRightsStatements();
        $ccLicenses = $this->service->getCreativeCommonsLicenses();
        $tkLabels = $this->service->getTkLabels();
        $donors = $this->service->getDonors();

        // Active embargo
        $embargo = $this->service->getActiveEmbargo($io->id);

        return view('ahg-io-manage::rights.extended', [
            'io'              => $io,
            'rights'          => $rights,
            'extendedRights'  => $extendedRights,
            'currentRights'   => $currentRights ? (object) [
                'rights_statement' => (object) ['rights_statement_id' => $currentRights->rights_statement_id ?? null],
                'cc_license'       => (object) ['creative_commons_license_id' => $currentRights->creative_commons_license_id ?? null],
                'rights_holder'    => (object) ['donor_id' => $currentRights->rights_holder ?? null],
                'tk_labels'        => $currentTkLabels,
            ] : null,
            'rightsStatements' => $rightsStatements,
            'ccLicenses'       => $ccLicenses,
            'tkLabels'         => $tkLabels,
            'donors'           => $donors,
            'embargo'          => $embargo,
        ]);
    }

    /**
     * Store (create or update) extended rights for this IO.
     */
    public function store(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'rights_statement_id' => 'nullable|integer',
            'cc_license_id'       => 'nullable|integer',
            'rights_holder_id'    => 'nullable|integer',
            'tk_label_ids'        => 'nullable|array',
            'tk_label_ids.*'      => 'integer',
            'rights_note'         => 'nullable|string|max:10000',
            'usage_conditions'    => 'nullable|string|max:10000',
            'copyright_notice'    => 'nullable|string|max:10000',
            'rights_date'         => 'nullable|date',
            'expiry_date'         => 'nullable|date',
            'rights_holder'       => 'nullable|string|max:255',
            'rights_holder_uri'   => 'nullable|string|max:255',
        ]);

        $data = [
            'rights_statement_id'         => $request->input('rights_statement_id') ?: null,
            'creative_commons_license_id' => $request->input('cc_license_id') ?: null,
            'rights_date'                 => $request->input('rights_date'),
            'expiry_date'                 => $request->input('expiry_date'),
            'rights_holder'               => $request->input('rights_holder'),
            'rights_holder_uri'           => $request->input('rights_holder_uri'),
            'is_primary'                  => 1,
            'rights_note'                 => $request->input('rights_note'),
            'usage_conditions'            => $request->input('usage_conditions'),
            'copyright_notice'            => $request->input('copyright_notice'),
            'tk_label_ids'                => $request->input('tk_label_ids', []),
        ];

        // Check if a primary extended right already exists for this object
        $existing = DB::table('extended_rights')
            ->where('object_id', $io->id)
            ->where('is_primary', 1)
            ->first();

        $userId = auth()->id();

        if ($existing) {
            $this->service->updateExtendedRight($existing->id, $data, $userId);
            $message = 'Extended rights updated successfully.';
        } else {
            $this->service->saveExtendedRight($io->id, $data, $userId);
            $message = 'Extended rights created successfully.';
        }

        return redirect()
            ->route('io.rights.extended', $slug)
            ->with('notice', $message);
    }

    /**
     * Show embargo status + form to create/lift.
     */
    public function embargo(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Active embargo
        $activeEmbargo = $this->service->getActiveEmbargo($io->id);

        // All embargoes (history)
        $embargoes = $this->service->getAllEmbargoes($io->id);

        // Descendant count for propagation option
        $descendantCount = $this->service->getDescendantCount($io->id);

        return view('ahg-io-manage::rights.embargo', [
            'io'              => $io,
            'activeEmbargo'   => $activeEmbargo,
            'embargoes'       => $embargoes,
            'descendantCount' => $descendantCount,
        ]);
    }

    /**
     * Create a new embargo.
     */
    public function storeEmbargo(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $request->validate([
            'embargo_type' => 'required|string|max:50',
            'start_date'   => 'required|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
            'reason'       => 'nullable|string|max:5000',
            'is_perpetual' => 'nullable|boolean',
            'notify_on_expiry'  => 'nullable|boolean',
            'notify_days_before'=> 'nullable|integer|min:1|max:365',
        ]);

        $data = [
            'object_id'         => $io->id,
            'embargo_type'      => $request->input('embargo_type'),
            'start_date'        => $request->input('start_date'),
            'end_date'          => $request->input('end_date'),
            'reason'            => $request->input('reason'),
            'is_perpetual'      => $request->boolean('is_perpetual') ? 1 : 0,
            'created_by'        => auth()->id(),
            'notify_on_expiry'  => $request->boolean('notify_on_expiry') ? 1 : 0,
            'notify_days_before'=> $request->input('notify_days_before', 30),
        ];

        $applyToChildren = $request->boolean('apply_to_children');

        if ($applyToChildren) {
            $results = $this->service->createEmbargoWithPropagation($data, true);
            $message = "Embargo created for {$results['created']} record(s).";
            if ($results['failed'] > 0) {
                $message .= " {$results['failed']} record(s) failed.";
            }
        } else {
            $this->service->createEmbargo($data);
            $message = 'Embargo created successfully.';
        }

        return redirect()
            ->route('io.rights.embargo', $slug)
            ->with('notice', $message);
    }

    /**
     * Lift an embargo.
     */
    public function liftEmbargo(Request $request, int $id)
    {
        $request->validate([
            'lift_reason' => 'nullable|string|max:5000',
        ]);

        $embargo = DB::table('embargo')->where('id', $id)->first();
        if (!$embargo) {
            abort(404);
        }

        $userId = auth()->id() ?? 0;
        $reason = $request->input('lift_reason', '');

        $this->service->liftEmbargo($id, $userId, $reason);

        // Resolve the slug for redirect
        $slug = DB::table('slug')
            ->where('object_id', $embargo->object_id)
            ->value('slug');

        return redirect()
            ->route('io.rights.embargo', $slug ?? '')
            ->with('notice', 'Embargo lifted successfully.');
    }

    /**
     * Export rights as JSON-LD.
     */
    public function exportJsonLd(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $jsonLd = $this->service->exportJsonLd($io->id);

        return response()->json($jsonLd, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    /**
     * Resolve IO from slug.
     */
    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
