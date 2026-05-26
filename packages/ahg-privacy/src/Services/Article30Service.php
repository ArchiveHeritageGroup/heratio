<?php

/**
 * Article30Service - GDPR Article 30 register CRUD and regulator-ready export.
 *
 * Issue #669 Phase 1. Backs both the /admin/privacy/article-30 admin UI and
 * the privacy:article-30-export CLI.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * This file is part of Heratio. Licensed AGPL-3.0-or-later.
 */

declare(strict_types=1);

namespace AhgPrivacy\Services;

use AhgPrivacy\Models\ProcessingActivity;
use Illuminate\Support\Collection;

class Article30Service
{
    /** @return Collection<int,ProcessingActivity> */
    public function listActive(): Collection
    {
        return ProcessingActivity::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int,ProcessingActivity> */
    public function listAll(): Collection
    {
        return ProcessingActivity::query()->orderBy('name')->get();
    }

    public function find(int $id): ?ProcessingActivity
    {
        return ProcessingActivity::query()->find($id);
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): ProcessingActivity
    {
        return ProcessingActivity::query()->create($this->sanitize($data));
    }

    /** @param array<string,mixed> $data */
    public function update(ProcessingActivity $activity, array $data): ProcessingActivity
    {
        $activity->fill($this->sanitize($data));
        $activity->save();
        return $activity;
    }

    public function delete(ProcessingActivity $activity): void
    {
        $activity->is_active = false;
        $activity->save();
    }

    /**
     * Build a regulator-ready snapshot of the Article 30 register.
     *
     * @return array{generated_at:string,controller:?string,activity_count:int,activities:array<int,array<string,mixed>>}
     */
    public function buildSnapshot(): array
    {
        $activities = $this->listAll()->map(function (ProcessingActivity $a): array {
            return [
                'id'                     => (int) $a->id,
                'name'                   => (string) $a->name,
                'purpose'                => (string) $a->purpose,
                'lawful_basis'           => (string) $a->lawful_basis,
                'categories_of_data'     => $a->categories_of_data ?? [],
                'categories_of_subjects' => $a->categories_of_subjects ?? [],
                'recipients'             => $a->recipients ?? [],
                'retention_period'       => (string) ($a->retention_period ?? ''),
                'security_measures'      => (string) ($a->security_measures ?? ''),
                'transfers_outside_eea'  => (bool) $a->transfers_outside_eea,
                'safeguards'             => (string) ($a->safeguards ?? ''),
                'dpo_contact'            => (string) ($a->dpo_contact ?? ''),
                'is_active'              => (bool) $a->is_active,
                'updated_at'             => optional($a->updated_at)->format('Y-m-d\TH:i:s\Z'),
            ];
        })->all();

        return [
            'generated_at'   => gmdate('Y-m-d\TH:i:s\Z'),
            'controller'     => $this->resolveControllerName(),
            'activity_count' => count($activities),
            'activities'     => $activities,
        ];
    }

    public function exportJson(): string
    {
        return (string) json_encode($this->buildSnapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function exportCsv(): string
    {
        $snapshot = $this->buildSnapshot();
        $fh = fopen('php://temp', 'w+');
        if ($fh === false) {
            return '';
        }
        fputcsv($fh, [
            'id', 'name', 'purpose', 'lawful_basis', 'categories_of_data',
            'categories_of_subjects', 'recipients', 'retention_period',
            'security_measures', 'transfers_outside_eea', 'safeguards',
            'dpo_contact', 'is_active', 'updated_at',
        ]);
        foreach ($snapshot['activities'] as $a) {
            fputcsv($fh, [
                $a['id'],
                $a['name'],
                $a['purpose'],
                $a['lawful_basis'],
                implode('; ', $a['categories_of_data']),
                implode('; ', $a['categories_of_subjects']),
                implode('; ', $a['recipients']),
                $a['retention_period'],
                $a['security_measures'],
                $a['transfers_outside_eea'] ? 'yes' : 'no',
                $a['safeguards'],
                $a['dpo_contact'],
                $a['is_active'] ? 'yes' : 'no',
                $a['updated_at'],
            ]);
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);
        return $csv;
    }

    public function exportMarkdown(): string
    {
        $snapshot = $this->buildSnapshot();
        $lines = [];
        $lines[] = '# GDPR Article 30 - Record of Processing Activities';
        $lines[] = '';
        $lines[] = sprintf('- Controller: %s', $snapshot['controller'] ?? 'not configured');
        $lines[] = sprintf('- Generated: %s', $snapshot['generated_at']);
        $lines[] = sprintf('- Activity count: %d', $snapshot['activity_count']);
        $lines[] = '';
        foreach ($snapshot['activities'] as $a) {
            $lines[] = sprintf('## %s%s', $a['name'], $a['is_active'] ? '' : ' (inactive)');
            $lines[] = '';
            $lines[] = sprintf('- Purpose: %s', $a['purpose']);
            $lines[] = sprintf('- Lawful basis: %s', $a['lawful_basis']);
            $lines[] = sprintf('- Categories of data: %s', implode(', ', $a['categories_of_data']));
            $lines[] = sprintf('- Categories of subjects: %s', implode(', ', $a['categories_of_subjects']));
            $lines[] = sprintf('- Recipients: %s', implode(', ', $a['recipients']));
            $lines[] = sprintf('- Retention: %s', $a['retention_period'] !== '' ? $a['retention_period'] : 'unspecified');
            $lines[] = sprintf('- Security measures: %s', $a['security_measures'] !== '' ? $a['security_measures'] : 'unspecified');
            $lines[] = sprintf('- Transfers outside EEA: %s', $a['transfers_outside_eea'] ? 'yes' : 'no');
            if ($a['transfers_outside_eea'] && $a['safeguards'] !== '') {
                $lines[] = sprintf('- Safeguards: %s', $a['safeguards']);
            }
            if ($a['dpo_contact'] !== '') {
                $lines[] = sprintf('- DPO contact: %s', $a['dpo_contact']);
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $data */
    private function sanitize(array $data): array
    {
        $out = [
            'name'                   => trim((string) ($data['name'] ?? '')),
            'purpose'                => trim((string) ($data['purpose'] ?? '')),
            'lawful_basis'           => in_array($data['lawful_basis'] ?? '', ProcessingActivity::LAWFUL_BASES, true)
                ? (string) $data['lawful_basis']
                : 'legitimate_interests',
            'categories_of_data'     => $this->toArray($data['categories_of_data'] ?? []),
            'categories_of_subjects' => $this->toArray($data['categories_of_subjects'] ?? []),
            'recipients'             => $this->toArray($data['recipients'] ?? []),
            'retention_period'       => trim((string) ($data['retention_period'] ?? '')),
            'security_measures'      => trim((string) ($data['security_measures'] ?? '')),
            'transfers_outside_eea'  => (bool) ($data['transfers_outside_eea'] ?? false),
            'safeguards'             => trim((string) ($data['safeguards'] ?? '')),
            'dpo_contact'            => trim((string) ($data['dpo_contact'] ?? '')),
            'is_active'              => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];
        return $out;
    }

    /** @param mixed $value */
    private function toArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn ($v) => $v !== ''));
        }
        if (is_string($value) && $value !== '') {
            // Allow comma- or semicolon-separated free-text input from the form.
            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value) ?: []), static fn ($v) => $v !== ''));
        }
        return [];
    }

    private function resolveControllerName(): ?string
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_setting')) {
                $row = \Illuminate\Support\Facades\DB::table('ahg_setting')
                    ->where('key', 'privacy_controller_name')->first(['value']);
                if (isset($row->value) && $row->value !== '') {
                    return (string) $row->value;
                }
            }
        } catch (\Throwable $e) {
            // ignore - settings table may not exist on a fresh install
        }
        return null;
    }
}
