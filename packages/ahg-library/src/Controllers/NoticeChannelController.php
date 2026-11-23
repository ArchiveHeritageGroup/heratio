<?php

/**
 * NoticeChannelController - per-channel notice log, print runs and patron
 * channel preference (#1474 item 2).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryNoticeChannelService;
use AhgLibrary\Services\LibraryNoticePrintService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NoticeChannelController extends Controller
{
    public function __construct(
        private LibraryNoticeChannelService $channels,
        private LibraryNoticePrintService $print,
    ) {
    }

    public function index()
    {
        return view('ahg-library::notices.channels', [
            'summary'      => DB::table('library_overdue_notice_log')
                ->select('channel', 'status', DB::raw('COUNT(*) as total'))
                ->groupBy('channel', 'status')->orderBy('channel')->orderBy('status')->get(),
            'channels'     => $this->channels->channelOptions(),
            'statuses'     => $this->channels->statusLabels(),
            'smsReady'     => $this->channels->deliverableSmsGateway() !== null,
            'queued'       => $this->print->queuedCount(),
            'maxRun'       => LibraryNoticePrintService::MAX_RUN_SIZE,
            'runs'         => DB::table('library_notice_print_batch')->orderByDesc('id')->limit(25)->get(),
        ]);
    }

    public function createRun(Request $request)
    {
        $result = $this->print->createRun($request->user()?->id);
        $back = redirect()->route('library.notice-channel.index');

        if ($result['batch_id'] === null) {
            return $back->with('error', __('No notices to print. Withdrawn because the loan has ended: :n. Replaced by a later notice: :s', ['n' => $result['withdrawn'], 's' => $result['superseded']]));
        }
        return $back->with('success', __('Print run :id created with :n notices. Withdrawn because the loan has ended: :w. Replaced by a later notice: :s', [
            'id' => $result['batch_id'], 'n' => $result['included'], 'w' => $result['withdrawn'], 's' => $result['superseded'],
        ]));
    }

    public function downloadRun(int $id)
    {
        abort_unless(DB::table('library_notice_print_batch')->where('id', $id)->exists(), 404);

        return response($this->print->renderPdf($id), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="overdue-notices-run-' . $id . '.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    public function patron(int $id)
    {
        $patron = DB::table('library_patron')->where('id', $id)->first();
        abort_unless($patron, 404);

        return view('ahg-library::notices.patron-channel', [
            'patron'     => $patron,
            'preference' => $this->channels->preferenceFor($id),
            'channels'   => $this->channels->channelOptions(),
            'phoneOk'    => $this->channels->normalisePhone($patron->phone) !== null,
        ]);
    }

    public function updatePatron(Request $request, int $id)
    {
        abort_unless(DB::table('library_patron')->where('id', $id)->exists(), 404);

        $validated = $request->validate([
            'channel' => ['nullable', 'string', Rule::in(array_keys($this->channels->channelOptions()))],
        ]);
        $this->channels->setPreference($id, ($validated['channel'] ?? '') ?: null, $request->user()?->id);

        return redirect()->route('library.patron-view', $id)->with('success', __('Notice channel updated.'));
    }
}
