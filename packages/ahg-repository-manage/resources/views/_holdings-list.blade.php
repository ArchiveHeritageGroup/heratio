@php
  $holdingsLabel = \AhgCore\Services\SettingHelper::get('ui_label_holdings', 'Holdings');
  $holdingsPager = $holdingsPager ?? null;
  $holdings = $holdings ?? collect();
@endphp

@if($holdingsPager && $holdingsPager->total() > 0)
<section class="card sidebar-paginated-list mb-3"
  data-total-pages="{{ $holdingsPager->lastPage() }}"
  data-url="{{ route('repository.show', ['slug' => $resource->slug]) }}">

  <h5 class="p-3 mb-0">
    {{ $holdingsLabel }}
    <span class="d-none spinner">
      <i class="fas fa-spinner fa-spin ms-2" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Loading ...') }}</span>
    </span>
  </h5>

  <ul class="list-group list-group-flush">
    @foreach($holdings as $item)
      <a href="{{ route('informationobject.show', ['slug' => $item->slug]) }}" class="list-group-item list-group-item-action">
        {{ $item->title ?? '[Untitled]' }}
      </a>
    @endforeach
  </ul>

  @if($holdingsPager->lastPage() > 1)
    <div class="card-body p-2 text-center small">
      {{ $holdingsPager->currentPage() }}/{{ $holdingsPager->lastPage() }}
    </div>
  @endif

  <div class="card-body p-0">
    <a class="btn atom-btn-white border-0 w-100" href="{{ route('informationobject.browse', ['repos' => $resource->id]) }}">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Browse :count results', ['count' => $holdingsPager->total()]) }}
    </a>
  </div>

</section>
@endif
