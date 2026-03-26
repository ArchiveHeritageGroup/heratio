@if(!isset($aggs[$name]) || (!isset($filters[$name]) && (count($aggs[$name]) < 2 || ('languages' == $name && count($aggs[$name]) < 3))))
  @php return; @endphp
@endif

@php $openned = (request()->has($name) || (isset($open) && $open && 0 < count($aggs[$name]))); @endphp

<div class="accordion mb-3">
  <div class="accordion-item aggregation">
    <h2 class="accordion-header" id="heading-{{ $name }}">
      <button
        class="accordion-button{{ $openned ? '' : ' collapsed' }}"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#collapse-{{ $name }}"
        aria-expanded="{{ $openned ? 'true' : 'false' }}"
        aria-controls="collapse-{{ $name }}">
        {{ $label }}
      </button>
    </h2>
    <div
      id="collapse-{{ $name }}"
      class="accordion-collapse collapse{{ $openned ? ' show' : '' }} list-group list-group-flush"
      aria-labelledby="heading-{{ $name }}">

      @if('languages' !== $name)
        @php
          $allParams = array_merge(request()->all(), [$name => null, 'page' => null]);
          $isActive = !isset($filters[$name]);
        @endphp
        <a href="{{ request()->fullUrlWithQuery([$name => null, 'page' => null]) }}"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center{{ $isActive ? ' active text-decoration-underline' : '' }}">
          {{ __('All') }}
        </a>
      @endif

      @foreach($aggs[$name] as $bucket)
        @php $active = ((isset($filters[$name]) && $filters[$name] == $bucket['key'])
            || (!isset($filters[$name]) && 'unique_language' == $bucket['key'])); @endphp

        <a href="{{ request()->fullUrlWithQuery(['page' => null, $name => 'unique_language' == $bucket['key'] ? null : $bucket['key']]) }}"
           class="list-group-item list-group-item-action d-flex justify-content-between align-items-center text-break{{ $active ? ' active text-decoration-underline' : '' }}">
          {{ __(strip_tags($bucket['display'])) }}
          <span class="visually-hidden">, {{ $bucket['doc_count'] }} {{ __('results') }}</span>
          <span aria-hidden="true" class="ms-3 text-nowrap">{{ $bucket['doc_count'] }}</span>
        </a>
      @endforeach
    </div>
  </div>
</div>
