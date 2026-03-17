<?php

namespace AhgReports\Controllers;

use AhgReports\Services\ReportService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private ReportService $service;

    public function __construct(ReportService $service)
    {
        $this->service = $service;
    }

    public function dashboard()
    {
        $stats = $this->service->getReportStats();
        return view('ahg-reports::dashboard', compact('stats'));
    }

    public function accessions(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportAccessions($params);
        $cultures = $this->service->getAvailableCultures();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Identifier', 'Title', 'Scope', 'Created', 'Updated'],
                'accession-report.csv'
            );
        }

        return view('ahg-reports::report-accessions', array_merge($data, [
            'params' => $params, 'cultures' => $cultures,
        ]));
    }

    public function descriptions(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'level', 'publicationStatus', 'limit', 'page']);
        $data = $this->service->reportDescriptions($params);
        $levels = $this->service->getLevelsOfDescription();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Identifier', 'Title', 'Level', 'Status', 'Created', 'Updated'],
                'description-report.csv'
            );
        }

        return view('ahg-reports::report-descriptions', array_merge($data, [
            'params' => $params, 'levels' => $levels,
        ]));
    }

    public function authorities(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'entityType', 'limit', 'page']);
        $data = $this->service->reportAuthorities($params);
        $entityTypes = $this->service->getEntityTypes();

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Entity Type', 'Dates', 'Created', 'Updated'],
                'authority-report.csv'
            );
        }

        return view('ahg-reports::report-authorities', array_merge($data, [
            'params' => $params, 'entityTypes' => $entityTypes,
        ]));
    }

    public function donors(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportDonors($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Email', 'Phone', 'City', 'Created', 'Updated'],
                'donor-report.csv'
            );
        }

        return view('ahg-reports::report-donors', array_merge($data, ['params' => $params]));
    }

    public function repositories(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportRepositories($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Identifier', 'Name', 'Holdings', 'Created', 'Updated'],
                'repository-report.csv'
            );
        }

        return view('ahg-reports::report-repositories', array_merge($data, ['params' => $params]));
    }

    public function storage(Request $request)
    {
        $params = $request->only(['culture', 'dateStart', 'dateEnd', 'dateOf', 'limit', 'page']);
        $data = $this->service->reportPhysicalStorage($params);

        if ($request->query('export') === 'csv') {
            return $this->service->exportCsv(
                $data['results']->toArray(),
                ['ID', 'Name', 'Type', 'Location', 'Created', 'Updated'],
                'physical-storage-report.csv'
            );
        }

        return view('ahg-reports::report-storage', array_merge($data, ['params' => $params]));
    }

    public function activity(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'actionUser', 'userAction', 'limit', 'page']);
        $data = $this->service->reportUserActivity($params);
        $users = $this->service->getAuditUsers();

        return view('ahg-reports::report-activity', array_merge($data, [
            'params' => $params, 'users' => $users,
        ]));
    }

    public function recent(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'className', 'limit', 'page']);
        $data = $this->service->reportUpdates($params);

        return view('ahg-reports::report-recent', array_merge($data, ['params' => $params]));
    }

    public function taxonomy(Request $request)
    {
        $params = $request->only(['dateStart', 'dateEnd', 'sort', 'limit', 'page']);
        $data = $this->service->reportTaxonomies($params);

        return view('ahg-reports::report-taxonomy', array_merge($data, ['params' => $params]));
    }
}
