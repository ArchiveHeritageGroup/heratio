<?php

namespace AhgExport\Controllers;

use AhgExport\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService
    ) {}

    /**
     * Export dashboard — index page showing all export options.
     */
    public function index()
    {
        $repositories = $this->exportService->getRepositories();
        $formats = $this->exportService->getExportFormats();

        return view('ahg-export::index', compact('repositories', 'formats'));
    }

    /**
     * CSV export page — information object CSV export.
     */
    public function csv(Request $request)
    {
        $repositories = $this->exportService->getRepositories();
        $ioCount = $this->exportService->getInformationObjectCount();

        return view('ahg-export::csv', compact('repositories', 'ioCount'));
    }

    /**
     * EAD export page.
     */
    public function ead(Request $request)
    {
        $repositories = $this->exportService->getRepositories();

        return view('ahg-export::ead', compact('repositories'));
    }

    /**
     * Archival description export page.
     */
    public function archival(Request $request)
    {
        $repositories = $this->exportService->getRepositories();

        return view('ahg-export::archival', compact('repositories'));
    }

    /**
     * Authority record export page.
     */
    public function authority(Request $request)
    {
        $authorityCount = $this->exportService->getAuthorityCount();

        return view('ahg-export::authority', compact('authorityCount'));
    }

    /**
     * Repository export page.
     */
    public function repository(Request $request)
    {
        $repositoryCount = $this->exportService->getRepositoryCount();
        $repositories = $this->exportService->getRepositories();

        return view('ahg-export::repository', compact('repositoryCount', 'repositories'));
    }

    /**
     * Accession CSV export page.
     */
    public function accessionCsv(Request $request)
    {
        $repositories = $this->exportService->getRepositories();
        $accessionCount = $this->exportService->getAccessionCount();

        return view('ahg-export::accession-csv', compact('repositories', 'accessionCount'));
    }
}
