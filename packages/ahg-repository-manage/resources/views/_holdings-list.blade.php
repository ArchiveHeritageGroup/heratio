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
    <nav class="card-body border-bottom p-2 small" aria-label="Pagination">
      <p class="text-center mb-1">
        Results <span class="result-start">{{ ($holdingsPager->currentPage() - 1) * $holdingsPager->perPage() + 1 }}</span>
        to <span class="result-end">{{ min($holdingsPager->currentPage() * $holdingsPager->perPage(), $holdingsPager->total()) }}</span>
        of {{ $holdingsPager->total() }}
      </p>
      <ul class="pagination pagination-sm justify-content-center mb-2">
        <li class="page-item {{ $holdingsPager->currentPage() <= 1 ? 'disabled' : '' }}">
          <a class="page-link page-link-prev" href="{{ $holdingsPager->currentPage() > 1 ? request()->fullUrlWithQuery(['holdings_page' => $holdingsPager->currentPage() - 1]) : '#' }}" aria-label="Previous">
            <i aria-hidden="true" class="fas fa-arrow-left"></i>
          </a>
        </li>
        <li class="page-item my-0 mx-0 text-center">
          <span class="px-2">{{ $holdingsPager->currentPage() }}</span>
          <span>of {{ $holdingsPager->lastPage() }}</span>
        </li>
        <li class="page-item {{ $holdingsPager->currentPage() >= $holdingsPager->lastPage() ? 'disabled' : '' }}">
          <a class="page-link page-link-next" href="{{ $holdingsPager->currentPage() < $holdingsPager->lastPage() ? request()->fullUrlWithQuery(['holdings_page' => $holdingsPager->currentPage() + 1]) : '#' }}" aria-label="Next">
            <i aria-hidden="true" class="fas fa-arrow-right"></i>
          </a>
        </li>
      </ul>
    </nav>
  @endif

  <div class="card-body p-0">
    <a class="btn atom-btn-white border-0 w-100" href="{{ route('informationobject.browse', ['repos' => $resource->id]) }}">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Browse :count results', ['count' => $holdingsPager->total()]) }}
    </a>
  </div>

</section>
@endif
