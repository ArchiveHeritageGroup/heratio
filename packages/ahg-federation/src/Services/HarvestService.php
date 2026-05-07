<?php

/**
 * HarvestService - orchestrates federation OAI-PMH harvests for Heratio
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

use AhgInformationObjectManage\Services\InformationObjectService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HarvestService
{
    protected HarvestClient $client;
    protected FederationProvenance $provenance;
    protected array $stats;

    /**
     * Preferred metadata formats in order of preference.
     */
    protected array $preferredFormats = ['oai_heritage', 'oai_dc', 'oai_ead'];

    public function __construct(?HarvestClient $client = null, ?FederationProvenance $provenance = null)
    {
        $this->client = $client ?? new HarvestClient();
        $this->provenance = $provenance ?? new FederationProvenance();
        $this->resetStats();
    }

    protected function resetStats(): void
    {
        $this->stats = [
            'total' => 0,
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'skipped' => 0,
            'errors' => 0,
            'errorMessages' => [],
        ];
    }

    /**
     * Harvest records from a single peer.
     *
     * @param array $options from / until / set / metadataPrefix / fullHarvest / sessionId
     */
    public function harvestPeer(int $peerId, array $options = []): HarvestResult
    {
        $this->resetStats();

        $peer = DB::table('federation_peer')->where('id', $peerId)->first();
        if (!$peer) {
            throw new HarvestException("Peer not found: $peerId");
        }
        if (!$peer->is_active) {
            throw new HarvestException("Peer is not active: {$peer->name}");
        }

        $metadataPrefix = $options['metadataPrefix'] ?? null;
        if (!$metadataPrefix) {
            $metadataPrefix = $this->detectBestFormat($peer->base_url);
        }

        $from = null;
        $until = $options['until'] ?? null;

        if (empty($options['fullHarvest']) && $peer->last_harvest_at) {
            $from = gmdate('Y-m-d\TH:i:s\Z', strtotime($peer->last_harvest_at));
        }
        if (!empty($options['from'])) {
            $from = $options['from'];
        }

        $harvestParams = ['metadataPrefix' => $metadataPrefix];
        if ($from) { $harvestParams['from'] = $from; }
        if ($until) { $harvestParams['until'] = $until; }
        if (!empty($options['set'])) { $harvestParams['set'] = $options['set']; }

        $startTime = microtime(true);

        try {
            foreach ($this->client->listRecords($peer->base_url, $harvestParams) as $oaiRecord) {
                $this->stats['total']++;
                try {
                    $this->processRecord($oaiRecord, $peerId, $metadataPrefix);
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    $this->stats['errorMessages'][] = ($oaiRecord['header']['identifier'] ?? '?') . ': ' . $e->getMessage();
                    Log::warning('[federation] record processing failed', [
                        'peer_id' => $peerId,
                        'identifier' => $oaiRecord['header']['identifier'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            DB::table('federation_peer')
                ->where('id', $peerId)
                ->update([
                    'last_harvest_at' => now(),
                    'last_harvest_status' => $this->stats['errors'] > 0 ? 'partial' : 'success',
                    'last_harvest_records' => $this->stats['total'],
                    'updated_at' => now(),
                ]);
        } catch (HarvestException $e) {
            $this->stats['errors']++;
            $this->stats['errorMessages'][] = 'Harvest failed: ' . $e->getMessage();

            DB::table('federation_peer')
                ->where('id', $peerId)
                ->update([
                    'last_harvest_status' => 'failed',
                    'updated_at' => now(),
                ]);
        }

        $duration = microtime(true) - $startTime;

        return new HarvestResult(
            peerId: $peerId,
            peerName: $peer->name,
            metadataPrefix: $metadataPrefix,
            from: $from,
            until: $until,
            set: $options['set'] ?? null,
            stats: $this->stats,
            duration: $duration,
        );
    }

    protected function processRecord(array $oaiRecord, int $peerId, string $metadataPrefix): void
    {
        $header = $oaiRecord['header'];
        $identifier = $header['identifier'];

        if (($header['status'] ?? 'active') === 'deleted') {
            $this->handleDeletedRecord($identifier, $peerId);
            return;
        }

        if (empty($oaiRecord['metadata'])) {
            $this->stats['skipped']++;
            return;
        }

        $existingId = $this->provenance->findBySourceIdentifier($peerId, $identifier);
        if ($existingId) {
            $this->updateRecord($oaiRecord, $existingId, $peerId, $metadataPrefix);
        } else {
            $this->importRecord($oaiRecord, $peerId, $metadataPrefix);
        }
    }

    public function importRecord(array $oaiRecord, int $peerId, string $metadataPrefix): int
    {
        $data = $this->mapMetadataToData($oaiRecord['metadata'], $metadataPrefix);

        // All harvested records start as Draft so an editor must review them
        // before they appear in public browse / search results.
        $data['publication_status_id'] = InformationObjectService::STATUS_DRAFT;

        $newId = InformationObjectService::create($data);

        $this->provenance->recordHarvest(
            objectId: $newId,
            peerId: $peerId,
            sourceIdentifier: $oaiRecord['header']['identifier'],
            metadataFormat: $metadataPrefix,
            action: 'created',
        );

        $this->stats['created']++;
        return $newId;
    }

    public function updateRecord(array $oaiRecord, int $existingId, int $peerId, string $metadataPrefix): void
    {
        $data = $this->mapMetadataToData($oaiRecord['metadata'], $metadataPrefix);

        InformationObjectService::update($existingId, $data);

        $this->provenance->recordHarvest(
            objectId: $existingId,
            peerId: $peerId,
            sourceIdentifier: $oaiRecord['header']['identifier'],
            metadataFormat: $metadataPrefix,
            action: 'updated',
        );

        $this->stats['updated']++;
    }

    protected function handleDeletedRecord(string $identifier, int $peerId): void
    {
        $existingId = $this->provenance->findBySourceIdentifier($peerId, $identifier);
        if (!$existingId) {
            $this->stats['skipped']++;
            return;
        }

        // Per the AtoM convention, mark the record deleted in the log but
        // leave the local copy in place so editors can review before purge.
        $this->provenance->recordHarvest(
            objectId: $existingId,
            peerId: $peerId,
            sourceIdentifier: $identifier,
            metadataFormat: '',
            action: 'deleted',
        );
        $this->stats['deleted']++;
    }

    /**
     * Map OAI metadata into the snake_case data shape expected by
     * InformationObjectService::create / update.
     */
    protected function mapMetadataToData(array $metadata, string $metadataPrefix): array
    {
        switch ($metadataPrefix) {
            case 'oai_heritage':
                return $this->mapHeritage($metadata);
            case 'oai_dc':
            default:
                return $this->mapDublinCore($metadata);
        }
    }

    protected function mapHeritage(array $m): array
    {
        $data = [];
        if (!empty($m['title']))                $data['title']                       = $m['title'];
        if (!empty($m['description']))          $data['scope_and_content']           = $m['description'];
        if (!empty($m['referenceCode']))        $data['identifier']                  = $m['referenceCode'];
        if (!empty($m['extent']))               $data['extent_and_medium']           = $m['extent'];
        if (!empty($m['accessConditions']))     $data['access_conditions']           = $m['accessConditions'];
        if (!empty($m['provenance']))           $data['archival_history']            = $m['provenance'];
        if (!empty($m['arrangement']))          $data['arrangement']                 = $m['arrangement'];

        if (!empty($m['levelOfDescription'])) {
            $levelId = $this->resolveTermId($m['levelOfDescription'], InformationObjectService::TAXONOMY_LEVELS_OF_DESCRIPTION);
            if ($levelId) { $data['level_of_description_id'] = $levelId; }
        }

        return $data;
    }

    protected function mapDublinCore(array $m): array
    {
        $data = [];

        if (!empty($m['title'])) {
            $data['title'] = is_array($m['title']) ? ($m['title'][0] ?? null) : $m['title'];
        }

        if (!empty($m['description'])) {
            $description = is_array($m['description']) ? implode("\n\n", $m['description']) : $m['description'];
            $data['scope_and_content'] = $description;
        }

        if (!empty($m['identifier'])) {
            // Use the first non-URL identifier as the local reference code.
            foreach ((array) $m['identifier'] as $identifier) {
                if (strpos($identifier, 'http') !== 0) {
                    $data['identifier'] = $identifier;
                    break;
                }
            }
        }

        if (!empty($m['rights'])) {
            $rights = is_array($m['rights']) ? implode("\n", $m['rights']) : $m['rights'];
            $data['access_conditions'] = $rights;
        }

        if (!empty($m['format'])) {
            $format = is_array($m['format']) ? implode('; ', $m['format']) : $m['format'];
            $data['extent_and_medium'] = $format;
        }

        if (!empty($m['source'])) {
            $source = is_array($m['source']) ? ($m['source'][0] ?? null) : $m['source'];
            if ($source) { $data['sources'] = $source; }
        }

        return $data;
    }

    /**
     * Look up a term by name within a taxonomy.
     * Returns the term id when an exact match exists in any culture; null otherwise.
     */
    protected function resolveTermId(string $name, int $taxonomyId): ?int
    {
        if ($name === '') {
            return null;
        }

        $row = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', $taxonomyId)
            ->where('term_i18n.name', $name)
            ->select('term.id')
            ->first();

        return $row ? (int) $row->id : null;
    }

    protected function detectBestFormat(string $baseUrl): string
    {
        try {
            $formats = $this->client->listMetadataFormats($baseUrl);
        } catch (\Throwable $e) {
            return 'oai_dc';
        }

        $available = array_column($formats, 'metadataPrefix');
        foreach ($this->preferredFormats as $preferred) {
            if (in_array($preferred, $available, true)) {
                return $preferred;
            }
        }

        return 'oai_dc';
    }

    public function getClient(): HarvestClient { return $this->client; }
    public function getProvenance(): FederationProvenance { return $this->provenance; }
}

class HarvestResult
{
    public function __construct(
        public readonly int $peerId,
        public readonly string $peerName,
        public readonly string $metadataPrefix,
        public readonly ?string $from,
        public readonly ?string $until,
        public readonly ?string $set,
        public readonly array $stats,
        public readonly float $duration,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->stats['errors'] === 0
            || $this->stats['created'] > 0
            || $this->stats['updated'] > 0;
    }

    public function getSummary(): string
    {
        return sprintf(
            'Harvested %d records from %s: %d created, %d updated, %d deleted, %d skipped, %d errors (%.2fs)',
            $this->stats['total'],
            $this->peerName,
            $this->stats['created'],
            $this->stats['updated'],
            $this->stats['deleted'],
            $this->stats['skipped'],
            $this->stats['errors'],
            $this->duration,
        );
    }

    public function toArray(): array
    {
        return [
            'peerId' => $this->peerId,
            'peerName' => $this->peerName,
            'metadataPrefix' => $this->metadataPrefix,
            'from' => $this->from,
            'until' => $this->until,
            'set' => $this->set,
            'stats' => $this->stats,
            'duration' => $this->duration,
            'successful' => $this->isSuccessful(),
            'summary' => $this->getSummary(),
        ];
    }
}
