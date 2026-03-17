<?php

namespace AhgRequestPublish\Controllers;

use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RequestPublishController extends Controller
{
    /**
     * Status ID constants from AtoM term table.
     */
    protected const STATUS_APPROVED = 219;
    protected const STATUS_PENDING = 220;
    protected const STATUS_REJECTED = 221;

    /**
     * Browse all publication requests with filtering, sorting, and pagination.
     */
    public function browse(Request $request)
    {
        // Gracefully handle missing table
        if (!Schema::hasTable('request_to_publish') || !Schema::hasTable('request_to_publish_i18n')) {
            return view('ahg-request-publish::browse', [
                'tableExists' => false,
            ]);
        }

        $culture = app()->getLocale();
        $status = $request->input('status', 'all');
        $sort = $request->input('sort', 'nameUp');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;

        // Base query builder
        $baseQuery = function () use ($culture) {
            return DB::table('request_to_publish as rtp')
                ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                    $j->on('rtp.id', '=', 'i18n.id')
                        ->where('i18n.culture', '=', $culture);
                })
                ->leftJoin('information_object as io', 'i18n.object_id', '=', 'io.id')
                ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                    $j->on('io.id', '=', 'ioi.id')
                        ->where('ioi.culture', '=', $culture);
                })
                ->leftJoin('slug as ios', 'io.id', '=', 'ios.object_id');
        };

        // Status counts
        $allCount = DB::table('request_to_publish as rtp')
            ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                $j->on('rtp.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->count();

        $pendingCount = DB::table('request_to_publish as rtp')
            ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                $j->on('rtp.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->where('i18n.status_id', self::STATUS_PENDING)
            ->count();

        $approvedCount = DB::table('request_to_publish as rtp')
            ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                $j->on('rtp.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->where('i18n.status_id', self::STATUS_APPROVED)
            ->count();

        $rejectedCount = DB::table('request_to_publish as rtp')
            ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                $j->on('rtp.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->where('i18n.status_id', self::STATUS_REJECTED)
            ->count();

        // Build main query
        $query = $baseQuery();

        // Filter by status
        if ($status === 'review') {
            $query->where('i18n.status_id', self::STATUS_PENDING);
        } elseif ($status === 'approved') {
            $query->where('i18n.status_id', self::STATUS_APPROVED);
        } elseif ($status === 'rejected') {
            $query->where('i18n.status_id', self::STATUS_REJECTED);
        }

        // Non-admin users: only see their own requests
        $user = Auth::user();
        if ($user && !$user->is_admin) {
            $query->where('i18n.unique_identifier', $user->id);
        }

        // Total for current filter
        $total = (clone $query)->count();

        // Sorting
        switch ($sort) {
            case 'nameDown':
                $query->orderBy('i18n.rtp_name', 'desc');
                break;
            case 'instUp':
                $query->orderBy('i18n.rtp_institution', 'asc');
                break;
            case 'instDown':
                $query->orderBy('i18n.rtp_institution', 'desc');
                break;
            case 'nameUp':
            default:
                $query->orderBy('i18n.rtp_name', 'asc');
                break;
        }

        // Paginate
        $offset = ($page - 1) * $limit;
        $rows = $query
            ->select(
                'rtp.id',
                'rtp.source_culture',
                'i18n.unique_identifier',
                'i18n.rtp_name',
                'i18n.rtp_surname',
                'i18n.rtp_phone',
                'i18n.rtp_email',
                'i18n.rtp_institution',
                'i18n.rtp_motivation',
                'i18n.rtp_planned_use',
                'i18n.rtp_need_image_by',
                'i18n.status_id',
                'i18n.object_id',
                'i18n.completed_at',
                'i18n.rtp_admin_notes',
                'i18n.created_at',
                'ioi.title as object_title',
                'io.identifier as object_identifier',
                'ios.slug as object_slug'
            )
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

        return view('ahg-request-publish::browse', [
            'tableExists' => true,
            'pager' => $pager,
            'status' => $status,
            'sort' => $sort,
            'allCount' => $allCount,
            'pendingCount' => $pendingCount,
            'approvedCount' => $approvedCount,
            'rejectedCount' => $rejectedCount,
        ]);
    }

    /**
     * Edit a single publication request (admin).
     */
    public function edit(int $id)
    {
        $culture = app()->getLocale();

        $record = DB::table('request_to_publish as rtp')
            ->join('request_to_publish_i18n as i18n', function ($j) use ($culture) {
                $j->on('rtp.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('information_object as io', 'i18n.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as ios', 'io.id', '=', 'ios.object_id')
            ->where('rtp.id', '=', $id)
            ->select(
                'rtp.id',
                'rtp.source_culture',
                'i18n.unique_identifier',
                'i18n.rtp_name',
                'i18n.rtp_surname',
                'i18n.rtp_phone',
                'i18n.rtp_email',
                'i18n.rtp_institution',
                'i18n.rtp_motivation',
                'i18n.rtp_planned_use',
                'i18n.rtp_need_image_by',
                'i18n.status_id',
                'i18n.object_id',
                'i18n.completed_at',
                'i18n.rtp_admin_notes',
                'i18n.created_at',
                'ioi.title as object_title',
                'io.identifier as object_identifier',
                'ios.slug as object_slug'
            )
            ->first();

        if (!$record) {
            abort(404, 'Publication request not found.');
        }

        return view('ahg-request-publish::edit', [
            'record' => $record,
        ]);
    }

    /**
     * Update a publication request (status, admin notes, completed_at).
     */
    public function update(Request $request, int $id)
    {
        $culture = app()->getLocale();

        $request->validate([
            'status_id' => 'required|in:' . self::STATUS_APPROVED . ',' . self::STATUS_PENDING . ',' . self::STATUS_REJECTED,
            'rtp_admin_notes' => 'nullable|string',
        ]);

        $statusId = (int) $request->input('status_id');
        $data = [
            'status_id' => $statusId,
            'rtp_admin_notes' => $request->input('rtp_admin_notes'),
        ];

        // Set completed_at when approved or rejected, clear when set back to pending
        if (in_array($statusId, [self::STATUS_APPROVED, self::STATUS_REJECTED])) {
            $data['completed_at'] = now();
        } else {
            $data['completed_at'] = null;
        }

        DB::table('request_to_publish_i18n')
            ->where('id', '=', $id)
            ->where('culture', '=', $culture)
            ->update($data);

        return redirect()->route('request-publish.browse')
            ->with('success', 'Publication request updated successfully.');
    }

    /**
     * Get a human-readable status label from status_id.
     */
    public static function getStatusLabel(int $statusId): string
    {
        return match ($statusId) {
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PENDING => 'In Review',
            default => 'Unknown',
        };
    }

    /**
     * Get Bootstrap badge class from status_id.
     */
    public static function getStatusBadgeClass(int $statusId): string
    {
        return match ($statusId) {
            self::STATUS_APPROVED => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_PENDING => 'bg-warning text-dark',
            default => 'bg-secondary',
        };
    }
}
