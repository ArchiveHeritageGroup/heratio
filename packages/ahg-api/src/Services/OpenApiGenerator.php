<?php

/**
 * OpenApiGenerator
 *
 * Reflective OpenAPI 3.1 spec generator for the Heratio REST API.
 * Walks Laravel's route table, filters /api/* routes, introspects
 * controller signatures + FormRequest rules, and emits a minimal
 * but spec-correct JSON document.
 *
 * Issue #652 Phase 1.
 *
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright 2026 Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgApi\Services;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class OpenApiGenerator
{
    /** @var string */
    protected string $title = 'Heratio REST API';

    /** @var string */
    protected string $description = 'OpenAPI 3.1 specification for the Heratio archival management REST API (v1 + v2).';

    /** @var string */
    protected string $version = '1.0.0';

    /**
     * Build the full OpenAPI 3.1 document.
     */
    public function generate(): array
    {
        $appVersion = $this->resolveAppVersion();

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->title,
                'description' => $this->description,
                'version' => $appVersion,
                'contact' => [
                    'name' => 'The Archive and Heritage Group',
                    'email' => 'johan@theahg.co.za',
                ],
                'license' => [
                    'name' => 'AGPL-3.0-or-later',
                    'identifier' => 'AGPL-3.0-or-later',
                ],
            ],
            'servers' => [
                [
                    'url' => rtrim((string) config('app.url', 'http://localhost'), '/'),
                    'description' => 'Current host',
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'API key issued via /api/v2/keys. Alternatively use Authorization: Bearer <token>.',
                    ],
                    'BearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Same API key supplied as Authorization: Bearer <token>.',
                    ],
                ],
                'schemas' => $this->commonSchemas(),
            ],
            'security' => [
                ['ApiKeyAuth' => []],
                ['BearerAuth' => []],
            ],
            'paths' => $this->buildPaths(),
            'tags' => [
                ['name' => 'v1', 'description' => 'REST API v1 (read-mostly, CRUD on a few entities)'],
                ['name' => 'v2', 'description' => 'REST API v2 (full REST with batch, search, audit, webhooks)'],
                ['name' => 'legacy', 'description' => 'Legacy /api/* routes for backward compatibility'],
                ['name' => 'docs', 'description' => 'Spec + Swagger UI endpoints'],
            ],
        ];

        return $spec;
    }

    /**
     * Walk Route::getRoutes() and convert /api/* routes into OpenAPI path items.
     */
    protected function buildPaths(): array
    {
        $paths = [];

        foreach (RouteFacade::getRoutes() as $route) {
            /** @var Route $route */
            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            // Convert Laravel {slug} -> OpenAPI {slug}, normalize leading slash
            $path = '/'.preg_replace('#\{([^\}]+)\}#', '{$1}', $uri);

            $methods = array_filter(
                $route->methods(),
                fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'], true)
            );

            foreach ($methods as $method) {
                $operation = $this->describeOperation($route, $method);
                $paths[$path][strtolower($method)] = $operation;
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Build an OpenAPI operation object for a single route+method.
     */
    protected function describeOperation(Route $route, string $method): array
    {
        $action = $route->getActionName();
        $tag = $this->tagFor($route);

        $operation = [
            'tags' => [$tag],
            'operationId' => $this->operationId($route, $method),
            'summary' => $this->summaryFor($route, $method),
            'parameters' => $this->parametersFor($route),
            'responses' => $this->responsesFor($method),
        ];

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $body = $this->requestBodyFor($route);
            if ($body !== null) {
                $operation['requestBody'] = $body;
            }
        }

        // Annotate with controller@method for traceability
        $operation['x-laravel-action'] = $action;

        // Idempotency-Key hint for non-idempotent POSTs
        if (strtoupper($method) === 'POST') {
            $operation['parameters'][] = [
                'name' => 'Idempotency-Key',
                'in' => 'header',
                'required' => false,
                'description' => 'Client-supplied idempotency token (RFC draft). Replays within 24h return the cached response.',
                'schema' => ['type' => 'string', 'maxLength' => 64],
            ];
        }

        // ETag hint for GETs
        if (strtoupper($method) === 'GET') {
            $operation['parameters'][] = [
                'name' => 'If-None-Match',
                'in' => 'header',
                'required' => false,
                'description' => 'Conditional GET. If the supplied ETag matches the current resource, the server returns 304 Not Modified.',
                'schema' => ['type' => 'string'],
            ];
            $operation['responses']['304'] = [
                'description' => 'Resource has not changed since the supplied ETag was issued.',
            ];
        }

        return $operation;
    }

    protected function tagFor(Route $route): string
    {
        $uri = $route->uri();
        if (str_starts_with($uri, 'api/v1/')) {
            return 'v1';
        }
        if (str_starts_with($uri, 'api/v2/') || $uri === 'api/v2') {
            return 'v2';
        }
        if (str_starts_with($uri, 'api/openapi') || str_starts_with($uri, 'api/docs')) {
            return 'docs';
        }

        return 'legacy';
    }

    protected function operationId(Route $route, string $method): string
    {
        $name = $route->getName();
        if ($name) {
            return strtolower($method).'_'.str_replace(['.', '/'], '_', $name);
        }

        $uri = $route->uri();
        $slug = preg_replace('#[^a-zA-Z0-9]+#', '_', $uri);

        return strtolower($method).'_'.trim($slug, '_');
    }

    protected function summaryFor(Route $route, string $method): string
    {
        $action = $route->getActionName();
        if ($action === 'Closure') {
            return strtoupper($method).' '.$route->uri();
        }

        [$class, $methodName] = array_pad(explode('@', $action), 2, '');
        $short = $class !== '' ? class_basename($class) : 'Handler';

        return sprintf('%s %s::%s', strtoupper($method), $short, $methodName);
    }

    /**
     * Path parameters extracted from {param} placeholders.
     */
    protected function parametersFor(Route $route): array
    {
        $params = [];

        foreach ($route->parameterNames() as $name) {
            $params[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => $this->paramDescription($name),
                'schema' => $this->paramSchema($name, $route),
            ];
        }

        return $params;
    }

    protected function paramDescription(string $name): string
    {
        return match ($name) {
            'slug' => 'Slug of the target object (URL-safe).',
            'id' => 'Numeric primary key.',
            'objectId' => 'Numeric object ID.',
            'photoId' => 'Numeric photo ID.',
            default => ucfirst($name).' path parameter.',
        };
    }

    protected function paramSchema(string $name, Route $route): array
    {
        $wheres = $route->wheres ?? [];
        $pattern = $wheres[$name] ?? null;
        if ($pattern === '[0-9]+' || in_array($name, ['id', 'objectId', 'photoId'], true)) {
            return ['type' => 'integer', 'format' => 'int64'];
        }

        return ['type' => 'string'];
    }

    /**
     * Best-effort request-body schema derived from the controller method signature
     * (FormRequest rules() if present) or a generic JSON object.
     */
    protected function requestBodyFor(Route $route): ?array
    {
        $properties = $this->formRequestSchema($route);

        if ($properties === null) {
            // Generic fallback so the spec is still valid
            return [
                'required' => false,
                'description' => 'Request payload (JSON).',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                ],
            ];
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $properties,
                ],
            ],
        ];
    }

    /**
     * Reflect on the controller method to look for a FormRequest with rules().
     */
    protected function formRequestSchema(Route $route): ?array
    {
        $action = $route->getActionName();
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return null;
        }

        [$class, $method] = explode('@', $action, 2);
        if (! class_exists($class)) {
            return null;
        }

        try {
            $ref = new ReflectionClass($class);
            if (! $ref->hasMethod($method)) {
                return null;
            }
            $m = $ref->getMethod($method);
        } catch (\ReflectionException $e) {
            return null;
        }

        foreach ($m->getParameters() as $param) {
            $type = $param->getType();
            if (! $type instanceof ReflectionNamedType) {
                continue;
            }
            $typeName = $type->getName();
            if (! class_exists($typeName)) {
                continue;
            }
            if (! is_subclass_of($typeName, \Illuminate\Foundation\Http\FormRequest::class)) {
                continue;
            }

            // Try to instantiate and read rules()
            try {
                $instance = new $typeName();
                if (! method_exists($instance, 'rules')) {
                    continue;
                }
                $rules = $instance->rules();
                if (! is_array($rules) || empty($rules)) {
                    continue;
                }

                return $this->rulesToSchema($rules);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Translate a Laravel validation rules array into a JSON-schema-ish object.
     */
    protected function rulesToSchema(array $rules): array
    {
        $properties = [];
        $required = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $ruleList = array_map(fn ($r) => is_object($r) ? get_class($r) : (string) $r, $ruleList);

            $type = 'string';
            $format = null;
            foreach ($ruleList as $r) {
                if ($r === 'integer' || $r === 'numeric') {
                    $type = 'integer';
                }
                if ($r === 'array') {
                    $type = 'array';
                }
                if ($r === 'boolean') {
                    $type = 'boolean';
                }
                if ($r === 'date') {
                    $type = 'string';
                    $format = 'date';
                }
                if ($r === 'email') {
                    $type = 'string';
                    $format = 'email';
                }
                if ($r === 'url') {
                    $type = 'string';
                    $format = 'uri';
                }
            }

            // Handle dot-notation fields (foo.bar) by collapsing to root key
            $rootField = explode('.', $field, 2)[0];

            $schema = ['type' => $type];
            if ($format !== null) {
                $schema['format'] = $format;
            }
            if ($type === 'array') {
                $schema['items'] = ['type' => 'string'];
            }

            $properties[$rootField] = $schema;

            if (in_array('required', $ruleList, true)) {
                $required[] = $rootField;
            }
        }

        $out = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (! empty($required)) {
            $out['required'] = array_values(array_unique($required));
        }

        return $out;
    }

    protected function responsesFor(string $method): array
    {
        $responses = [
            '200' => [
                'description' => 'Success.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ],
                ],
            ],
            '401' => [
                'description' => 'Missing or invalid API key.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
            '403' => [
                'description' => 'Authenticated but missing required scope.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
            '404' => [
                'description' => 'Resource not found.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ],
        ];

        $up = strtoupper($method);
        if ($up === 'POST') {
            $responses['201'] = [
                'description' => 'Created.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/SuccessEnvelope'],
                    ],
                ],
            ];
            $responses['422'] = [
                'description' => 'Validation failed.',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorEnvelope'],
                    ],
                ],
            ];
        }
        if ($up === 'DELETE') {
            $responses['204'] = ['description' => 'Deleted.'];
        }

        return $responses;
    }

    /**
     * Components / shared schemas.
     */
    protected function commonSchemas(): array
    {
        return [
            'SuccessEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => true],
                    'data' => ['description' => 'Endpoint-specific payload.'],
                    'meta' => ['type' => 'object', 'description' => 'Pagination + counters (optional).'],
                    'links' => ['type' => 'object', 'description' => 'HATEOAS-style links (optional).'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['success'],
            ],
            'ErrorEnvelope' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean', 'const' => false],
                    'error' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                    'timestamp' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['success', 'error', 'message'],
            ],
        ];
    }

    /**
     * Pull version from version.json if present, falling back to a constant.
     */
    protected function resolveAppVersion(): string
    {
        $path = base_path('version.json');
        if (is_file($path)) {
            $raw = @file_get_contents($path);
            if ($raw !== false) {
                $json = json_decode($raw, true);
                if (is_array($json) && ! empty($json['version'])) {
                    return (string) $json['version'];
                }
            }
        }

        return $this->version;
    }
}
