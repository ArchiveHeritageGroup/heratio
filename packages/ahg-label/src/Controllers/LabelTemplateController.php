<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgLabel\Controllers;

use AhgLabel\Models\LabelTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin CRUD for configurable label / barcode sheet templates (#1281).
 */
class LabelTemplateController extends Controller
{
    public function index(): View
    {
        return view('label::templates.index', [
            'templates' => LabelTemplate::query()->orderByDesc('is_default')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('label::templates.edit', ['template' => null] + $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $template = LabelTemplate::create($data);
        if ($template->is_default) {
            $this->makeSoleDefault((int) $template->id);
        }

        return redirect()->route('ahglabel.templates.index')->with('success', 'Label template created.');
    }

    public function edit(int $id): View|RedirectResponse
    {
        $template = LabelTemplate::find($id);
        if (! $template) {
            return redirect()->route('ahglabel.templates.index')->with('error', 'Template not found.');
        }

        return view('label::templates.edit', ['template' => $template] + $this->formOptions());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = LabelTemplate::find($id);
        if (! $template) {
            return redirect()->route('ahglabel.templates.index')->with('error', 'Template not found.');
        }
        $template->update($this->validated($request));
        if ($template->is_default) {
            $this->makeSoleDefault((int) $template->id);
        }

        return redirect()->route('ahglabel.templates.index')->with('success', 'Label template updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        LabelTemplate::where('id', $id)->delete();

        return redirect()->route('ahglabel.templates.index')->with('success', 'Label template deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'page_size' => 'required|in:'.implode(',', LabelTemplate::PAGE_SIZES),
            'columns' => 'required|integer|min:1|max:20',
            'rows' => 'required|integer|min:1|max:30',
            'label_width_mm' => 'required|numeric|min:1|max:300',
            'label_height_mm' => 'required|numeric|min:1|max:300',
            'margin_mm' => 'required|numeric|min:0|max:50',
            'gutter_mm' => 'required|numeric|min:0|max:50',
            'font_size_pt' => 'required|integer|min:4|max:48',
            'barcode_source' => 'required|in:'.implode(',', LabelTemplate::BARCODE_SOURCES),
            'qr_target' => 'required|in:'.implode(',', LabelTemplate::QR_TARGETS),
        ]);

        foreach (['show_title', 'show_identifier', 'show_repository', 'show_barcode', 'show_qr', 'is_default'] as $flag) {
            $data[$flag] = (int) $request->boolean($flag);
        }

        return $data;
    }

    /** Ensure only one template carries is_default. */
    private function makeSoleDefault(int $id): void
    {
        DB::table('label_template')->where('id', '!=', $id)->update(['is_default' => 0]);
    }

    private function formOptions(): array
    {
        return [
            'pageSizes' => LabelTemplate::PAGE_SIZES,
            'barcodeSources' => LabelTemplate::BARCODE_SOURCES,
            'qrTargets' => LabelTemplate::QR_TARGETS,
        ];
    }
}
