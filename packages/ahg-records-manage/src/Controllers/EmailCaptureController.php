<?php

/**
 * EmailCaptureController — capture, classify, declare email-as-record (P2.6).
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgRecordsManage\Controllers;

use AhgRecordsManage\Services\EmailCaptureService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailCaptureController extends Controller
{
    public function __construct(protected EmailCaptureService $emails)
    {
    }

    /** GET /admin/records/emails */
    public function index(Request $request)
    {
        $filters = [
            'status' => $request->query('status'),
            'source' => $request->query('source'),
            'q'      => $request->query('q'),
            'limit'  => (int) $request->query('limit', 100),
            'offset' => (int) $request->query('offset', 0),
        ];

        $page   = $this->emails->listQueue($filters);
        $counts = $this->emails->counts();

        return view('ahg-records::emails.index', [
            'rows'    => $page['rows'],
            'total'   => $page['total'],
            'filters' => $filters,
            'counts'  => $counts,
        ]);
    }

    /** GET /admin/records/emails/upload */
    public function uploadForm()
    {
        return view('ahg-records::emails.upload');
    }

    /** POST /admin/records/emails/upload */
    public function upload(Request $request)
    {
        $request->validate([
            'eml_file' => 'required|file|max:51200', // 50 MB cap
        ]);

        try {
            $result = $this->emails->captureFromEml($request->file('eml_file'), auth()->id() ?? 0);
        } catch (\Throwable $e) {
            return redirect()->route('records.emails.upload-form')
                ->with('error', 'Could not parse EML: ' . $e->getMessage());
        }

        $msg = $result['duplicate']
            ? 'This message was already captured (Message-ID match) — opened existing record.'
            : 'Email captured.';
        return redirect()->route('records.emails.show', $result['id'])->with('success', $msg);
    }

    /** GET /admin/records/emails/{id} */
    public function show(int $id)
    {
        $email = $this->emails->get($id);
        if (! $email) {
            abort(404, 'Email not found');
        }

        $fileplanNodes = DB::table('rm_fileplan_node')
            ->where('status', 'active')
            ->orderBy('lft')
            ->limit(500)
            ->get(['id', 'code', 'title', 'depth']);

        $disposalClasses = DB::table('rm_disposal_class')
            ->where('is_active', 1)
            ->orderBy('class_ref')
            ->get(['id', 'class_ref', 'title', 'retention_period_years', 'disposal_action']);

        return view('ahg-records::emails.show', [
            'email'           => $email,
            'fileplanNodes'   => $fileplanNodes,
            'disposalClasses' => $disposalClasses,
        ]);
    }

    /** POST /admin/records/emails/{id}/classify */
    public function classify(Request $request, int $id)
    {
        $data = $request->validate([
            'fileplan_node_id'  => 'required|integer',
            'disposal_class_id' => 'nullable|integer',
        ]);

        $ok = $this->emails->classify($id, $data['fileplan_node_id'], $data['disposal_class_id'] ?? null, auth()->id() ?? 0);

        return redirect()->route('records.emails.show', $id)
            ->with($ok ? 'success' : 'error', $ok ? 'Email classified.' : 'Could not classify this email.');
    }

    /** POST /admin/records/emails/{id}/declare */
    public function declareRecord(int $id)
    {
        $ioId = $this->emails->declareAsRecord($id, auth()->id() ?? 0);
        if ($ioId === null) {
            return redirect()->route('records.emails.show', $id)->with('error', 'Could not declare this email as a record.');
        }
        return redirect()->route('records.emails.show', $id)->with('success', "Declared as information_object #{$ioId}.");
    }
}
