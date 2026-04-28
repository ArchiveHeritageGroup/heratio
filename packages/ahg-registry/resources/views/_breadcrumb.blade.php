{{--
  Registry breadcrumb partial.
  Vars: $items = [['label' => '...', 'url' => '...'], ...]
  Skips a leading "Home" entry per AtoM convention.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $crumbs = collect($items ?? [])->reject(fn ($c) => ($c['label'] ?? '') === __('Home'))->values();
@endphp
@if ($crumbs->isNotEmpty())
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        @foreach ($crumbs as $i => $c)
            @if ($i === $crumbs->count() - 1)
                <li class="breadcrumb-item active" aria-current="page">{{ $c['label'] ?? '' }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ $c['url'] ?? '#' }}">{{ $c['label'] ?? '' }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
@endif
