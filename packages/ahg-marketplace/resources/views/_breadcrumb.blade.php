{{--
  Partial: breadcrumb (ported from atom-ahg-plugins/ahgMarketplacePlugin/_breadcrumb.php)

  Variables:
    $items (array) Array of ['label' => '...', 'url' => '...'] pairs.
                   The last item should omit 'url' (rendered as active).
--}}
@php
  $items = $items ?? [];
  $count = count($items);
@endphp
@if ($count > 0)
<nav aria-label="{{ __('Breadcrumb') }}" class="mkt-breadcrumb mb-3">
  <ol class="breadcrumb mb-0">
    @foreach ($items as $idx => $item)
      @php $isLast = ($idx === $count - 1); @endphp
      @if ($isLast || empty($item['url']))
        <li class="breadcrumb-item active" aria-current="page">{{ $item['label'] }}</li>
      @else
        <li class="breadcrumb-item"><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
      @endif
    @endforeach
  </ol>
</nav>
@endif
