<?php

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointMappingService.
 *
 * @phase 2.A
 */
class SharePointMappingService
{
    public function project(int $driveId, array $driveItem, array $fields): array
    {
        $rules = $this->loadMapping($driveId);
        $out = [];

        foreach ($rules as $rule) {
            $value = $this->readSource($rule->source_field, $driveItem, $fields);
            if ($value === null && $rule->default_value !== null) {
                $value = $rule->default_value;
            }
            if ($rule->transform) {
                $value = $this->applyTransform($rule->transform, $value);
            }
            if ($value !== null && $value !== '') {
                $out[$rule->target_field] = $value;
            }
        }

        $out['_sharepoint_drive_id'] = $driveId;
        $out['_sharepoint_item_id'] = $driveItem['id'] ?? null;
        $out['_sharepoint_etag'] = $driveItem['eTag'] ?? null;

        return $out;
    }

    /** @return array<int, object> */
    private function loadMapping(int $driveId): array
    {
        return DB::table('sharepoint_mapping')
            ->where('drive_id', $driveId)
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    private function readSource(string $sourceField, array $driveItem, array $fields)
    {
        if (str_starts_with($sourceField, 'fields.')) {
            return $fields[substr($sourceField, strlen('fields.'))] ?? null;
        }
        if (str_contains($sourceField, '.')) {
            $current = $driveItem;
            foreach (explode('.', $sourceField) as $part) {
                if (!is_array($current) || !array_key_exists($part, $current)) {
                    return null;
                }
                $current = $current[$part];
            }
            return $current;
        }
        return $driveItem[$sourceField] ?? null;
    }

    private function applyTransform(string $transform, $value)
    {
        if ($value === null) {
            return null;
        }
        return match ($transform) {
            'date_iso' => $this->toIsoDate((string) $value),
            'html_strip' => trim(strip_tags((string) $value)),
            'lowercase' => strtolower((string) $value),
            'uppercase' => strtoupper((string) $value),
            default => $value,
        };
    }

    private function toIsoDate(string $raw): ?string
    {
        try {
            return (new \DateTimeImmutable($raw))->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
