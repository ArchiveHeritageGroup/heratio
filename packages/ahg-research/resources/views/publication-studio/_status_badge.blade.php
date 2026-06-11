{{--
  Publication Studio status badge - Heratio ahg-research (heratio#1232)
  Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems
  Licensed under the GNU Affero General Public License v3 or later.
--}}
@php
    $map = [
        'drafting'  => 'secondary',
        'submitted' => 'primary',
        'reviewed'  => 'info',
        'revised'   => 'warning',
        'accepted'  => 'success',
        'published' => 'success',
        'rejected'  => 'danger',
    ];
    $cls = $map[$status ?? ''] ?? 'secondary';
@endphp
<span class="badge bg-{{ $cls }} text-nowrap">{{ __(ucfirst($status ?? 'drafting')) }}</span>
