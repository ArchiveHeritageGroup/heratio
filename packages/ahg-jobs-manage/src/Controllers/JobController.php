<?php

namespace AhgJobsManage\Controllers;

use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', 30));
        $status = $request->get('status', '');
        $sort = $request->get('sort', 'date');

        // Job stats
        $totalCount = DB::table('job')->count();
        $completedCount = DB::table('job')->where('status_id', 184)->count();
        $errorCount = DB::table('job')->where('status_id', 185)->count();
        $runningCount = DB::table('job')
            ->whereNull('completed_at')
            ->whereNotIn('status_id', [184, 185])
            ->count();

        // Build query
        $query = DB::table('job')
            ->join('object', 'job.id', '=', 'object.id')
            ->leftJoin('user', 'job.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as status_term', function ($join) use ($culture) {
                $join->on('job.status_id', '=', 'status_term.id')
                    ->where('status_term.culture', '=', $culture);
            });

        // Apply status filter
        if ($status === 'completed') {
            $query->where('job.status_id', 184);
        } elseif ($status === 'error') {
            $query->where('job.status_id', 185);
        } elseif ($status === 'running') {
            $query->whereNull('job.completed_at')
                ->whereNotIn('job.status_id', [184, 185]);
        }

        // Count for pagination
        $total = $query->count();

        // Select and sort
        $query->select([
            'job.id',
            'job.name',
            'job.status_id',
            'job.completed_at',
            'job.download_path',
            'object.created_at',
            'actor_i18n.authorized_form_of_name as user_name',
            'user.username',
            'status_term.name as status_name',
        ]);

        if ($sort === 'name') {
            $query->orderBy('job.name', 'asc');
        } else {
            $query->orderBy('object.created_at', 'desc');
        }

        $jobs = $query
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get()
            ->map(function ($job) {
                return (array) $job;
            })
            ->toArray();

        $pager = new SimplePager([
            'hits' => $jobs,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

        return view('ahg-jobs-manage::browse', [
            'pager' => $pager,
            'stats' => [
                'total' => $totalCount,
                'completed' => $completedCount,
                'error' => $errorCount,
                'running' => $runningCount,
            ],
            'currentStatus' => $status,
            'sortOptions' => [
                'date' => 'Date',
                'name' => 'Name',
            ],
        ]);
    }

    public function show(int $id)
    {
        $culture = app()->getLocale();

        $job = DB::table('job')
            ->join('object', 'job.id', '=', 'object.id')
            ->leftJoin('user', 'job.user_id', '=', 'user.id')
            ->leftJoin('actor_i18n', function ($join) use ($culture) {
                $join->on('user.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as status_term', function ($join) use ($culture) {
                $join->on('job.status_id', '=', 'status_term.id')
                    ->where('status_term.culture', '=', $culture);
            })
            ->where('job.id', $id)
            ->select([
                'job.id',
                'job.name',
                'job.status_id',
                'job.completed_at',
                'job.download_path',
                'job.output',
                'job.object_id',
                'object.created_at',
                'actor_i18n.authorized_form_of_name as user_name',
                'user.username',
                'status_term.name as status_name',
            ])
            ->first();

        if (!$job) {
            abort(404);
        }

        return view('ahg-jobs-manage::show', [
            'job' => $job,
        ]);
    }
}
