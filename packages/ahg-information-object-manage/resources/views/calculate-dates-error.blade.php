@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-information-object-manage::_context-menu')
@endsection

@section('title')
  <h1>{{ __('Calculate dates - Error') }}</h1>
@endsection

@section('content')
  <form action="{{ route('informationobject.calculateDates', $resource->slug) }}" method="post">
    @csrf
    @if(1 == ($resource->rgt ?? 0) - ($resource->lft ?? 0) || 0 == count($descendantEventTypes ?? []))
      <div id="content" class="p-3">
        @if(1 == ($resource->rgt ?? 0) - ($resource->lft ?? 0))
            {{ __(
                'Cannot calculate accumulated dates because this %1% has no children',
                ['%1%' => config('app.ui_label_informationobject', 'archival description')]
            ) }}
        @else
          {{ __('Cannot calculate accumulated dates because no lower level dates exist') }}
        @endif
      </div>
    @endif

    <section class="actions mb-3">
      <a href="{{ route('informationobject.show', $resource->slug) }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a>
    </section>
  </form>
@endsection
