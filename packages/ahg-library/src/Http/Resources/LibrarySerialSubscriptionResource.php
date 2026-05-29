<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibrarySerialSubscriptionResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-serial-subscriptions';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'subscription_start' => optional($this->subscription_start)->toDateString(),
            'subscription_end'   => optional($this->subscription_end)->toDateString(),
            'subscription_cost'  => $this->subscription_cost !== null ? (float) $this->subscription_cost : null,
            'notification_email' => $this->notification_email,
            'auto_claim_max'     => (int) $this->auto_claim_max,
            'notes'              => $this->notes,
            'created_at'         => optional($this->created_at)->toIso8601String(),
            'updated_at'         => optional($this->updated_at)->toIso8601String(),
        ];
    }

    protected function relationships(Request $request): array
    {
        return [
            'serial' => $this->identifier('library-serials', $this->serial_id),
        ];
    }
}
