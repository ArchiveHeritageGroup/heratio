<?php

namespace AhgSharePoint\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mirror of AtomExtensions\SharePoint\Services\SharePointRetentionMapper.
 *
 * Reads listItem.fields (_ComplianceTag, _ComplianceTagWrittenTime, _IsRecord)
 * and resolves a target Heratio disposition based on a tenant-configurable
 * map stored in ahg_settings.
 *
 * @phase 2.A
 */
class SharePointRetentionMapper
{
    public function resolve(array $listItemFields): array
    {
        $tag = $listItemFields['_ComplianceTag'] ?? null;
        $written = $listItemFields['_ComplianceTagWrittenTime'] ?? null;
        $isRecord = (bool) ($listItemFields['_IsRecord'] ?? false);

        $base = ['compliance_tag' => $tag, 'is_record' => $isRecord];

        if ($tag === null || $tag === '') {
            return $base + $this->lookupMap('default');
        }

        $entry = $this->lookupMap($tag);

        if (!empty($entry['embargo_until_field']) && $entry['embargo_until_field'] === '_ComplianceTagWrittenTime' && $written) {
            $offsetDays = (int) ($entry['embargo_offset_days'] ?? 0);
            try {
                $writtenDate = new \DateTimeImmutable($written);
                $entry['embargo_until'] = $writtenDate->modify("+{$offsetDays} days")->format('Y-m-d');
            } catch (\Throwable $e) {
                // skip
            }
        }

        return $base + $entry;
    }

    private function lookupMap(string $key): array
    {
        $row = DB::table('ahg_settings')
            ->where('setting_group', 'sharepoint')
            ->where('setting_key', 'retention_label_map')
            ->first();

        if ($row === null || empty($row->setting_value)) {
            return [];
        }

        $map = json_decode($row->setting_value, true);
        if (!is_array($map)) {
            return [];
        }

        return $map[$key] ?? ($map['default'] ?? []);
    }
}
