@extends('ahg-theme-b5::layout_2col')

@section('title')
  <h1>{{ $resource->getTitle(['cultureFallback' => true]) }}</h1>
@endsection

@section('sidebar')
  @include('ahg-menu-manage::_static-pages-menu')

  @php $browseMenu = \AhgCore\Models\Menu::find(\AhgCore\Constants\QubitMenu::BROWSE_ID); @endphp
  @if($browseMenu && $browseMenu->hasChildren())
    <section class="card mb-3">
      <h2 class="h5 p-3 mb-0">
        {{ __('Browse by') }}
      </h2>
      <div class="list-group list-group-flush">
        @foreach($browseMenu->getChildren() as $item)
          <a class="list-group-item list-group-item-action" href="{{ $item->getPath(['getUrl' => true, 'resolveAlias' => true]) }}">
            {{ e($item->getLabel(['cultureFallback' => true])) }}
          </a>
        @endforeach
      </div>
    </section>
  @endif

  @include('ahg-core::_popular', [
      'limit' => 10,
  ])
@endsection

{{-- Featured collection carousel - provided by ahg-iiif-collection --}}
@if(class_exists('\\AhgIiifCollection\\AhgIiifCollectionServiceProvider'))
  @include('ahg-iiif-collection::_featured-collection')
@endif

<div class="page p-3">
  {!! $content !!}
</div>

@if(Auth::check() && Auth::user()->can('update', $resource))
  @section('after-content')
    <section class="actions mb-3">
      <a href="{{ route('staticpage.edit', ['slug' => $resource->slug]) }}" class="btn atom-btn-outline-light">{{ __('Edit') }}</a>
    </section>
  @endsection
@endif
