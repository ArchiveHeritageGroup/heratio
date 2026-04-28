{{--
  Vendor transaction status badge.
  Vars: $status (string code, e.g. 'pending_approval').

  Display config (label, color, icon) is sourced from
  ahg_dropdown taxonomy 'vendor_transaction_status' via VendorStatusService.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $cfg = app(\AhgVendor\Services\VendorStatusService::class)->display((string) ($status ?? ''));
    $inlineColor = $cfg['color'] === 'secondary' && $cfg['color_hex']
        ? "background-color: {$cfg['color_hex']} !important; color: #fff;"
        : '';
@endphp
<span class="badge bg-{{ $cfg['color'] }}" @if ($inlineColor) style="{{ $inlineColor }}" @endif>
    <i class="fas fa-{{ $cfg['icon'] }} me-1"></i>{{ $cfg['label'] }}
</span>
