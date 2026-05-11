<?php

/**
 * ShareLinkAdminController — admin index of all share links.
 *
 * Routes (registered in routes/web.php):
 *   GET /admin/share-links       → index
 *   GET /admin/share-links/{id}  → show
 *
 * Mirrors AtoM's shareLink/admin and shareLink/adminShow actions.
 *
 * Permission: admin (group 100) bypass OR `share_link.list_all` ACL,
 * enforced via the AclCheck service.
 *
 * @phase F
 */

namespace AhgShareLink\Controllers;

use AhgShareLink\Services\AclCheck;
use AhgShareLink\Services\InvalidRequestException;
use AhgShareLink\Services\NotAuthenticatedException;
use AhgShareLink\Services\PermissionDeniedException;
use AhgShareLink\Services\RevokeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShareLinkAdminController extends Controller
{
    private const PAGE_SIZE = 25;

    public function index(Request $request)
    {
        $userId = (int) (Auth::id() ?? 0);
        if (!$this->canList($userId)) {
            abort(403, __('You do not have permission to view share links'));
        }

        $filters = $this->parseFilters($request);
        $page = max(1, (int) $request->input('page', 1));

        [$tokens, $total] = $this->queryList($filters, $page, self::PAGE_SIZE);

        return view('ahg-share-link::admin.index', [
            'tokens'     => $tokens,
            'totalCount' => $total,
            'page'       => $page,
            'pageSize'   => self::PAGE_SIZE,
            'totalPages' => (int) ceil(max(1, $total) / self::PAGE_SIZE),
            'filters'    => $filters,
            'issuers'    => $this->fetchIssuerOptions(),
        ]);
    }

    public function show(int $id)
    {
        $userId = (int) (Auth::id() ?? 0);
        if (!$this->canList($userId)) {
            abort(403, __('You do not have permission to view share links'));
        }

        $row = DB::table('information_object_share_token')->where('id', $id)->first();
        if (!$row) {
            abort(404);
        }

        $issuer = DB::table('user')->where('id', $row->issued_by)->first();
        $i18n = DB::table('information_object_i18n')->where('id', $row->information_object_id)->orderBy('culture')->first();
        $accessLog = DB::table('information_object_share_access')
            ->where('token_id', $id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('ahg-share-link::admin.show', [
            'tokenRow'    => $row,
            'issuerName'  => $issuer ? ($issuer->username ?: ('user #' . $row->issued_by)) : '(unknown)',
            'issuerEmail' => $issuer->email ?? null,
            'ioTitle'     => $i18n->title ?? ('#' . $row->information_object_id),
            'accessLog'   => $accessLog,
            'status'      => $this->resolveStatus($row),
            'publicUrl'   => url('/share/' . $row->token),
        ]);
    }

    /**
     * POST /admin/share-links/{id}/revoke — revoke an active token.
     *
     * Permission: must have share_link.list_all to be on the admin page at all;
     * RevokeService enforces own-vs-others gating internally.
     *
     * @phase G
     */
    public function revoke(int $id, Request $request, RevokeService $revokeService): RedirectResponse
    {
        $userId = (int) (Auth::id() ?? 0);
        if (!$this->canList($userId)) {
            abort(403, __('You do not have permission to revoke share links'));
        }

        $reason = trim((string) $request->input('reason')) ?: null;
        $backUrl = (string) $request->input('back', route('share-link.admin.index'));

        try {
            $result = $revokeService->revoke(userId: $userId, tokenId: $id, reason: $reason);
        } catch (NotAuthenticatedException $e) {
            abort(401, $e->getMessage());
        } catch (PermissionDeniedException $e) {
            abort(403, $e->getMessage());
        } catch (InvalidRequestException $e) {
            abort(404, $e->getMessage());
        }

        if ($result['was_already_revoked']) {
            $msg = __('This share link was already revoked.');
            $flashKey = 'info';
        } else {
            $msg = __('Share link revoked.');
            $flashKey = 'success';
        }

        return redirect($backUrl)->with($flashKey, $msg);
    }

    private function canList(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        try {
            return (new AclCheck())->canUserDo($userId, AclCheck::ACTION_LIST_ALL);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveStatus(object $row): string
    {
        if (!empty($row->revoked_at)) {
            return 'revoked';
        }
        if (strtotime((string) $row->expires_at) <= time()) {
            return 'expired';
        }
        if ($row->max_access !== null && (int) $row->access_count >= (int) $row->max_access) {
            return 'exhausted';
        }
        return 'active';
    }

    /**
     * @return array{status:string,q:string,issuer:?int}
     */
    private function parseFilters(Request $request): array
    {
        $status = (string) $request->input('status', 'active');
        if (!in_array($status, ['active', 'expired', 'revoked', 'exhausted', 'all'], true)) {
            $status = 'active';
        }
        $q = trim((string) $request->input('q', ''));
        $issuer = $request->input('issuer');
        $issuerId = ($issuer !== null && $issuer !== '' && is_numeric($issuer)) ? (int) $issuer : null;
        return ['status' => $status, 'q' => $q, 'issuer' => $issuerId];
    }

    /**
     * @return array{0:\Illuminate\Support\Collection, 1:int}
     */
    private function queryList(array $filters, int $page, int $pageSize): array
    {
        $base = DB::table('information_object_share_token as t')
            ->leftJoin('user as u', 't.issued_by', '=', 'u.id')
            ->leftJoin('information_object_i18n as i18n', 'i18n.id', '=', 't.information_object_id');

        $base = $this->applyFilters($base, $filters);

        $totalQ = clone $base;
        $totalRow = $totalQ->selectRaw('count(distinct t.id) as c')->first();
        $total = (int) ($totalRow->c ?? 0);

        $rows = $base
            ->select(
                't.*',
                'u.username as issuer_username',
                DB::raw('(SELECT i.title FROM information_object_i18n i WHERE i.id = t.information_object_id ORDER BY i.culture LIMIT 1) as io_title'),
            )
            ->groupBy('t.id')
            ->orderByDesc('t.id')
            ->forPage($page, $pageSize)
            ->get();

        return [$rows, $total];
    }

    private function applyFilters($q, array $filters)
    {
        $now = date('Y-m-d H:i:s');
        switch ($filters['status']) {
            case 'active':
                // Truly active: not revoked, not expired, and either no quota
                // or quota not yet reached. Excludes exhausted tokens.
                $q->whereNull('t.revoked_at')
                    ->where('t.expires_at', '>', $now)
                    ->where(function ($qq) {
                        $qq->whereNull('t.max_access')
                            ->orWhereColumn('t.access_count', '<', 't.max_access');
                    });
                break;
            case 'expired':
                $q->whereNull('t.revoked_at')->where('t.expires_at', '<=', $now);
                break;
            case 'revoked':
                $q->whereNotNull('t.revoked_at');
                break;
            case 'exhausted':
                $q->whereNull('t.revoked_at')
                    ->where('t.expires_at', '>', $now)
                    ->whereNotNull('t.max_access')
                    ->whereColumn('t.access_count', '>=', 't.max_access');
                break;
            case 'all':
            default:
                break;
        }
        if ($filters['issuer'] !== null) {
            $q->where('t.issued_by', $filters['issuer']);
        }
        if ($filters['q'] !== '') {
            $needle = '%' . $filters['q'] . '%';
            $q->where(function ($qq) use ($needle) {
                $qq->where('t.token', 'like', $needle)
                    ->orWhere('t.recipient_email', 'like', $needle)
                    ->orWhere('i18n.title', 'like', $needle);
            });
        }
        return $q;
    }

    /** @return array<int,object> */
    private function fetchIssuerOptions(): array
    {
        return DB::table('information_object_share_token as t')
            ->leftJoin('user as u', 't.issued_by', '=', 'u.id')
            ->select('t.issued_by', 'u.username')
            ->distinct()
            ->orderBy('u.username')
            ->get()
            ->all();
    }
}
