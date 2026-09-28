{{-- heratio#1514 - storage location tree (recursive) --}}
@if(count($tree) > 0)
  <ul class="list-unstyled {{ ($nested ?? false) ? 'ms-3 mt-1' : 'mb-0' }}">
    @foreach($tree as $node)
      <li class="mb-1">
        <a href="{{ route('storagelocation.show', ['slug' => $node->slug]) }}">{{ $node->name }}</a>
        <span class="badge bg-secondary ms-1">{{ $types[$node->location_type] ?? $node->location_type }}</span>
        @if(! empty($node->children))
          @include('ahg-storage-manage::storage-location._tree', ['tree' => $node->children, 'nested' => true])
        @endif
      </li>
    @endforeach
  </ul>
@elseif(! ($nested ?? false))
  <p class="text-muted mb-0">{{ __('No storage locations yet.') }}</p>
@endif
