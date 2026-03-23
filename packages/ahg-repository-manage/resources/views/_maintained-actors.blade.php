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
    <div class="card-body p-2 text-center small">
      {{ $actorPager->currentPage() }}/{{ $actorPager->lastPage() }}
    </div>
  @endif

  <div class="card-body p-0">
    <a class="btn atom-btn-white border-0 w-100" href="{{ $actorMoreUrl }}">
      <i class="fas fa-search me-1" aria-hidden="true"></i>
      {{ __('Browse :count results', ['count' => $actorPager->total()]) }}
    </a>
  </div>

</section>
@endif
