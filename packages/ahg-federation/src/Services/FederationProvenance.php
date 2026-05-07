<?php

/**
 * FederationProvenance - track source/harvest history for federated records
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

namespace AhgFederation\Services;

use Illuminate\Support\Facades\DB;

class FederationProvenance
{
    public const SCOPE = 'federation';
    public const PROP_SOURCE_PEER_ID = 'source_peer_id';
    public const PROP_SOURCE_OAI_IDENTIFIER = 'source_oai_identifier';
    public const PROP_HARVEST_DATE = 'harvest_date';
    public const PROP_METADATA_FORMAT = 'metadata_format';

    public function recordHarvest(
        int $objectId,
        int $peerId,
        string $sourceIdentifier,
        string $metadataFormat,
        string $action
    ): void {
        $this->setProperty($objectId, self::PROP_SOURCE_PEER_ID, (string) $peerId);
        $this->setProperty($objectId, self::PROP_SOURCE_OAI_IDENTIFIER, $sourceIdentifier);
        $this->setProperty($objectId, self::PROP_HARVEST_DATE, date('Y-m-d H:i:s'));
        $this->setProperty($objectId, self::PROP_METADATA_FORMAT, $metadataFormat);

        DB::table('federation_harvest_log')->insert([
            'peer_id' => $peerId,
            'information_object_id' => $objectId,
            'source_oai_identifier' => $sourceIdentifier,
            'harvest_date' => date('Y-m-d H:i:s'),
            'metadata_format' => $metadataFormat,
            'action' => $action,
        ]);
    }

    /**
     * Find an information object id by (peer_id, source_oai_identifier).
     * Returns the object id if found, otherwise null.
     */
    public function findBySourceIdentifier(int $peerId, string $sourceIdentifier): ?int
    {
        // First locate every property row that matches the source identifier.
        $propertyIds = DB::table('property')
            ->where('scope', self::SCOPE)
            ->where('name', self::PROP_SOURCE_OAI_IDENTIFIER)
            ->pluck('id', 'object_id');

        if ($propertyIds->isEmpty()) {
            return null;
        }

        // Match the value through property_i18n.
        $matchedObjectId = DB::table('property_i18n')
            ->whereIn('id', $propertyIds->values())
            ->where('value', $sourceIdentifier)
            ->value('id');

        if (!$matchedObjectId) {
            return null;
        }

        $objectId = $propertyIds->search($matchedObjectId);
        if ($objectId === false) {
            return null;
        }

        // Verify the peer id property matches.
        $peerProperty = DB::table('property')
            ->where('object_id', $objectId)
            ->where('scope', self::SCOPE)
            ->where('name', self::PROP_SOURCE_PEER_ID)
            ->first();

        if (!$peerProperty) {
            return null;
        }

        $peerValue = DB::table('property_i18n')
            ->where('id', $peerProperty->id)
            ->value('value');

        if ((int) $peerValue !== $peerId) {
            return null;
        }

        return (int) $objectId;
    }

    public function getProvenance(int $objectId): ?array
    {
        $peerId = $this->getProperty($objectId, self::PROP_SOURCE_PEER_ID);
        if (!$peerId) {
            return null;
        }

        $peer = DB::table('federation_peer')->where('id', (int) $peerId)->first();

        return [
            'sourcePeerId' => (int) $peerId,
            'sourcePeerName' => $peer ? $peer->name : 'Unknown',
            'sourcePeerUrl' => $peer ? $peer->base_url : null,
            'sourceOaiIdentifier' => $this->getProperty($objectId, self::PROP_SOURCE_OAI_IDENTIFIER),
            'harvestDate' => $this->getProperty($objectId, self::PROP_HARVEST_DATE),
            'metadataFormat' => $this->getProperty($objectId, self::PROP_METADATA_FORMAT),
        ];
    }

    public function getHarvestHistory(int $objectId): array
    {
        return DB::table('federation_harvest_log as l')
            ->leftJoin('federation_peer as p', 'l.peer_id', '=', 'p.id')
            ->where('l.information_object_id', $objectId)
            ->select(['l.*', 'p.name as peer_name', 'p.base_url as peer_url'])
            ->orderByDesc('l.harvest_date')
            ->get()
            ->toArray();
    }

    public function isFederatedRecord(int $objectId): bool
    {
        return $this->getProperty($objectId, self::PROP_SOURCE_PEER_ID) !== null;
    }

    public function countRecordsFromPeer(int $peerId): int
    {
        $propertyIds = DB::table('property')
            ->where('scope', self::SCOPE)
            ->where('name', self::PROP_SOURCE_PEER_ID)
            ->pluck('id');

        if ($propertyIds->isEmpty()) {
            return 0;
        }

        return DB::table('property_i18n')
            ->whereIn('id', $propertyIds)
            ->where('value', (string) $peerId)
            ->count();
    }

    public function removeProvenance(int $objectId): void
    {
        $propertyIds = DB::table('property')
            ->where('object_id', $objectId)
            ->where('scope', self::SCOPE)
            ->pluck('id');

        if ($propertyIds->isNotEmpty()) {
            DB::table('property_i18n')->whereIn('id', $propertyIds)->delete();
            DB::table('property')->whereIn('id', $propertyIds)->delete();
        }
    }

    public function getStatistics(): array
    {
        $peerIdProps = DB::table('property')
            ->where('scope', self::SCOPE)
            ->where('name', self::PROP_SOURCE_PEER_ID)
            ->pluck('id');

        $totalRecords = DB::table('property_i18n')->whereIn('id', $peerIdProps)->count();

        $recordsByPeer = DB::table('property_i18n as pi')
            ->whereIn('pi.id', $peerIdProps)
            ->leftJoin('federation_peer as fp', 'pi.value', '=', DB::raw('CAST(fp.id AS CHAR)'))
            ->select([
                'pi.value as peer_id',
                'fp.name as peer_name',
                DB::raw('COUNT(*) as record_count'),
            ])
            ->groupBy('pi.value', 'fp.name')
            ->get()
            ->toArray();

        $recentHarvests = DB::table('federation_harvest_log as l')
            ->leftJoin('federation_peer as p', 'l.peer_id', '=', 'p.id')
            ->where('l.harvest_date', '>=', date('Y-m-d H:i:s', strtotime('-30 days')))
            ->select([
                'l.peer_id',
                'p.name as peer_name',
                'l.action',
                DB::raw('COUNT(*) as count'),
                DB::raw('MAX(l.harvest_date) as last_harvest'),
            ])
            ->groupBy('l.peer_id', 'p.name', 'l.action')
            ->get()
            ->toArray();

        return [
            'totalFederatedRecords' => $totalRecords,
            'recordsByPeer' => $recordsByPeer,
            'recentHarvests' => $recentHarvests,
        ];
    }

    /**
     * Set a property; value lives in property_i18n keyed by culture.
     * Heratio's property table holds (object_id, scope, name) and the
     * actual value is stored in property_i18n (id, culture, value).
     */
    protected function setProperty(int $objectId, string $name, string $value, ?string $culture = null): void
    {
        $culture = $culture ?: app()->getLocale();

        $existing = DB::table('property')
            ->where('object_id', $objectId)
            ->where('scope', self::SCOPE)
            ->where('name', $name)
            ->first();

        if ($existing) {
            $i18nExists = DB::table('property_i18n')
                ->where('id', $existing->id)
                ->where('culture', $culture)
                ->exists();

            if ($i18nExists) {
                DB::table('property_i18n')
                    ->where('id', $existing->id)
                    ->where('culture', $culture)
                    ->update(['value' => $value]);
            } else {
                DB::table('property_i18n')->insert([
                    'id' => $existing->id,
                    'culture' => $culture,
                    'value' => $value,
                ]);
            }
            return;
        }

        $propertyId = DB::table('property')->insertGetId([
            'object_id' => $objectId,
            'scope' => self::SCOPE,
            'name' => $name,
            'source_culture' => $culture,
        ]);

        DB::table('property_i18n')->insert([
            'id' => $propertyId,
            'culture' => $culture,
            'value' => $value,
        ]);
    }

    protected function getProperty(int $objectId, string $name, ?string $culture = null): ?string
    {
        $culture = $culture ?: app()->getLocale();

        $property = DB::table('property')
            ->where('object_id', $objectId)
            ->where('scope', self::SCOPE)
            ->where('name', $name)
            ->first();

        if (!$property) {
            return null;
        }

        $value = DB::table('property_i18n')
            ->where('id', $property->id)
            ->where('culture', $culture)
            ->value('value');

        if ($value === null) {
            // Fall back to source_culture when the requested culture has no row.
            $value = DB::table('property_i18n')
                ->where('id', $property->id)
                ->where('culture', $property->source_culture)
                ->value('value');
        }

        return $value;
    }
}
