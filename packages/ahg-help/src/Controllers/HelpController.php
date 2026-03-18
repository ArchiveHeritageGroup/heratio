<?php

namespace AhgHelp\Controllers;

use AhgHelp\Services\HelpArticleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HelpController extends Controller
{
    protected array $categoryDescriptions = [
        'Admin & Settings' => 'System settings, backup, encryption, statistics, and workflow',
        'AI & Automation' => 'AI tools, NER, duplicate detection, semantic and fuzzy search',
        'Browse & Search' => 'GLAM browse, advanced search, term and taxonomy browsing',
        'Collection Mgmt' => 'Condition, contacts, contracts, donors, loans, provenance, vendors',
        'Compliance' => 'Audit trail, embargo, privacy, security, preservation',
        'Exhibitions' => 'Exhibition management and landing page builder',
        'GLAM Sectors' => 'Gallery, Library, Museum, DAM, and GLAM Browse modules',
        'Heritage Accounting' => 'Heritage asset accounting (GRAP 103 / IPSAS 45)',
        'Import/Export' => 'Data ingest, migration, metadata export, portable export',
        'Integration' => 'DOI, REST API, GraphQL, Federation, IIIF, RiC',
        'Labels & Forms' => 'Barcodes, label printing, and forms builder',
        'Marketplace' => 'Heritage marketplace and vendor management',
        'Public Access' => 'Access requests, cart, favorites, feedback, heritage platform',
        'Reference' => 'Functions, workflows, and roadmap documents',
        'Research' => 'Researcher portal and knowledge platform',
        'Rights' => 'Rights management, extended rights, ICIP',
        'User Guide' => 'Step-by-step guides for common tasks',
        'User Manual' => 'Comprehensive user manuals',
        'Viewers & Media' => '3D viewer, audio player, IIIF, Mirador, OpenSeadragon, PDF',
    ];

    protected array $categoryIcons = [
        'Admin & Settings' => 'bi-gear',
        'AI & Automation' => 'bi-robot',
        'Browse & Search' => 'bi-search',
        'Collection Mgmt' => 'bi-archive',
        'Compliance' => 'bi-shield-check',
        'Exhibitions' => 'bi-easel',
        'GLAM Sectors' => 'bi-building',
        'Heritage Accounting' => 'bi-calculator',
        'Import/Export' => 'bi-arrow-left-right',
        'Integration' => 'bi-plug',
        'Labels & Forms' => 'bi-tag',
        'Marketplace' => 'bi-shop',
        'Public Access' => 'bi-people',
        'Reference' => 'bi-file-text',
        'Research' => 'bi-mortarboard',
        'Rights' => 'bi-lock',
        'User Guide' => 'bi-book',
        'User Manual' => 'bi-journal-text',
        'Viewers & Media' => 'bi-play-circle',
    ];

    public function index()
    {
        $categories = HelpArticleService::getCategories();
        $recentArticles = HelpArticleService::getRecentlyUpdated(5);

        return view('ahg-help::index', [
            'categories' => $categories,
            'recentArticles' => $recentArticles,
            'categoryDescriptions' => $this->categoryDescriptions,
            'categoryIcons' => $this->categoryIcons,
        ]);
    }

    public function category(string $category)
    {
        $category = urldecode($category);
        $articles = HelpArticleService::getByCategory($category);

        if (empty($articles)) {
            return redirect()->route('help.index');
        }

        $grouped = [];
        foreach ($articles as $article) {
            $sub = $article['subcategory'] ?: 'General';
            $grouped[$sub][] = $article;
        }
        ksort($grouped);

        $categories = HelpArticleService::getCategories();

        return view('ahg-help::category', [
            'category' => $category,
            'grouped' => $grouped,
            'categories' => $categories,
        ]);
    }

    public function article(string $slug)
    {
        $article = HelpArticleService::getBySlug($slug);
        if (!$article) {
            abort(404);
        }

        $toc = [];
        if (!empty($article['toc_json'])) {
            $toc = json_decode($article['toc_json'], true) ?: [];
        }

        $adjacent = HelpArticleService::getAdjacentArticles($article['id'], $article['category']);
        $categories = HelpArticleService::getCategories();

        return view('ahg-help::article', [
            'article' => $article,
            'toc' => $toc,
            'prevArticle' => $adjacent['prev'],
            'nextArticle' => $adjacent['next'],
            'categories' => $categories,
        ]);
    }

    public function search(Request $request)
    {
        $query = trim($request->get('q', ''));
        $articleResults = [];
        $sectionResults = [];

        if (mb_strlen($query) >= 2) {
            $articleResults = HelpArticleService::search($query, 20);
            $sectionResults = HelpArticleService::searchSections($query, 20);
        }

        $categories = HelpArticleService::getCategories();

        return view('ahg-help::search', [
            'query' => $query,
            'articleResults' => $articleResults,
            'sectionResults' => $sectionResults,
            'categories' => $categories,
        ]);
    }
}
