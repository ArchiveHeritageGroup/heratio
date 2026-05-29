<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Base JSON:API resource (heratio#1100). Subclasses define the member type,
 * attributes and (optionally) relationships; this assembles the
 * {type, id, attributes, relationships} resource object. JsonResource wraps a
 * single resource in {"data": ...} and a collection in {"data": [...]}.
 */
abstract class JsonApiResource extends JsonResource
{
    /** JSON:API member type, e.g. "library-orders". */
    abstract protected function jsonApiType(): string;

    /** @return array<string, mixed> */
    abstract protected function jsonAttributes(Request $request): array;

    /** @return array<string, mixed> */
    protected function relationships(Request $request): array
    {
        return [];
    }

    public function toArray(Request $request): array
    {
        $object = [
            'type'       => $this->jsonApiType(),
            'id'         => (string) $this->resource->getKey(),
            'attributes' => $this->jsonAttributes($request),
        ];

        $relationships = array_filter(
            $this->relationships($request),
            static fn ($v) => $v !== null,
        );
        if ($relationships !== []) {
            $object['relationships'] = $relationships;
        }

        return $object;
    }

    /** Build a JSON:API resource-identifier object. */
    protected function identifier(string $type, int|string|null $id): ?array
    {
        return $id === null ? null : ['data' => ['type' => $type, 'id' => (string) $id]];
    }
}
