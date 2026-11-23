<?php

/**
 * MarcRecordController - source record history, export, diff and replay (#1476).
 *
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\MarcEditService;
use AhgLibrary\Services\MarcRecordReplayService;
use AhgLibrary\Services\MarcRecordStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MarcRecordController extends Controller
{
    public function __construct(
        private MarcRecordStore $store,
        private MarcRecordReplayService $replay,
    ) {
    }

    public function history(int $id)
    {
        $this->itemOr404($id);

        return view('ahg-library::marc-records.history', [
            'libraryItemId' => $id,
            'versions' => $this->store->versions($id),
            'eventLabels' => $this->store->labels('library_marc_record_event'),
            'formatLabels' => $this->store->labels('library_marc_source_format'),
        ]);
    }

    public function download(int $id, int $version, string $format)
    {
        $this->itemOr404($id);
        $row = $this->store->version($id, $version);
        abort_unless($row, 404);

        $content = $format === 'binary' ? $row->source_binary : $row->marcxml;
        abort_if($content === null, 404);

        // The original bytes of a non-UTF-8 XML file are still XML.
        $isIso = $format === 'binary' && $row->source_format === 'iso2709';
        $extension = $isIso ? 'mrc' : 'xml';
        return response($content, 200, [
            'Content-Type' => $isIso ? 'application/marc' : ($format === 'binary' ? 'application/xml' : 'application/xml; charset=utf-8'),
            'Content-Disposition' => "attachment; filename=\"marc-{$id}-v{$version}.{$extension}\"",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function diff(Request $request, int $id)
    {
        $this->itemOr404($id);
        $data = $request->validate([
            'from' => 'nullable|integer|min:1',
            'to' => 'nullable|integer|min:1',
        ]);

        $from = isset($data['from']) ? $this->store->version($id, (int) $data['from']) : $this->store->replaySource($id);
        if (! $from && ! isset($data['from'])) {
            $versions = $this->store->versions($id);
            $oldest = end($versions);
            $from = $oldest ? $this->store->version($id, (int) $oldest->version) : null;
        }
        $to = isset($data['to']) ? $this->store->version($id, (int) $data['to']) : null;
        abort_if(isset($data['from']) && ! $from, 404);
        abort_if(isset($data['to']) && ! $to, 404);

        $toXml = $to ? $to->marcxml : (new MarcEditService())->exportLibraryItem($id, (string) app()->getLocale());
        $comparable = $from && $from->marcxml !== null && $toXml !== null && $toXml !== '';

        return view('ahg-library::marc-records.diff', [
            'libraryItemId' => $id,
            'from' => $from,
            'to' => $to,
            'comparable' => $comparable,
            'result' => $comparable ? $this->store->diff($from->marcxml, $toXml) : null,
        ]);
    }

    public function replayPreview(int $id)
    {
        $this->itemOr404($id);

        return view('ahg-library::marc-records.replay', ['libraryItemId' => $id] + $this->replay->plan($id));
    }

    public function replayApply(Request $request, int $id)
    {
        $this->itemOr404($id);
        $data = $request->validate([
            'columns' => 'required|array|min:1',
            'columns.*' => ['string', Rule::in(array_keys(MarcRecordReplayService::COLUMNS))],
        ]);

        $written = $this->replay->apply($id, $data['columns']);

        return redirect()->route('library.marc-source', $id)
            ->with('success', __(':count field(s) updated from the source record.', ['count' => $written]));
    }

    private function itemOr404(int $id): void
    {
        abort_unless(DB::table('library_item')->where('id', $id)->exists(), 404);
    }
}
