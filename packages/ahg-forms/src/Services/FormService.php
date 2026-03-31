<?php

/**
 * FormService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgForms\Services;

use Illuminate\Support\Facades\DB;

class FormService
{
    public function getTemplates(?string $type = null): \Illuminate\Support\Collection
    {
        $query = DB::table('ahg_form_template')->orderBy('name');

        if ($type) {
            $query->where('form_type', $type);
        }

        return $query->get();
    }

    public function getTemplate(int $id): ?object
    {
        $template = DB::table('ahg_form_template')->where('id', $id)->first();

        if ($template) {
            $template->fields = DB::table('ahg_form_field')
                ->where('template_id', $id)
                ->orderBy('sort_order')
                ->get();
        }

        return $template;
    }

    public function createTemplate(array $data): int
    {
        $insertData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'form_type' => $data['form_type'] ?? 'information_object',
            'config' => isset($data['config']) ? json_encode($data['config']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return DB::table('ahg_form_template')->insertGetId($insertData);
    }

    public function updateTemplate(int $id, array $data): void
    {
        $updateData = ['updated_at' => now()];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        if (isset($data['config'])) {
            $updateData['config'] = is_array($data['config']) ? json_encode($data['config']) : $data['config'];
        }
        if (isset($data['is_default'])) {
            $updateData['is_default'] = $data['is_default'] ? 1 : 0;
        }

        DB::table('ahg_form_template')->where('id', $id)->update($updateData);
    }

    public function deleteTemplate(int $id): void
    {
        DB::table('ahg_form_field')->where('template_id', $id)->delete();
        DB::table('ahg_form_template')->where('id', $id)->delete();
    }

    public function cloneTemplate(int $sourceId, ?string $newName = null): int
    {
        $source = $this->getTemplate($sourceId);

        if (!$source) {
            throw new \Exception('Source template not found');
        }

        $newId = DB::table('ahg_form_template')->insertGetId([
            'name' => $newName ?? $source->name . ' (copy)',
            'description' => $source->description,
            'form_type' => $source->form_type,
            'config' => $source->config,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($source->fields) {
            foreach ($source->fields as $field) {
                DB::table('ahg_form_field')->insert([
                    'template_id' => $newId,
                    'field_name' => $field->field_name,
                    'field_type' => $field->field_type,
                    'label' => $field->label,
                    'atom_field' => $field->atom_field ?? null,
                    'help_text' => $field->help_text ?? null,
                    'placeholder' => $field->placeholder ?? null,
                    'default_value' => $field->default_value ?? null,
                    'sort_order' => $field->sort_order,
                    'is_required' => $field->is_required,
                    'is_readonly' => $field->is_readonly ?? 0,
                    'created_at' => now(),
                ]);
            }
        }

        return $newId;
    }

    public function exportTemplate(int $id): array
    {
        $template = $this->getTemplate($id);

        if (!$template) {
            return [];
        }

        return [
            'name' => $template->name,
            'description' => $template->description,
            'form_type' => $template->form_type,
            'config' => $template->config,
            'fields' => $template->fields ? $template->fields->toArray() : [],
        ];
    }

    public function importTemplate(array $data, ?string $name = null): int
    {
        $id = $this->createTemplate([
            'name' => $name ?? $data['name'] ?? 'Imported Template',
            'description' => $data['description'] ?? null,
            'form_type' => $data['form_type'] ?? 'information_object',
            'config' => $data['config'] ?? null,
        ]);

        foreach ($data['fields'] ?? [] as $i => $field) {
            DB::table('ahg_form_field')->insert([
                'template_id' => $id,
                'field_name' => $field['field_name'] ?? 'field_' . $i,
                'field_type' => $field['field_type'] ?? 'text',
                'label' => $field['label'] ?? 'Field ' . ($i + 1),
                'atom_field' => $field['atom_field'] ?? null,
                'sort_order' => $field['sort_order'] ?? $i,
                'is_required' => $field['is_required'] ?? 0,
                'created_at' => now(),
            ]);
        }

        return $id;
    }

    public function getFields(int $templateId): \Illuminate\Support\Collection
    {
        return DB::table('ahg_form_field')
            ->where('template_id', $templateId)
            ->orderBy('sort_order')
            ->get();
    }

    public function getAssignments(): \Illuminate\Support\Collection
    {
        return DB::table('ahg_form_assignment as a')
            ->leftJoin('ahg_form_template as t', 'a.template_id', '=', 't.id')
            ->select('a.*', 't.name as template_name', 't.form_type')
            ->orderBy('a.priority')
            ->get();
    }

    public function createAssignment(array $data): int
    {
        $data['created_at'] = now();

        return DB::table('ahg_form_assignment')->insertGetId($data);
    }

    public function deleteAssignment(int $id): void
    {
        DB::table('ahg_form_assignment')->where('id', $id)->delete();
    }

    public function getStatistics(): array
    {
        return [
            'templates' => DB::table('ahg_form_template')->count(),
            'fields' => DB::table('ahg_form_field')->count(),
            'assignments' => DB::table('ahg_form_assignment')->count(),
        ];
    }
}
