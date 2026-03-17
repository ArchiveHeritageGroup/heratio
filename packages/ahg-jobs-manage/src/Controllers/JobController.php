<?php

namespace AhgJobsManage\Controllers;

use AhgCore\Pagination\SimplePager;
use AhgCore\Services\SettingHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobController extends Controller
{
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $page = max(1, (int) $request->get('page', 1));
        $limit = max(1, (int) $request->get('limit', SettingHelper::hitsPerPage()));
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
            })
            ->leftJoin('slug as job_slug', 'job.object_id', '=', 'job_slug.object_id');

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
            'job.object_id',
            'job.completed_at',
            'job.download_path',
            'object.created_at',
            'actor_i18n.authorized_form_of_name as user_name',
            'user.username',
            'status_term.name as status_name',
            'job_slug.slug as object_slug',
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

    public function destroy(int $id)
    {
        $job = DB::table('job')->where('id', $id)->first();

        if (!$job || !in_array($job->status_id, [184, 185])) {
            return redirect()->route('job.browse')->with('error', 'Only completed or failed jobs can be deleted.');
        }

        DB::table('job')->where('id', $id)->delete();
        DB::table('object')->where('id', $id)->delete();

        return redirect()->route('job.browse')->with('success', 'Job deleted successfully.');
    }

    public function clearInactive()
    {
        $ids = DB::table('job')
            ->whereIn('status_id', [184, 185])
            ->pluck('id')
            ->toArray();

        if (count($ids) > 0) {
            DB::table('job')->whereIn('id', $ids)->delete();
            DB::table('object')->whereIn('id', $ids)->delete();
        }

        $count = count($ids);

        return redirect()->route('job.browse')->with('success', "{$count} inactive job(s) cleared.");
    }

    public function exportCsv(): StreamedResponse
    {
        $culture = app()->getLocale();

        $jobs = DB::table('job')
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
            ->select([
                'job.name',
                'status_term.name as status_name',
                'actor_i18n.authorized_form_of_name as user_name',
                'user.username',
                'object.created_at',
                'job.completed_at',
            ])
            ->orderBy('object.created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="jobs-export.csv"',
        ];

        return new StreamedResponse(function () use ($jobs) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Name', 'Status', 'User', 'Created', 'Completed']);

            foreach ($jobs as $job) {
                fputcsv($handle, [
                    $job->name,
                    $job->status_name ?? '',
                    $job->user_name ?: $job->username ?: '',
                    $job->created_at ?? '',
                    $job->completed_at ?? '',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
