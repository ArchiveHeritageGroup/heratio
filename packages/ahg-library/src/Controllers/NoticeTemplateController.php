<?php

/**
 * NoticeTemplateController - admin CRUD for overdue / hold notice templates (#1093).
 *
 * Surfaces library_notice_template for librarians to view + edit the subject /
 * body / trigger / active state of each notice tier, with a live token preview.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryOverdueNoticeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NoticeTemplateController extends Controller
{
    /** Tokens advertised in the editor help panel. */
    public const TOKENS = [
        'patron_name', 'title', 'barcode', 'due_date', 'days_overdue',
        'currency', 'fine_per_day', 'fine_amount', 'library_name', 'expiry_date',
    ];

    /**
     * GET /library-manage/notice-templates
     */
    public function index()
    {
        $templates = Schema::hasTable('library_notice_template')
            ? DB::table('library_notice_template')
                ->orderBy('notice_type')
                ->orderBy('channel')
                ->get()
            : collect();

        return view('ahg-library::notices.index', [
            'templates' => $templates,
            'tokens'    => self::TOKENS,
        ]);
    }

    /**
     * GET /library-manage/notice-templates/{id}/edit
     */
    public function edit(int $id)
    {
        $template = DB::table('library_notice_template')->where('id', $id)->first();
        if (!$template) {
            abort(404);
        }

        return view('ahg-library::notices.edit', [
            'template' => $template,
            'tokens'   => self::TOKENS,
        ]);
    }

    /**
     * PUT /library-manage/notice-templates/{id}
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'subject'              => 'required|string|max:255',
            'body'                 => 'required|string|max:20000',
            'trigger_days_overdue' => 'required|integer|min:0|max:3650',
            'is_active'            => 'nullable|boolean',
        ]);

        $template = DB::table('library_notice_template')->where('id', $id)->first();
        if (!$template) {
            abort(404);
        }

        DB::table('library_notice_template')->where('id', $id)->update([
            'subject'              => $validated['subject'],
            'body'                 => $validated['body'],
            'trigger_days_overdue' => (int) $validated['trigger_days_overdue'],
            'is_active'            => $request->boolean('is_active') ? 1 : 0,
            'updated_at'           => now(),
        ]);

        return redirect()
            ->route('library.notice-templates.index')
            ->with('success', __('Notice template updated.'));
    }

    /**
     * POST /library-manage/notice-templates/{id}/preview  (JSON)
     * Render the submitted (unsaved) template against sample tokens.
     */
    public function preview(Request $request, int $id, LibraryOverdueNoticeService $notices)
    {
        $tpl = (object) [
            'subject' => (string) $request->input('subject', ''),
            'body'    => (string) $request->input('body', ''),
        ];

        $sample = [
            'patron_name'  => 'Jane Doe',
            'title'        => 'The Art of Cataloguing',
            'barcode'      => 'CPY-000123',
            'due_date'     => now()->subDays(9)->toDateString(),
            'days_overdue' => '9',
            'currency'     => 'ZAR',
            'fine_per_day' => '0.50',
            'fine_amount'  => '4.50',
            'library_name' => config('app.name', 'Library'),
            'expiry_date'  => now()->addDays(5)->toDateString(),
        ];

        return response()->json([
            'success'  => true,
            'rendered' => $notices->render($tpl, $sample),
        ]);
    }
}
