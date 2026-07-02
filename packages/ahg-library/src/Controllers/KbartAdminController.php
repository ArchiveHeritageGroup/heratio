<?php

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\KbartRemoteService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class KbartAdminController extends Controller
{
    public function __construct(
        private KbartRemoteService $remote
    ) {}

    /**
     * GET /library-manage/kbart/remote
     * Feed subscription dashboard: list all feeds with last-run metadata.
     */
    public function index(): \Illuminate\View\View
    {
        $feeds = $this->remote->listFeeds();

        return view('ahg-library::kbart.admin-remote', [
            'feeds' => $feeds,
            'auto_import_enabled' => $this->remote->isAutoImportEnabled(),
        ]);
    }

    /**
     * GET /library-manage/kbart/remote/log
     * #768 Refresh-log admin page: per-fetch history with diff counts.
     */
    public function log(): \Illuminate\View\View
    {
        if (!Schema::hasTable('library_kbart_import_log')) {
            $rows = collect();
        } else {
            $rows = DB::table('library_kbart_import_log')
                ->leftJoin('library_kbart_feed', 'library_kbart_feed.id', '=', 'library_kbart_import_log.feed_id')
                ->select(
                    'library_kbart_import_log.id',
                    'library_kbart_import_log.feed_id',
                    'library_kbart_feed.name as feed_name',
                    'library_kbart_feed.url as feed_url',
                    'library_kbart_import_log.status',
                    'library_kbart_import_log.row_count',
                    'library_kbart_import_log.added',
                    'library_kbart_import_log.removed',
                    'library_kbart_import_log.changed',
                    'library_kbart_import_log.error',
                    'library_kbart_import_log.diff_sample',
                    'library_kbart_import_log.elapsed_ms',
                    'library_kbart_import_log.created_at'
                )
                ->orderByDesc('library_kbart_import_log.created_at')
                ->paginate(50);
        }

        return view('ahg-library::kbart.refresh-log', ['rows' => $rows]);
    }

    /**
     * GET /library-manage/kbart/remote/create
     */
    public function create(): \Illuminate\View\View
    {
        return view('ahg-library::kbart.remote-form', [
            'feed'   => null,
            'url'    => action([\AhgLibrary\Controllers\KbartAdminController::class, 'store']),
            'method' => 'POST',
        ]);
    }

    /**
     * POST /library-manage/kbart/remote
     * Create or update a feed subscription.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'url'    => 'required|url|max:1000',
            'vendor' => 'nullable|string|max:255',
            'notes'  => 'nullable|string|max:2000',
            'active' => 'nullable|in:1',
        ]);

        $id = $this->remote->saveFeed(null, $request->input());

        $this->remote->ensureFeedTable();
        $feed = \Illuminate\Support\Facades\DB::table('library_kbart_feed')->where('id', $id)->first(['name', 'url']);

        return redirect()
            ->action([KbartAdminController::class, 'index'])
            ->with('success', "Feed '{$feed->name}' created.");
    }

    /**
     * GET /library-manage/kbart/remote/{feed}/edit
     */
    public function edit(int $feed): \Illuminate\View\View
    {
        $row = $this->fetchFeed($feed);

        return view('ahg-library::kbart.remote-form', [
            'feed'   => $row,
            'url'    => action([KbartAdminController::class, 'update'], ['feed' => $feed]),
            'method' => 'PUT',
        ]);
    }

    /**
     * PUT /library-manage/kbart/remote/{feed}
     */
    public function update(Request $request, int $feed): RedirectResponse
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'url'    => 'required|url|max:1000',
            'vendor' => 'nullable|string|max:255',
            'notes'  => 'nullable|string|max:2000',
            'active' => 'nullable|in:1',
        ]);

        $this->fetchFeed($feed); // fail fast
        $this->remote->saveFeed($feed, $request->input());

        $name = \Illuminate\Support\Facades\DB::table('library_kbart_feed')->where('id', $feed)->value('name');
        return redirect()
            ->action([KbartAdminController::class, 'index'])
            ->with('success', "Feed '{$name}' updated.");
    }

    /**
     * POST /library-manage/kbart/remote/{feed}/refresh
     * Trigger a single feed fetch now (manual refresh).
     */
    public function refresh(Request $request, int $feed): RedirectResponse
    {
        $row = $this->fetchFeed($feed);
        $result = $this->remote->fetchSingleFeed($feed, $row->name, $row->url);

        $msg = $result['status'] === 'success'
            ? "Refreshed '{$row->name}': {$result['row_count']} row(s) imported."
            : "Refresh failed for '{$row->name}': {$result['error']}";

        return redirect()
            ->action([KbartAdminController::class, 'index'])
            ->with($result['status'] === 'success' ? 'success' : 'error', $msg);
    }

    /**
     * POST /library-manage/kbart/remote/{feed}/toggle
     */
    public function toggle(int $feed): RedirectResponse
    {
        $this->fetchFeed($feed);
        $new = $this->remote->toggleFeed($feed);
        $state = $new ? 'activated' : 'deactivated';
        return redirect()
            ->action([KbartAdminController::class, 'index'])
            ->with('success', "Feed {$feed} {$state}.");
    }

    /**
     * DELETE /library-manage/kbart/remote/{feed}
     */
    public function destroy(int $feed): RedirectResponse
    {
        $row = $this->fetchFeed($feed);
        $this->remote->deleteFeed($feed);
        return redirect()
            ->action([KbartAdminController::class, 'index'])
            ->with('success', "Feed '{$row->name}' deleted.");
    }

    /**
     * GET /library-manage/kbart/remote/test-url
     * Probe a feed URL before saving to see if it's reachable.
     */
    public function testUrl(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['url' => 'required|url']);

        // #1395(C) — SSRF guard: reject internal/metadata/private hosts and do
        // not follow redirects (a 30x could rebind to a private IP).
        $ssrf = app(\AhgCore\Services\SsrfGuard::class);
        if (! $ssrf->isSafeUrl($request->input('url'))) {
            return response()->json(['ok' => false, 'hint' => 'URL host is not permitted.'], 422);
        }

        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(30)
                ->withOptions($ssrf->safeHttpOptions())
                ->get($request->input('url'));
            $ok = $resp->successful() && trim($resp->body()) !== '';
            return response()->json([
                'ok'   => $ok,
                'size' => strlen($resp->body()),
                'status' => $resp->status(),
                'hint' => $ok ? 'URL is reachable and returned content.' : "HTTP {$resp->status()}",
            ]);
        } catch (Throwable $e) {
            return response()->json(['ok' => false, 'hint' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    private function fetchFeed(int $feed): object
    {
        $this->remote->ensureFeedTable();
        $row = \Illuminate\Support\Facades\DB::table('library_kbart_feed')->where('id', $feed)->first();
        if (! $row) {
            abort(404, "Feed #{$feed} not found.");
        }
        return $row;
    }
}
