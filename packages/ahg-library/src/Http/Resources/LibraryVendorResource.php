<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Http\Resources;

use Illuminate\Http\Request;

class LibraryVendorResource extends JsonApiResource
{
    protected function jsonApiType(): string
    {
        return 'library-vendors';
    }

    protected function jsonAttributes(Request $request): array
    {
        return [
            'vendor_code'    => $this->vendor_code,
            'name'           => $this->name,
            'vendor_type'    => $this->vendor_type,
            'account_number' => $this->account_number,
            'contact_name'   => $this->contact_name,
            'email'          => $this->email,
            'phone'          => $this->phone,
            'website'        => $this->website,
            'address'        => $this->address,
            'city'           => $this->city,
            'country'        => $this->country,
            'currency'       => $this->currency,
            'san'            => $this->san,
            'notes'          => $this->notes,
            'is_active'      => (bool) $this->is_active,
            'created_at'     => optional($this->created_at)->toIso8601String(),
            'updated_at'     => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
