<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgIngest\Controllers;

use AhgIngest\Services\IngestService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Resumable / chunked web-upload handler for large (>1GB) ingest files (#1328).
 *
 * Custom chunk protocol (no external dependency): the browser slices a file
 * into fixed-size parts and POSTs them one at a time to `chunk`; `status` lets
 * it resume after an interruption by reporting which parts already landed;
 * `complete` reassembles the parts in order, verifies the whole-file sha256,
 * and hands the staged file to IngestService::ingestFile() - the same entry
 * point ahg-scan uses - so the normal digital-object creation, checksum and
 * repository-quota checks all apply. A PREMIS receipt event is recorded.
 *
 * Parts live under {storage_path}/.chunks/{sessionId}/{uploadId}/ and are
 * removed on completion or abort. Because each part is small, the upload is
 * never bounded by post_max_size / upload_max_filesize for the whole file.
 */
class ChunkedUploadController extends Controller
{
    protected IngestService $service;

    public function __construct()
    {
        $this->service = new IngestService();
    }

    /** Receive one chunk. */
    public function chunk(Request $request, int $id)
    {
        abort_unless($this->service->getSession($id), 404);

        $request->validate([
            'upload_id'    => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'chunk_index'  => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:200000'],
            'chunk'        => ['required', 'file'],
        ]);

        $dir = $this->chunkDir($id, $request->input('upload_id'));
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
            return response()->json(['ok' => false, 'error' => 'Could not create staging directory.'], 500);
        }

        $index = (int) $request->input('chunk_index');
        $part = $dir.'/'.$index.'.part';
        if (! @move_uploaded_file($request->file('chunk')->getRealPath(), $part)
            && ! @copy($request->file('chunk')->getRealPath(), $part)) {
            return response()->json(['ok' => false, 'error' => 'Could not store chunk.'], 500);
        }
        @chmod($part, 0644);

        return response()->json([
            'ok'       => true,
            'received' => $index,
            'count'    => count(glob($dir.'/*.part') ?: []),
        ]);
    }

    /** Which chunk indices are already on the server (for resume). */
    public function status(Request $request, int $id)
    {
        abort_unless($this->service->getSession($id), 404);
        $uploadId = (string) $request->query('upload_id', '');
        $dir = $this->chunkDir($id, $uploadId);
        $received = [];
        foreach (glob($dir.'/*.part') ?: [] as $f) {
            $received[] = (int) basename($f, '.part');
        }
        sort($received);

        return response()->json(['ok' => true, 'received' => $received]);
    }

    /** Reassemble, checksum-verify, and ingest. */
    public function complete(Request $request, int $id)
    {
        $session = $this->service->getSession($id);
        abort_unless($session, 404);

        $request->validate([
            'upload_id'    => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'file_name'    => ['required', 'string', 'max:500'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'checksum'     => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);

        $uploadId = $request->input('upload_id');
        $total = (int) $request->input('total_chunks');
        $dir = $this->chunkDir($id, $uploadId);

        // All parts present?
        $missing = [];
        for ($i = 0; $i < $total; $i++) {
            if (! is_file($dir.'/'.$i.'.part')) {
                $missing[] = $i;
            }
        }
        if (! empty($missing)) {
            return response()->json(['ok' => false, 'error' => 'Missing chunks; resume required.', 'missing' => $missing], 409);
        }

        $safeName = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($request->input('file_name')));
        $assembled = $dir.'/assembled-'.$safeName;

        // Reassemble in order via a streamed copy (constant memory).
        $out = @fopen($assembled, 'wb');
        if ($out === false) {
            return response()->json(['ok' => false, 'error' => 'Could not open output file.'], 500);
        }
        for ($i = 0; $i < $total; $i++) {
            $in = @fopen($dir.'/'.$i.'.part', 'rb');
            if ($in === false) {
                @fclose($out);
                $this->cleanup($dir);
                return response()->json(['ok' => false, 'error' => "Could not read chunk {$i}."], 500);
            }
            stream_copy_to_stream($in, $out);
            @fclose($in);
        }
        @fclose($out);

        // Verify the whole-file checksum when the client supplied one.
        $checksum = $request->input('checksum');
        if ($checksum && ! hash_equals(strtolower($checksum), (string) @hash_file('sha256', $assembled))) {
            $this->cleanup($dir);
            return response()->json(['ok' => false, 'error' => 'Checksum mismatch; the upload was corrupted. Please retry.'], 422);
        }

        try {
            $result = $this->service->ingestFile($id, $assembled, [], $safeName);
        } catch (\Throwable $e) {
            $this->cleanup($dir);
            Log::warning('Chunked ingest failed', ['session' => $id, 'error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $ioId = (int) ($result['io_id'] ?? 0);
        $this->emitPremisReceipt($ioId, $safeName, (string) @hash_file('sha256', $assembled), $total);
        $this->cleanup($dir);

        return response()->json([
            'ok'    => true,
            'io_id' => $ioId,
            'do_id' => (int) ($result['do_id'] ?? 0),
        ]);
    }

    /** Discard a partial upload. */
    public function abort(Request $request, int $id)
    {
        abort_unless($this->service->getSession($id), 404);
        $request->validate(['upload_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/']]);
        $this->cleanup($this->chunkDir($id, $request->input('upload_id')));

        return response()->json(['ok' => true]);
    }

    // --- internals --------------------------------------------------------

    private function chunkDir(int $sessionId, string $uploadId): string
    {
        $safeUpload = preg_replace('/[^A-Za-z0-9_-]/', '', $uploadId);

        return rtrim((string) config('heratio.storage_path'), '/').'/.chunks/'.$sessionId.'/'.$safeUpload;
    }

    private function cleanup(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /** Best-effort PREMIS ingestion (receipt) event for the reassembled upload. */
    private function emitPremisReceipt(int $ioId, string $name, string $checksum, int $chunks): void
    {
        if ($ioId <= 0) {
            return;
        }
        try {
            if (! Schema::hasTable('preservation_event')) {
                return;
            }
            DB::table('preservation_event')->insert([
                'information_object_id' => $ioId,
                'event_type'            => 'ingestion (resumable upload)',
                'event_datetime'        => date('Y-m-d H:i:s'),
                'event_detail'          => 'Reassembled '.$chunks.'-part resumable web upload of '.$name,
                'event_outcome'         => 'success',
                'event_outcome_detail'  => json_encode(['chunks' => $chunks, 'checksum' => $checksum, 'checksum_type' => 'sha256']),
                'linking_agent_type'    => 'system',
                'linking_agent_value'   => 'ChunkedUploadController',
                'created_at'            => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
