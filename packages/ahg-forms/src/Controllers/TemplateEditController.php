<?php

/**
 * TemplateEditController - Service for Heratio
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

namespace AhgForms\Controllers;

use AhgForms\Services\FormService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TemplateEditController extends Controller
{
    private FormService $forms;

    public function __construct(FormService $forms)
    {
        $this->forms = $forms;
    }

    /**
     * GET /forms/edit/{entityType}/{entityId}/{templateId?}
     * Render the chosen (or auto-resolved) template against an existing entity.
     */
    public function edit(string $entityType, int $entityId, ?int $templateId = null)
    {
        $context = $this->buildContext($entityType, $entityId);

        $template = $templateId
            ? $this->forms->getTemplate($templateId)
            : $this->forms->resolveTemplate($entityType, $context);

        if (!$template) {
            return redirect($this->cancelUrl($entityType, $entityId))
                ->with('error', 'No form template configured for this entity.');
        }

        if ($template->form_type !== $entityType) {
            abort(400, 'Template form_type does not match entity type.');
        }

        $values = $this->loadCurrentValues($template, $entityType, $entityId);

        return view('ahg-forms::template-edit', [
            'template'   => $template,
            'entityType' => $entityType,
            'entityId'   => $entityId,
            'values'     => $values,
            'action'     => route('forms.template.submit', ['entityType' => $entityType, 'entityId' => $entityId, 'templateId' => $template->id]),
            'cancelUrl'  => $this->cancelUrl($entityType, $entityId),
        ]);
    }

    /**
     * POST /forms/edit/{entityType}/{entityId}/submit/{templateId}
     * Apply template mappings → write values back to the entity's tables.
     */
    public function submit(Request $request, string $entityType, int $entityId, int $templateId)
    {
        $template = $this->forms->getTemplate($templateId);
        if (!$template || $template->form_type !== $entityType) {
            abort(404);
        }

        $submitted = (array) $request->input('fields', []);
        $mappings = $this->forms->getMappingsForTemplate($templateId);

        // Bucket writes by table; one UPDATE per (table, where-key)
        $bucket = [];
        foreach (($template->fields ?? []) as $field) {
            $fieldName = $field->field_name;
            $raw = $submitted[$fieldName] ?? null;
            $value = $this->normaliseValue($raw, $field->field_type);

            $maps = $mappings[(int)$field->id] ?? [];
            if (empty($maps)) {
                continue; // unmapped → ignore (still saved to submission log below)
            }

            foreach ($maps as $m) {
                $value = $this->applyTransformation($value, $m->transformation, $m->transformation_config);
                $key = $m->target_table . '|' . ($m->is_i18n ? ($m->culture ?: 'en') : '');
                $bucket[$key]['table'] = $m->target_table;
                $bucket[$key]['is_i18n'] = (int)$m->is_i18n;
                $bucket[$key]['culture'] = $m->culture ?: 'en';
                $bucket[$key]['cols'][$m->target_column] = $value;
            }
        }

        DB::beginTransaction();
        try {
            foreach ($bucket as $b) {
                $this->writeBucket($entityType, $entityId, $b);
            }

            DB::table('ahg_form_submission_log')->insert([
                'template_id'   => $templateId,
                'entity_type'   => $entityType,
                'entity_id'     => $entityId,
                'submitted_by'  => auth()->id(),
                'submitted_at'  => now(),
                'payload_json'  => json_encode($submitted),
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('TemplateEditController submit failed', ['err' => $e->getMessage()]);
            return back()->with('error', 'Save failed: ' . $e->getMessage())->withInput();
        }

        return redirect($this->showUrl($entityType, $entityId))
            ->with('success', "Saved using template '{$template->name}'.");
    }

    // -- helpers ----------------------------------------------------------------

    private function buildContext(string $entityType, int $entityId): array
    {
        if ($entityType === 'information_object') {
            $row = DB::table('information_object')
                ->where('id', $entityId)
                ->select('repository_id', 'level_of_description_id', 'parent_id')
                ->first();
            if (!$row) return [];
            // Walk up to find the top-level collection
            $collectionId = null;
            $parent = $row->parent_id;
            $depth = 0;
            while ($parent && $depth < 10) {
                $next = DB::table('information_object')->where('id', $parent)->select('id', 'parent_id')->first();
                if (!$next) break;
                if (!$next->parent_id || (int)$next->parent_id === 1) {
                    $collectionId = (int)$next->id;
                    break;
                }
                $parent = $next->parent_id;
                $depth++;
            }
            return [
                'repository_id'           => $row->repository_id ? (int)$row->repository_id : null,
                'level_of_description_id' => $row->level_of_description_id ? (int)$row->level_of_description_id : null,
                'collection_id'           => $collectionId,
            ];
        }
        return [];
    }

    private function loadCurrentValues(object $template, string $entityType, int $entityId): array
    {
        $values = [];
        $mappings = $this->forms->getMappingsForTemplate((int)$template->id);

        // Pre-load distinct tables once, then read columns from cache
        $cache = [];
        foreach (($template->fields ?? []) as $field) {
            $maps = $mappings[(int)$field->id] ?? [];
            foreach ($maps as $m) {
                $key = $m->target_table . '|' . ($m->is_i18n ? ($m->culture ?: 'en') : '');
                if (!isset($cache[$key])) {
                    $cache[$key] = $this->readRow($entityType, $entityId, $m->target_table, (int)$m->is_i18n, $m->culture ?: 'en');
                }
                $row = $cache[$key];
                if ($row && property_exists($row, $m->target_column)) {
                    $values[$field->field_name] = $row->{$m->target_column};
                    break; // first mapping that produces a value wins
                }
            }
        }
        return $values;
    }

    private function readRow(string $entityType, int $entityId, string $table, int $isI18n, string $culture): ?object
    {
        $idCol = $this->idColumn($entityType, $table);
        $q = DB::table($table)->where($idCol, $entityId);
        if ($isI18n) {
            $q->where('culture', $culture);
        }
        return $q->first();
    }

    private function writeBucket(string $entityType, int $entityId, array $b): void
    {
        $table = $b['table'];
        $idCol = $this->idColumn($entityType, $table);

        if ($b['is_i18n']) {
            $exists = DB::table($table)
                ->where($idCol, $entityId)
                ->where('culture', $b['culture'])
                ->exists();
            if ($exists) {
                DB::table($table)
                    ->where($idCol, $entityId)
                    ->where('culture', $b['culture'])
                    ->update($b['cols']);
            } else {
                DB::table($table)->insert(array_merge(
                    [$idCol => $entityId, 'culture' => $b['culture']],
                    $b['cols']
                ));
            }
        } else {
            DB::table($table)->where($idCol, $entityId)->update($b['cols']);
        }
    }

    private function idColumn(string $entityType, string $table): string
    {
        // information_object_i18n / actor_i18n / etc. all use 'id' as the FK back to the entity row's id.
        // Most extension tables (museum_metadata, ric_*) use object_id.
        if (in_array($table, ['information_object', 'actor', 'repository', 'accession'], true)) {
            return 'id';
        }
        if (str_ends_with($table, '_i18n')) {
            return 'id';
        }
        // Best guess — could be made data-driven later via ahg_form_field_mapping.target_id_column if needed.
        $candidates = ['object_id', 'information_object_id', 'actor_id', 'entity_id'];
        try {
            $cols = DB::getSchemaBuilder()->getColumnListing($table);
            foreach ($candidates as $c) {
                if (in_array($c, $cols, true)) return $c;
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return 'object_id';
    }

    private function normaliseValue($raw, string $type)
    {
        if (is_null($raw)) return null;
        return match ($type) {
            'number'   => is_numeric($raw) ? $raw + 0 : null,
            'checkbox' => ((string)$raw === '1' || $raw === true) ? 1 : 0,
            'date'     => $raw ?: null,
            default    => is_string($raw) ? trim($raw) : $raw,
        };
    }

    private function applyTransformation($value, ?string $transformation, $config)
    {
        if (!$transformation) return $value;
        $cfg = is_string($config) ? json_decode($config, true) : ($config ?? []);
        return match ($transformation) {
            'upper'      => is_string($value) ? mb_strtoupper($value) : $value,
            'lower'      => is_string($value) ? mb_strtolower($value) : $value,
            'trim'       => is_string($value) ? trim($value) : $value,
            'json_array' => is_string($value) ? json_decode($value, true) : $value,
            'prefix'     => is_string($value) && !empty($cfg['prefix']) ? $cfg['prefix'] . $value : $value,
            default      => $value,
        };
    }

    private function showUrl(string $entityType, int $entityId): string
    {
        if ($entityType === 'information_object') {
            $slug = DB::table('slug')->where('object_id', $entityId)->value('slug');
            return $slug ? url('/' . $slug) : url('/informationobject/' . $entityId);
        }
        return url('/');
    }

    private function cancelUrl(string $entityType, int $entityId): string
    {
        if ($entityType === 'information_object') {
            $slug = DB::table('slug')->where('object_id', $entityId)->value('slug');
            return $slug ? url('/informationobject/' . $slug . '/edit') : url('/');
        }
        return url('/');
    }
}
