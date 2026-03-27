@extends('theme::layouts.1col')

@section('title')
  <h1>{{ __('Move %1%', ['%1%' => $resource->authorized_form_of_name ?? $resource->title ?? '']) }}</h1>
@endsection

@section('before-content')
  <div class="d-inline-block mb-3">
    @include('ahg-core::components.inline-search', [
        'label' => __('Search title or identifier'),
        'landmarkLabel' => config('app.ui_label_informationobject', __('Archival description')),
        'route' => '/informationobject/' . ($resource->slug ?? '') . '/move',
    ])
  </div>

  @if(isset($parent->ancestors) && count($parent->ancestors) > 0)
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        @foreach($parent->ancestors as $item)
          @if(isset($item->parent))
            <li class="breadcrumb-item"><a href="/informationobject/{{ $resource->slug ?? '' }}/move?parent={{ $item->slug ?? '' }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a></li>
          @endif
        @endforeach
        @if(isset($parent->parent))
          <li class="breadcrumb-item active" aria-current="page">{{ $parent->authorized_form_of_name ?? $parent->title ?? '' }}</li>
        @endif
      </ol>
    </nav>
  @endif
@endsection

@section('content')
  @if(isset($results) && count($results))
    <div class="table-responsive mb-3">
      <table class="table table-bordered mb-0">
        <thead>
          <tr>
            <th>{{ __('Identifier') }}</th>
            <th>{{ __('Title') }}</th>
          </tr>
        </thead>
        <tbody>
          @foreach($results as $item)
            <tr>
              <td width="15%">
                {{ $item->identifier ?? '' }}
              </td>
              <td width="85%">
                @if(($resource->lft ?? 0) > ($item->lft ?? 0) || ($resource->rgt ?? 0) < ($item->rgt ?? 0))
                  <a href="/informationobject/{{ $resource->slug ?? '' }}/move?parent={{ $item->slug ?? '' }}">{{ $item->authorized_form_of_name ?? $item->title ?? '' }}</a>
                @else
                  {{ $item->authorized_form_of_name ?? $item->title ?? '' }}
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endsection

@section('after-content')
  @if(isset($pager))
    @include('ahg-core::components.pager', ['pager' => $pager])
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="/informationobject/{{ $resource->slug ?? '' }}/move">

    @csrf

    <input type="hidden" name="parent" value="{{ $parent->slug ?? $parent->id ?? '' }}">

    <ul class="actions mb-3 nav gap-2">
      <li><input class="btn atom-btn-outline-success" type="submit" value="{{ __('Move here') }}"></li>
      <li><a href="{{ isset($resource->slug) ? route('informationobject.show', $resource->slug) : url()->previous() }}" class="btn atom-btn-outline-light" role="button">{{ __('Cancel') }}</a></li>
    </ul>

  </form>
@endsection
