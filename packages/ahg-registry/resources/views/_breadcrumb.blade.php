{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_breadcrumb.php --}}
@php
    // Skip the Home breadcrumb if present
    $rawItems = !empty($items) ? (array) $items : [];
    $breadcrumbItems = [];
    foreach ($rawItems as $crumb) {
        $c = (array) $crumb;
        if (($c['label'] ?? '') !== __('Home')) {
            $breadcrumbItems[] = $c;
        }
    }
@endphp
@if (!empty($breadcrumbItems))
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    @foreach ($breadcrumbItems as $i => $crumb)
      @if ($i === count($breadcrumbItems) - 1)
        <li class="breadcrumb-item active" aria-current="page">{!! $crumb['label'] !!}</li>
      @else
        <li class="breadcrumb-item"><a href="{{ $crumb['url'] ?? '#' }}">{!! $crumb['label'] !!}</a></li>
      @endif
    @endforeach
  </ol>
</nav>
@endif
