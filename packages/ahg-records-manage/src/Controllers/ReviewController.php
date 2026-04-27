<?php

/**
 * ReviewController — Phase 2.4 review queue + review completion.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\ReviewScheduleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function __construct(protected ReviewScheduleService $reviews)
    {
    }

    /**
     * GET /admin/records/reviews
     * Queue browse: defaults to pending + overdue + due-soon at the top.
     */
    public function index(Request $request)
    {
        $filters = [
            'status'      => $request->query('status'),
            'due_before'  => $request->query('due_before'),
            'due_after'   => $request->query('due_after'),
            'q'           => $request->query('q'),
            'limit'       => (int) $request->query('limit', 100),
            'offset'      => (int) $request->query('offset', 0),
        ];

        $page    = $this->reviews->listQueue($filters);
        $counts  = $this->reviews->counts();
        $decisions = DB::table('ahg_dropdown')
            ->where('taxonomy', 'rm_review_decision')->where('is_active', 1)
            ->orderBy('sort_order')->get();

        return view('ahg-records::reviews.index', [
            'rows'      => $page['rows'],
            'total'     => $page['total'],
            'filters'   => $filters,
            'counts'    => $counts,
            'decisions' => $decisions,
        ]);
    }

    /**
     * GET /admin/records/reviews/{id}
     * Detail page with the complete-review form.
     */
    public function show(int $id)
    {
        $review = $this->reviews->get($id);
        if (! $review) {
            abort(404, 'Review not found');
        }

        $decisions = DB::table('ahg_dropdown')
            ->where('taxonomy', 'rm_review_decision')->where('is_active', 1)
            ->orderBy('sort_order')->get();

        return view('ahg-records::reviews.show', [
            'review'    => $review,
            'decisions' => $decisions,
        ]);
    }

    /**
     * POST /admin/records/reviews
     * Schedule a new review on a record.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'information_object_id' => 'required|integer',
            'disposal_class_id'     => 'nullable|integer',
            'review_type'           => 'nullable|string|max:30',
            'review_due_date'       => 'required|date',
            'assigned_to'           => 'nullable|integer',
        ]);

        $id = $this->reviews->schedule($data, auth()->id() ?? 0);

        return redirect()
            ->route('records.reviews.show', $id)
            ->with('success', 'Review scheduled.');
    }

    /**
     * POST /admin/records/reviews/{id}/complete
     */
    public function complete(Request $request, int $id)
    {
        $data = $request->validate([
            'decision'             => 'required|string|max:30',
            'decision_notes'       => 'nullable|string',
            'next_review_due_date' => 'nullable|date',
        ]);

        $ok = $this->reviews->complete($id, $data, auth()->id() ?? 0);

        return redirect()
            ->route('records.reviews.show', $id)
            ->with($ok ? 'success' : 'error', $ok ? 'Review marked complete.' : 'Could not complete this review.');
    }

    /**
     * POST /admin/records/reviews/{id}/assign
     */
    public function assign(Request $request, int $id)
    {
        $data = $request->validate(['assigned_to' => 'required|integer']);
        $this->reviews->assign($id, $data['assigned_to']);
        return redirect()->route('records.reviews.show', $id)->with('success', 'Reviewer assigned.');
    }
}
