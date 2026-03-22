<?php

namespace AhgStatistics\Controllers;

use AhgStatistics\Services\StatisticsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    protected StatisticsService $service;

    public function __construct()
    {
        $this->service = new StatisticsService();
    }

    public function dashboard(Request $request)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $stats = $this->service->getDashboardStats($startDate, $endDate);
        $topItems = $this->service->getTopItems('view', 10, $startDate, $endDate);
        $topDownloads = $this->service->getTopItems('download', 10, $startDate, $endDate);
        $geoStats = array_slice($this->service->getGeographicStats($startDate, $endDate), 0, 10);
        $viewsData = $this->service->getViewsOverTime($startDate, $endDate);

        return view('ahg-statistics::dashboard', compact('stats', 'topItems', 'topDownloads', 'geoStats', 'viewsData', 'startDate', 'endDate'));
    }

    public function views(Request $request)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());
        $groupBy = $request->get('group', 'day');

        $data = $this->service->getViewsOverTime($startDate, $endDate, $groupBy);

        return view('ahg-statistics::views', compact('data', 'startDate', 'endDate', 'groupBy'));
    }

    public function downloads(Request $request)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $data = $this->service->getDownloadsOverTime($startDate, $endDate);
        $topDownloads = $this->service->getTopItems('download', 50, $startDate, $endDate);

        return view('ahg-statistics::downloads', compact('data', 'topDownloads', 'startDate', 'endDate'));
    }

    public function topItems(Request $request)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());
        $eventType = $request->get('type', 'view');
        $limit = min((int) $request->get('limit', 50), 500);

        $items = $this->service->getTopItems($eventType, $limit, $startDate, $endDate);

        return view('ahg-statistics::top-items', compact('items', 'startDate', 'endDate', 'eventType', 'limit'));
    }

    public function geographic(Request $request)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $data = $this->service->getGeographicStats($startDate, $endDate);

        return view('ahg-statistics::geographic', compact('data', 'startDate', 'endDate'));
    }

    public function item(Request $request)
    {
        $objectId = (int) $request->get('object_id');
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $culture = app()->getLocale();
        $object = \Illuminate\Support\Facades\DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) use ($culture) {
                $j->on('io.id', '=', 'ioi.id')->where('ioi.culture', '=', $culture);
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'ioi.title', 's.slug')
            ->first();

        abort_unless($object, 404, 'Object not found');

        $stats = $this->service->getItemStats($objectId, $startDate, $endDate);

        return view('ahg-statistics::item', compact('object', 'stats', 'startDate', 'endDate'));
    }

    public function repository(Request $request, int $id)
    {
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $culture = app()->getLocale();
        $repository = \Illuminate\Support\Facades\DB::table('actor as a')
            ->leftJoin('actor_i18n as ai', function ($j) use ($culture) {
                $j->on('a.id', '=', 'ai.id')->where('ai.culture', '=', $culture);
            })
            ->where('a.id', $id)
            ->select('a.id', 'ai.authorized_form_of_name as name')
            ->first();

        abort_unless($repository, 404, 'Repository not found');

        $stats = $this->service->getRepositoryStats($id, $startDate, $endDate);

        return view('ahg-statistics::repository', compact('repository', 'stats', 'startDate', 'endDate'));
    }

    public function admin(Request $request)
    {
        if ($request->isMethod('post')) {
            $settings = [
                'retention_days', 'geoip_enabled', 'geoip_database_path',
                'bot_filtering_enabled', 'anonymize_ip', 'exclude_admin_views',
            ];

            foreach ($settings as $key) {
                $value = $request->get($key, '0');
                $this->service->setConfig($key, $value);
            }

            return redirect()->route('statistics.admin')->with('notice', 'Settings saved');
        }

        $config = [
            'retention_days' => $this->service->getConfig('retention_days', 90),
            'geoip_enabled' => $this->service->getConfig('geoip_enabled', true),
            'geoip_database_path' => $this->service->getConfig('geoip_database_path', '/usr/share/GeoIP/GeoLite2-City.mmdb'),
            'bot_filtering_enabled' => $this->service->getConfig('bot_filtering_enabled', true),
            'anonymize_ip' => $this->service->getConfig('anonymize_ip', true),
            'exclude_admin_views' => $this->service->getConfig('exclude_admin_views', true),
        ];

        $dbStats = [
            'raw_events' => \Illuminate\Support\Facades\DB::table('ahg_usage_event')->count(),
            'daily_aggregates' => \Illuminate\Support\Facades\DB::table('ahg_statistics_daily')->count(),
            'monthly_aggregates' => \Illuminate\Support\Facades\DB::table('ahg_statistics_monthly')->count(),
            'bot_patterns' => \Illuminate\Support\Facades\DB::table('ahg_bot_list')->count(),
        ];

        return view('ahg-statistics::admin', compact('config', 'dbStats'));
    }

    public function bots(Request $request)
    {
        if ($request->isMethod('post')) {
            $action = $request->get('form_action');

            if ($action === 'add') {
                $this->service->addBot($request->only(['name', 'pattern', 'category']));
            } elseif ($action === 'delete') {
                $this->service->deleteBot((int) $request->get('id'));
            }

            return redirect()->route('statistics.bots')->with('notice', 'Bot list updated');
        }

        $bots = $this->service->getBotList();

        return view('ahg-statistics::bots', compact('bots'));
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'views');
        $startDate = $request->get('start', now()->subDays(30)->toDateString());
        $endDate = $request->get('end', now()->toDateString());

        $csv = $this->service->exportToCsv($type, $startDate, $endDate);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"statistics_{$type}_{$startDate}_{$endDate}.csv\"",
        ]);
    }

    /**
     * Handle POST actions for statistics.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');

        if ($action === 'purge') {
            $days = (int) $request->get('days', 90);
            $cutoff = now()->subDays($days)->toDateString();

            \Illuminate\Support\Facades\DB::table('ahg_usage_event')
                ->where('created_at', '<', $cutoff)
                ->delete();

            return redirect()->route('statistics.admin')->with('notice', "Events older than {$days} days purged.");
        }

        if ($action === 'aggregate') {
            $this->service->aggregateStats();

            return redirect()->route('statistics.admin')->with('notice', 'Statistics aggregated.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }
}
