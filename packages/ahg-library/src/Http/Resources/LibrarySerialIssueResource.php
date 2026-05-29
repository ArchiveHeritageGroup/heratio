<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibrarySerialIssueResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-serial-issues';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'volume'         => $this->volume,
            'issue_number'   => $this->issue_number,
            'issue_date'     => optional($this->issue_date)->toDateString(),
            'received_at'    => optional($this->received_at)->toDateString(),
            'status'         => $this->status,
            'shelf_location' => $this->shelf_location,
            'bound_at'       => optional($this->bound_at)->toDateString(),
            'notes'          => $this->notes,
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }

    protected function relationships(Request $request): array
    {
        return [
            'serial'  => $this->identifier('library-serials', $this->serial_id),
            'binding' => $this->identifier('library-bindings', $this->binding_id),
        ];
    }
}
