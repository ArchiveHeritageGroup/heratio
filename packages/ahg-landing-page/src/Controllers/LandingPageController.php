<?php

namespace AhgLandingPage\Controllers;

use AhgLandingPage\Services\LandingPageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LandingPageController extends Controller
{
    protected LandingPageService $service;

    public function __construct()
    {
        $this->service = new LandingPageService();
    }

    // Public
    public function index(Request $request)
    {
        $slug = $request->get('slug');
        $page = $this->service->getPageBySlug($slug);

        abort_unless($page, 404);

        $blocks = $this->service->getPageBlocks($page->id);

        return view('ahg-landing-page::index', compact('page', 'blocks'));
    }

    // Admin
    public function list()
    {
        $pages = $this->service->getAllPages();

        return view('ahg-landing-page::list', compact('pages'));
    }

    public function create(Request $request)
    {
        if ($request->isMethod('post')) {
            $result = $this->service->createPage($request->only([
                'name', 'slug', 'description', 'is_default', 'is_active',
            ]), auth()->id());

            if ($result['success']) {
                return redirect()->route('landing-page.edit', $result['page_id']);
            }

            return back()->withErrors(['error' => $result['error']]);
        }

        return view('ahg-landing-page::create');
    }

    public function edit(int $id)
    {
        $page = $this->service->getPage($id);
        abort_unless($page, 404);

        $blocks = $this->service->getPageBlocks($id, false);
        $blockTypes = $this->service->getBlockTypes();
        $versions = $this->service->getPageVersions($id);

        return view('ahg-landing-page::edit', compact('page', 'blocks', 'blockTypes', 'versions'));
    }

    public function updateSettings(Request $request, int $id)
    {
        $result = $this->service->updatePage($id, $request->only([
            'name', 'slug', 'description', 'is_default', 'is_active',
        ]), auth()->id());

        return response()->json($result);
    }

    public function deletePage(Request $request, int $id)
    {
        $result = $this->service->deletePage($id, auth()->id());

        return response()->json($result);
    }

    public function addBlock(Request $request)
    {
        $config = json_decode($request->get('config', '{}'), true);
        $options = [];

        if ($request->get('parent_block_id')) {
            $options['parent_block_id'] = (int) $request->get('parent_block_id');
            $options['column_slot'] = $request->get('column_slot');
        }

        $result = $this->service->addBlock(
            (int) $request->get('page_id'),
            (int) $request->get('block_type_id'),
            $config,
            auth()->id(),
            $options
        );

        return response()->json($result);
    }

    public function updateBlock(Request $request, int $blockId)
    {
        $data = [];

        if ($request->has('config')) {
            $data['config'] = json_decode($request->get('config'), true);
        }

        foreach (['title', 'css_classes', 'container_type', 'background_color',
            'text_color', 'padding_top', 'padding_bottom', 'col_span'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $request->get($field);
            }
        }

        $result = $this->service->updateBlock($blockId, $data, auth()->id());

        return response()->json($result);
    }

    public function deleteBlock(Request $request, int $blockId)
    {
        $result = $this->service->deleteBlock($blockId, auth()->id());

        return response()->json($result);
    }

    public function reorderBlocks(Request $request)
    {
        $order = json_decode($request->get('order', '[]'), true);
        $result = $this->service->reorderBlocks(
            (int) $request->get('page_id'),
            $order,
            auth()->id()
        );

        return response()->json($result);
    }

    public function duplicateBlock(Request $request, int $blockId)
    {
        $result = $this->service->duplicateBlock($blockId, auth()->id());

        return response()->json($result);
    }

    public function toggleVisibility(Request $request, int $blockId)
    {
        $result = $this->service->toggleBlockVisibility($blockId, auth()->id());

        return response()->json($result);
    }

    // User Dashboard
    public function myDashboard()
    {
        $dashboards = $this->service->getUserDashboards(auth()->id());

        if ($dashboards->isEmpty()) {
            return redirect()->route('landing-page.myDashboard.create');
        }

        $page = $dashboards->first();
        $blocks = $this->service->getPageBlocks($page->id);

        return view('ahg-landing-page::my-dashboard', compact('page', 'blocks'));
    }

    public function myDashboardList()
    {
        $pages = $this->service->getUserDashboards(auth()->id());

        return view('ahg-landing-page::my-dashboard-list', compact('pages'));
    }

    /**
     * Admin dashboard for landing pages.
     */
    public function admin()
    {
        $pages = $this->service->getAllPages();
        $stats = [
            'total' => count($pages),
            'active' => collect($pages)->where('is_active', 1)->count(),
        ];

        return view('ahg-landing-page::admin', compact('pages', 'stats'));
    }

    /**
     * Handle POST actions for landing pages.
     */
    public function post(Request $request)
    {
        $action = $request->get('action');
        $id = (int) $request->get('id');

        if ($action === 'delete' && $id) {
            $this->service->deletePage($id, auth()->id());

            return redirect()->route('landing-page.list')->with('notice', 'Landing page deleted.');
        }

        if ($action === 'toggle_active' && $id) {
            $page = $this->service->getPage($id);
            if ($page) {
                $this->service->updatePage($id, ['is_active' => !$page->is_active], auth()->id());
            }

            return redirect()->route('landing-page.list')->with('notice', 'Page status updated.');
        }

        return redirect()->back()->with('error', 'Invalid action.');
    }

    public function myDashboardCreate(Request $request)
    {
        if ($request->isMethod('post')) {
            $result = $this->service->createPage(
                $request->only(['name', 'slug', 'description']) + ['is_active' => 1, 'page_type' => 'dashboard'],
                auth()->id()
            );

            if ($result['success']) {
                return redirect()->route('landing-page.myDashboard');
            }

            return back()->withErrors(['error' => $result['error']]);
        }

        $hasDashboards = $this->service->getUserDashboards(auth()->id())->isNotEmpty();

        return view('ahg-landing-page::my-dashboard-create', compact('hasDashboards'));
    }
}
