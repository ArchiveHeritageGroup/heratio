<?php

namespace AhgApi\Controllers\V2;

use Illuminate\Http\JsonResponse;

class ApiRootController extends BaseApiController
{
    /**
     * GET /api/v2 — API index with all available endpoints.
     */
    public function index(): JsonResponse
    {
        return $this->success([
            'name' => 'Heratio REST API',
            'version' => 'v2',
            'endpoints' => [
                'descriptions' => ['GET /api/v2/descriptions', 'POST /api/v2/descriptions', 'GET /api/v2/descriptions/{slug}', 'PUT /api/v2/descriptions/{slug}', 'DELETE /api/v2/descriptions/{slug}'],
                'authorities' => ['GET /api/v2/authorities', 'GET /api/v2/authorities/{slug}'],
                'repositories' => ['GET /api/v2/repositories'],
                'taxonomies' => ['GET /api/v2/taxonomies', 'GET /api/v2/taxonomies/{id}/terms'],
                'search' => ['POST /api/v2/search'],
                'batch' => ['POST /api/v2/batch'],
                'keys' => ['GET /api/v2/keys', 'POST /api/v2/keys', 'DELETE /api/v2/keys/{id}'],
                'webhooks' => ['GET /api/v2/webhooks', 'POST /api/v2/webhooks', 'GET /api/v2/webhooks/{id}', 'PUT /api/v2/webhooks/{id}', 'DELETE /api/v2/webhooks/{id}', 'GET /api/v2/webhooks/{id}/deliveries', 'POST /api/v2/webhooks/{id}/regenerate-secret'],
                'events' => ['GET /api/v2/events', 'GET /api/v2/events/{id}', 'GET /api/v2/events/correlation/{id}'],
                'audit' => ['GET /api/v2/audit', 'GET /api/v2/audit/{id}'],
                'publish' => ['GET /api/v2/publish/readiness/{slug}', 'POST /api/v2/publish/execute/{slug}'],
                'upload' => ['POST /api/v2/upload', 'POST /api/v2/descriptions/{slug}/upload'],
                'conditions' => ['GET /api/v2/conditions', 'POST /api/v2/conditions', 'GET /api/v2/conditions/{id}', 'PUT /api/v2/conditions/{id}', 'DELETE /api/v2/conditions/{id}', 'GET /api/v2/descriptions/{slug}/conditions', 'GET /api/v2/conditions/{id}/photos', 'POST /api/v2/conditions/{id}/photos'],
                'assets' => ['GET /api/v2/assets', 'POST /api/v2/assets', 'GET /api/v2/assets/{id}', 'PUT /api/v2/assets/{id}', 'GET /api/v2/descriptions/{slug}/asset', 'GET /api/v2/valuations', 'POST /api/v2/valuations', 'GET /api/v2/assets/{id}/valuations'],
                'privacy' => ['GET /api/v2/privacy/dsars', 'POST /api/v2/privacy/dsars', 'GET /api/v2/privacy/dsars/{id}', 'PUT /api/v2/privacy/dsars/{id}', 'GET /api/v2/privacy/breaches', 'POST /api/v2/privacy/breaches'],
                'sync' => ['GET /api/v2/sync/changes', 'POST /api/v2/sync/batch'],
            ],
            'authentication' => [
                'methods' => ['X-API-Key header', 'Authorization: Bearer', 'Session (cookie)'],
                'scopes' => ['read', 'write', 'delete', 'batch', 'publish:write'],
            ],
        ]);
    }
}
