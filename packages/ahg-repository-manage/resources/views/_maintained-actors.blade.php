@php
  $actors = $actors ?? collect();
  $actorLabel = $list['label'] ?? __('Maintained actors');
  $actorMoreUrl = $list['moreUrl'] ?? '#';
  $actorPager = $list['pager'] ?? null;
  $actorItems = $list['items'] ?? collect();
@endphp

@if($actorPager && $actorPager->total() > 0)
<section class="card sidebar-paginated-list mb-3"
  data-total-pages="{{ $actorPager->lastPage() }}"
  data-url="{{ $list['dataUrl'] ?? '#' }}">

  <h5 class="p-3 mb-0">
    {{ $actorLabel }}
    <span class="d-none spinner">
      <i class="fas fa-spinner fa-spin ms-2" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Loading ...') }}</span>
    </span>
  </h5>

  <ul class="list-group list-group-flush">
    @foreach($actorItems as $actor)
      <a href="{{ route('actor.show', ['slug' => $actor->slug]) }}" class="list-group-item list-group-item-action">
        {{ $actor->authorized_form_of_name ?? '[Untitled]' }}
      </a>
    @endforeach
  </ul>

  @if($actorPager->lastPage() > 1)
    <nav class="card-body border-bottom p-2 small" aria-label="Pagination">
      <p class="text-center mb-1">
        Results <span class="result-start">{{ ($actorPager->currentPage() - 1) * $actorPager->perPage() + 1 }}</span>
        to <span class="result-end">{{ min($actorPager->currentPage() * $actorPager->perPage(), $actorPager->total()) }}</span>
        of {{ $actorPager->total() }}
      </p>
      <ul class="pagination pagination-sm justify-content-center mb-2">
        <li class="page-item {{ $actorPager->currentPage() <= 1 ? 'disabled' : '' }}">
          <a class="page-link page-link-prev" href="{{ $actorPager->currentPage() > 1 ? request()->fullUrlWithQuery(['actors_page' => $actorPager->currentPage() - 1]) : '#' }}" aria-label="Previous">
            <i aria-hidden="true" class="fas fa-arrow-left"></i>
          </a>
        </li>
        <li class="page-item my-0 mx-0 text-center">
          <span class="px-2">{{ $actorPager->currentPage() }}</span>
          <span>of {{ $actorPager->lastPage() }}</span>
        </li>
        <li class="page-item {{ $actorPager->currentPage() >= $actorPager->lastPage() ? 'disabled' : '' }}">
          <a class="page-link page-link-next" href="{{ $actorPager->currentPage() < $actorPager->lastPage() ? request()->fullUrlWithQuery(['actors_page' => $actorPager->currentPage() + 1]) : '#' }}" aria-label="Next">
            <i aria-hidden="true" class="fas fa-arrow-right"></i>
          </a>
        </li>
      </ul>
    </nav>
  @endif

  <div class="card-body p-0">
    <a class="btn atom-btn-white border-0 w-100" href="{{ $actorMoreUrl }}">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Browse :count results', ['count' => $actorPager->total()]) }}
    </a>
  </div>

</section>
@endif
