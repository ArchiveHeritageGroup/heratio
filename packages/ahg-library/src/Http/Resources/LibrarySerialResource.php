<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibrarySerialResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-serials';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'title'      => $this->title,
            'issn'       => $this->issn,
            'frequency'  => $this->frequency,
            'publisher'  => $this->publisher,
            'status'     => $this->status,
            'notes'      => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    protected function relationships(Request $request): array
    {
        return [
            'issues' => $this->whenLoaded('issues', fn () => [
                'data' => $this->issues->map(fn ($i) => [
                    'type' => 'library-serial-issues',
                    'id'   => (string) $i->id,
                ])->all(),
            ]),
            'subscription' => $this->whenLoaded(
                'subscription',
                fn () => $this->identifier('library-serial-subscriptions', $this->subscription?->id)
            ),
        ];
    }
}
