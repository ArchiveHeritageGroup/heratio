<?php

/**
 * RestoreService — apply a stored version snapshot back to the live entity.
 *
 * Mirror of the AtoM-side service. v1 scope: base + i18n + custom_fields.
 * Access points / events / relations / physical_objects are not restored.
 *
 * @phase H
 */

namespace AhgVersionControl\Services;

use Illuminate\Support\Facades\DB;

class RestoreService
{
    private const ENTITY_CONFIG = [
        'information_object' => [
            'base_table'   => 'information_object',
            'i18n_table'   => 'information_object_i18n',
            'fk_in_i18n'   => 'id',
        ],
        'actor' => [
            'base_table'   => 'actor',
            'i18n_table'   => 'actor_i18n',
            'fk_in_i18n'   => 'id',
        ],
    ];

    public function __construct(
        private readonly SnapshotBuilder $builder,
        private readonly VersionWriter $writer,
    ) {
    }

    /**
     * @return int the new version_number created by the restore
     */
    public function restore(string $entityType, int $entityId, int $targetVersionNumber, ?int $userId = null): int
    {
        if (!isset(self::ENTITY_CONFIG[$entityType])) {
            throw new \RuntimeException("RestoreService: unsupported entity_type '{$entityType}'");
        }
        $cfg = self::ENTITY_CONFIG[$entityType];
        $versionTable = $entityType === 'actor' ? 'actor_version' : 'information_object_version';
        $vfk = $entityType === 'actor' ? 'actor_id' : 'information_object_id';

        // Phase J — clearance check (against the CURRENT classification of the entity).
        $clearance = new ClearanceCheck();
        if (!$clearance->canUserRestore($userId, $entityId)) {
            throw new InsufficientClearanceException(
                $clearance->explainDenial($userId, $entityId)
                    ?? 'Insufficient security clearance to restore this record.',
            );
        }

        $snapJson = DB::table($versionTable)
            ->where($vfk, $entityId)
            ->where('version_number', $targetVersionNumber)
            ->value('snapshot');
        if (!is_string($snapJson)) {
            throw new \RuntimeException("RestoreService: version {$targetVersionNumber} not found for {$entityType} {$entityId}");
        }
        $snapshot = json_decode($snapJson, true);
        if (!is_array($snapshot)) {
            throw new \RuntimeException("RestoreService: snapshot JSON is malformed");
        }

        $base = is_array($snapshot['base'] ?? null) ? $snapshot['base'] : [];
        $i18n = is_array($snapshot['i18n'] ?? null) ? $snapshot['i18n'] : [];
        $customFields = is_array($snapshot['custom_fields'] ?? null) ? $snapshot['custom_fields'] : [];

        VersionContext::skip();
        try {
            DB::transaction(function () use ($cfg, $entityId, $base, $i18n, $customFields) {
                $baseUpdate = $base;
                unset($baseUpdate['id'], $baseUpdate['lft'], $baseUpdate['rgt'], $baseUpdate['oai_local_identifier']);
                if (!empty($baseUpdate)) {
                    DB::table($cfg['base_table'])
                        ->where('id', $entityId)
                        ->update($baseUpdate);
                }

                DB::table($cfg['i18n_table'])->where($cfg['fk_in_i18n'], $entityId)->delete();
                foreach ($i18n as $row) {
                    if (!is_array($row) || empty($row['culture'])) {
                        continue;
                    }
                    $row[$cfg['fk_in_i18n']] = $entityId;
                    DB::table($cfg['i18n_table'])->insert($row);
                }

                try {
                    DB::table('custom_field_value')->where('object_id', $entityId)->delete();
                    foreach ($customFields as $cfRow) {
                        if (!is_array($cfRow) || empty($cfRow['field_definition_id'])) {
                            continue;
                        }
                        DB::table('custom_field_value')->insert([
                            'field_definition_id' => $cfRow['field_definition_id'],
                            'object_id'           => $entityId,
                            'value_text'          => $cfRow['value_text']     ?? null,
                            'value_number'        => $cfRow['value_number']   ?? null,
                            'value_date'          => $cfRow['value_date']     ?? null,
                            'value_boolean'       => $cfRow['value_boolean']  ?? null,
                            'value_dropdown'      => $cfRow['value_dropdown'] ?? null,
                            'sequence'            => $cfRow['sequence']       ?? 0,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // ahg-custom-fields not installed — silently skip.
                }
            });
        } finally {
            VersionContext::enable();
        }

        $newSnapshot = $entityType === 'actor'
            ? $this->builder->buildForActor($entityId)
            : $this->builder->buildForInformationObject($entityId);

        return $this->writer->write(
            entityType: $entityType,
            entityId: $entityId,
            snapshot: $newSnapshot,
            changeSummary: sprintf('Restored from v%d', $targetVersionNumber),
            userId: $userId,
            isRestore: true,
            restoredFromVersion: $targetVersionNumber,
        );
    }
}
