<?php

/**
 * RicApiClient - Thin HTTP wrapper over the OpenRiC API.
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
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace AhgRic\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Every data-access method in Heratio's RiC admin controllers should go
 * through here. The class speaks HTTP — either to an external RiC service
 * (via X-API-Key, when config('ric.api_url') is set) or to the in-process
 * /api/ric/v1 routes on the same host.
 *
 * Why not just call the services directly? Because the point of Phase 4.4
 * is that Heratio stops being a privileged caller. Same HTTP surface any
 * external consumer would use.
 */
class RicApiClient
{
    protected string $baseUrl;
    protected ?string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $configured = config('ric.api_url');
        $fallback = rtrim((string) config('app.url'), '/') . '/api/ric/v1';
        $this->baseUrl = rtrim($configured ?: $fallback, '/');
        $this->apiKey = config('ric.service_key');
        $this->timeout = (int) config('ric.http_timeout', 5);
    }

    // --- Low-level -------------------------------------------------

    protected function client(): PendingRequest
    {
        $c = Http::timeout($this->timeout)->acceptJson();
        if ($this->apiKey) {
            $c = $c->withHeaders(['X-API-Key' => $this->apiKey]);
        }
        return $c;
    }

    protected function handle(Response $resp, string $op): array
    {
        if ($resp->successful()) {
            return $resp->json() ?? [];
        }
        Log::warning("[RicApiClient] {$op} returned {$resp->status()}: " . substr((string) $resp->body(), 0, 200));
        throw new RuntimeException("RiC API call failed: {$op} returned {$resp->status()}");
    }

    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->handle($this->client()->get($url, $query), "GET {$path}");
    }

    public function post(string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->handle($this->client()->asJson()->post($url, $body), "POST {$path}");
    }

    public function patch(string $path, array $body = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->handle($this->client()->asJson()->patch($url, $body), "PATCH {$path}");
    }

    public function delete(string $path): array
    {
        $url = $this->baseUrl . $path;
        return $this->handle($this->client()->delete($url), "DELETE {$path}");
    }

    // --- Typed helpers — mirror the public /api/ric/v1 surface -----

    public function health(): array { return $this->get('/health'); }
    public function vocabulary(): array { return $this->get('/vocabulary'); }
    public function taxonomy(string $code): array { return $this->get("/vocabulary/{$code}"); }

    public function listPlaces(array $params = []): array { return $this->get('/places', $params); }
    public function showPlace(int $id): array { return $this->get("/places/{$id}"); }
    public function placesFlat(?int $excludeId = null): array
    {
        $q = $excludeId !== null ? ['exclude_id' => $excludeId] : [];
        return $this->get('/places/flat', $q);
    }

    public function listRules(array $params = []): array { return $this->get('/rules', $params); }
    public function showRule(int $id): array { return $this->get("/rules/{$id}"); }

    public function listActivities(array $params = []): array { return $this->get('/activities', $params); }
    public function showActivity(int $id): array { return $this->get("/activities/{$id}"); }

    public function listInstantiations(array $params = []): array { return $this->get('/instantiations', $params); }
    public function showInstantiation(int $id): array { return $this->get("/instantiations/{$id}"); }

    public function autocomplete(string $q, ?string $types = null, int $limit = 20): array
    {
        $params = ['q' => $q, 'limit' => $limit];
        if ($types) $params['types'] = $types;
        return $this->get('/autocomplete', $params);
    }

    public function entitiesForRecord(int $recordId, ?array $types = null): array
    {
        $params = [];
        if ($types) $params['types'] = implode(',', $types);
        return $this->get("/records/{$recordId}/entities", $params);
    }

    public function entityInfo(int $id): array { return $this->get("/entities/{$id}/info"); }

    public function relationsFor(int $entityId): array { return $this->get("/relations-for/{$entityId}"); }

    public function listRelations(array $params = []): array { return $this->get('/relations', $params); }
    public function relationTypes(?string $domain = null, ?string $range = null): array
    {
        $params = [];
        if ($domain) $params['domain'] = $domain;
        if ($range) $params['range'] = $range;
        return $this->get('/relation-types', $params);
    }

    public function hierarchy(int $id, ?string $include = null): array
    {
        $params = $include ? ['include' => $include] : [];
        return $this->get("/hierarchy/{$id}", $params);
    }

    // --- Writes ----------------------------------------------------

    public function createEntity(string $type, array $data): array { return $this->post("/{$type}", $data); }
    public function updateEntity(string $type, int $id, array $data): array { return $this->patch("/{$type}/{$id}", $data); }
    public function deleteEntity(string $type, int $id): array { return $this->delete("/{$type}/{$id}"); }
    public function deleteEntityById(int $id): array { return $this->delete("/entities/{$id}"); }

    public function createRelation(int $subjectId, int $objectId, string $relationType, array $extra = []): array
    {
        return $this->post('/relations', array_merge([
            'subject_id' => $subjectId,
            'object_id' => $objectId,
            'relation_type' => $relationType,
        ], $extra));
    }
    public function updateRelation(int $id, array $data): array { return $this->patch("/relations/{$id}", $data); }
    public function deleteRelation(int $id): array { return $this->delete("/relations/{$id}"); }

    /**
     * Escape hatch for anything not yet covered by a typed helper.
     */
    public function raw(): self { return $this; }
}
