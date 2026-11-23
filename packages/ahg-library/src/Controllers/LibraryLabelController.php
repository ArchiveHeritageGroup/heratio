<?php

/**
 * LibraryLabelController - label printing and stationery templates (#1476 item 2).
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibraryLabelService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryLabelController extends Controller
{
    public function __construct(private LibraryLabelService $labels)
    {
    }

    public function index()
    {
        $ready = $this->labels->available();

        return view('ahg-library::labels.index', [
            'ready' => $ready,
            'templates' => $ready ? $this->labels->templates() : [],
            'formats' => $ready ? $this->labels->formats() : [],
            'maxLabels' => LibraryLabelService::MAX_LABELS,
        ]);
    }

    public function preview(Request $request)
    {
        abort_unless($this->labels->available(), 404);

        $data = $request->validate([
            'template_id' => ['required', 'integer', Rule::exists('library_label_template', 'id')->where('is_active', 1)],
            'format' => ['required', 'string', Rule::in(array_keys($this->labels->formats()))],
            'identifiers' => ['nullable', 'string', 'max:100000'],
            'accession' => ['nullable', 'string', 'max:51', function ($attribute, $value, $fail) {
                if (LibraryLabelService::isBareWildcard((string) $value)) {
                    $fail(__('Enter the start of an accession number before the *.'));
                }
            }],
            'skip' => ['nullable', 'integer', 'min:0'],
            'nudge_x' => ['nullable', 'numeric', 'between:-10,10'],
            'nudge_y' => ['nullable', 'numeric', 'between:-10,10'],
            'outlines' => ['nullable', 'boolean'],
        ]);

        $template = $this->labels->template((int) $data['template_id']);
        $skip = (int) ($data['skip'] ?? 0);
        $perPage = (int) $template->columns_across * (int) $template->rows_down;
        if ($skip >= $perPage) {
            throw ValidationException::withMessages(['skip' => __('This sheet only has :n positions.', ['n' => $perPage])]);
        }

        $nudgeX = (float) ($data['nudge_x'] ?? 0);
        $nudgeY = (float) ($data['nudge_y'] ?? 0);
        if ($errors = $this->labels->nudgeErrors($template, $nudgeX, $nudgeY)) {
            throw ValidationException::withMessages($errors);
        }

        $identifiers = preg_split('/[\r\n,;\t]+/', (string) ($data['identifiers'] ?? '')) ?: [];
        if (trim(implode('', $identifiers)) === '' && trim((string) ($data['accession'] ?? '')) === '') {
            throw ValidationException::withMessages(['identifiers' => __('Scan or paste at least one barcode, or enter an accession.')]);
        }

        $resolved = $this->labels->resolveCopies($identifiers, $data['accession'] ?? null);
        $sheets = $this->labels->sheets(
            $resolved['copies'], $data['format'], $template, $skip, $nudgeX, $nudgeY
        );

        return view('ahg-library::labels.print', [
            'template' => $template,
            'sheets' => $sheets,
            'resolved' => $resolved,
            'outlines' => (bool) ($data['outlines'] ?? false),
            'maxLabels' => LibraryLabelService::MAX_LABELS,
        ]);
    }

    public function templates()
    {
        $ready = $this->labels->available();

        return view('ahg-library::labels.templates', [
            'ready' => $ready,
            'templates' => $ready ? $this->labels->templates(false) : [],
        ]);
    }

    public function createTemplate(Request $request)
    {
        abort_unless($this->labels->available(), 404);
        $from = $request->integer('from');

        return view('ahg-library::labels.template-form', [
            'template' => null,
            'values' => $from ? $this->labels->template($from) : null,
        ]);
    }

    public function editTemplate(int $id)
    {
        abort_unless($this->labels->available(), 404);
        $template = $this->labels->template($id);
        abort_if(! $template || $template->is_builtin, 404);

        return view('ahg-library::labels.template-form', ['template' => $template, 'values' => $template]);
    }

    public function storeTemplate(Request $request)
    {
        abort_unless($this->labels->available(), 404);
        $this->labels->saveTemplate($this->validated($request), null, Auth::id());

        return redirect()->route('library.labels.templates')->with('success', __('Template saved.'));
    }

    public function updateTemplate(Request $request, int $id)
    {
        abort_unless($this->labels->available(), 404);
        $template = $this->labels->template($id);
        abort_if(! $template || $template->is_builtin, 404);
        $this->labels->saveTemplate($this->validated($request), $id, Auth::id());

        return redirect()->route('library.labels.templates')->with('success', __('Template saved.'));
    }

    public function deleteTemplate(int $id)
    {
        abort_unless($this->labels->available(), 404);
        abort_unless($this->labels->deleteTemplate($id), 404);

        return redirect()->route('library.labels.templates')->with('success', __('Template deleted.'));
    }

    private function validated(Request $request): array
    {
        $mm = fn (int|float $min, int|float $max) => ['required', 'numeric', 'min:'.$min, 'max:'.$max];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'page_width_mm' => $mm(50, 1000),
            'page_height_mm' => $mm(20, 1000),
            'margin_top_mm' => $mm(0, 500),
            'margin_left_mm' => $mm(0, 500),
            'label_width_mm' => $mm(10, 500),
            'label_height_mm' => $mm(5, 500),
            'pitch_x_mm' => $mm(10, 500),
            'pitch_y_mm' => $mm(5, 500),
            'columns_across' => ['required', 'integer', 'min:1', 'max:30'],
            'rows_down' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        if ($errors = $this->labels->geometryErrors($data)) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
