@php
    // Set a default if it has been defined
    $currentSort = request()->input('sort', '');
    if (empty($currentSort) && !empty($default)) {
        $currentSort = $name . ucfirst($default);
    }

    $up = "{$name}Up";
    $down = "{$name}Down";
    $next = $currentSort !== $up ? $up : $down;
@endphp

<th class="sortable" width="{{ $size ?? '' }}">

  <a href="{{ request()->fullUrlWithQuery(['sort' => $next]) }}" title="{{ __('Sort') }}" class="sortable">{{ $label }}</a>

  @if($up === $currentSort)
    <img src="/images/up.gif" alt="{{ __('Sort ascending') }}">
  @elseif($down === $currentSort)
    <img src="/images/down.gif" alt="{{ __('Sort descending') }}">
  @endif

</th>
