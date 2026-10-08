<?php

/**
 * BulkEditController - the bulk edit screens (heratio#1542).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */

namespace AhgInformationObjectManage\Controllers;

use AhgInformationObjectManage\Jobs\BulkEditJob;
use AhgInformationObjectManage\Services\BulkEditService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BulkEditController extends Controller
{
    public function __construct(private BulkEditService $service) {}

    /** The form, and with POST preview=1 the list of changes it would make. */
    public function index(Request $request)
    {
        $data = ['preview' => null, 'input' => $request->all()] + $this->options();
        if ($request->isMethod('post')) {
            [$kind, $params, $scope] = $this->read($request);
            try {
                $data['preview'] = $this->service->preview($kind, $params, $scope);
            } catch (\InvalidArgumentException $e) {
                return back()->withInput()->with('error', $e->getMessage());
            }
        }
        $data['batches'] = DB::table('ahg_bulk_edit')->orderByDesc('id')->limit(20)->get();

        return view('ahg-io-manage::bulk-edit.index', $data);
    }

    /** Queue the batch shown in the preview. */
    public function run(Request $request)
    {
        [$kind, $params, $scope] = $this->read($request);
        try {
            $id = $this->service->queue($kind, $params, $scope, Auth::id());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        BulkEditJob::dispatch($id);

        return redirect()->route('bulk-edit.show', $id)->with('success', __('Bulk edit queued.'));
    }

    public function show(int $id)
    {
        $batch = DB::table('ahg_bulk_edit')->where('id', $id)->first() ?? abort(404);
        $changes = DB::table('ahg_bulk_edit_change as c')->leftJoin('slug', 'slug.object_id', '=', 'c.object_id')
            ->where('c.bulk_edit_id', $id)->orderBy('c.id')->limit(500)->get(['c.*', 'slug.slug']);

        return view('ahg-io-manage::bulk-edit.show', compact('batch', 'changes'));
    }

    /** Progress, for the page to poll while a batch runs. */
    public function status(int $id)
    {
        $batch = DB::table('ahg_bulk_edit')->where('id', $id)->first(['status', 'total', 'done', 'changed', 'error']) ?? abort(404);

        return response()->json($batch);
    }

    public function undo(int $id)
    {
        try {
            $r = $this->service->undo($id, Auth::id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bulk-edit.show', $id)->with('success',
            __(':restored value(s) restored; :skipped skipped because they were edited after the batch.', $r));
    }

    /** @return array{0: string, 1: array, 2: array} */
    private function read(Request $request): array
    {
        $v = $request->validate([
            'kind' => ['required', Rule::in(BulkEditService::KINDS)],
            'scope_slug' => 'nullable|string|max:255',
            'repository_id' => 'nullable|integer|exists:repository,id',
            'column' => ['nullable', Rule::in(array_keys(BulkEditService::TEXT_COLUMNS))],
            'pattern' => 'nullable|string|max:1000',
            'replacement' => 'nullable|string|max:1000',
            'case_sensitive' => 'nullable|boolean',
            'field' => ['nullable', Rule::in(array_keys(BulkEditService::SET_FIELDS))],
            'value' => 'nullable|integer',
            'template' => 'nullable|string|max:1024',
            'mode' => ['nullable', Rule::in(['natural', 'numeric'])],
            'culture' => 'nullable|string|max:16',
        ]);

        $scope = [];
        if (! empty($v['scope_slug'])) {
            $scope['ancestor_id'] = (int) DB::table('slug')->join('information_object', 'information_object.id', '=', 'slug.object_id')
                ->where('slug.slug', $v['scope_slug'])->value('slug.object_id')
                ?: throw \Illuminate\Validation\ValidationException::withMessages(['scope_slug' => __('No archival description has that slug.')]);
        } elseif (! empty($v['repository_id'])) {
            $scope['repository_id'] = (int) $v['repository_id'];
        } else {
            throw \Illuminate\Validation\ValidationException::withMessages(['scope_slug' => __('Choose a fonds or series, or a repository.')]);
        }
        if ($v['kind'] === 'sort_children' && empty($scope['ancestor_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['scope_slug' => __('Sorting needs the record whose children are sorted.')]);
        }
        if (($v['field'] ?? null) && $v['kind'] === 'set_field' && ($v['value'] ?? null) !== null) {
            $ok = match ($v['field']) {
                'repository_id' => DB::table('repository')->where('id', $v['value'])->exists(),
                'level_of_description_id' => DB::table('term')->where('id', $v['value'])->where('taxonomy_id', 34)->exists(),
                'publication_status_id' => DB::table('term')->where('id', $v['value'])->where('taxonomy_id', 60)->exists(),
            };
            $ok ?: throw \Illuminate\Validation\ValidationException::withMessages(['value' => __('That value does not exist for this field.')]);
        }

        $params = array_intersect_key($v, array_flip(['column', 'pattern', 'replacement', 'case_sensitive', 'field', 'value', 'template', 'mode', 'culture']));
        $params['culture'] = $params['culture'] ?? app()->getLocale();
        $params['case_sensitive'] = ! empty($params['case_sensitive']);
        if ($v['kind'] === 'set_field') {
            $params['value'] = isset($v['value']) ? (string) $v['value'] : '';
        }

        return [$v['kind'], $params, $scope];
    }

    private function options(): array
    {
        $culture = app()->getLocale();
        $terms = fn (int $taxonomy) => DB::table('term')->join('term_i18n', 'term_i18n.id', '=', 'term.id')
            ->where('term.taxonomy_id', $taxonomy)->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')->pluck('term_i18n.name', 'term.id');

        return [
            'columns' => BulkEditService::TEXT_COLUMNS,
            'setFields' => BulkEditService::SET_FIELDS,
            'repositories' => DB::table('repository')->join('actor_i18n', 'actor_i18n.id', '=', 'repository.id')
                ->where('actor_i18n.culture', $culture)->orderBy('actor_i18n.authorized_form_of_name')
                ->pluck('actor_i18n.authorized_form_of_name', 'repository.id'),
            'levels' => $terms(34),
            'statuses' => $terms(60),
        ];
    }
}
