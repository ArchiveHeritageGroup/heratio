{{-- heratio#1514 - storage location delete confirmation (port of AtoM storageLocation/delete) --}}
@extends('theme::layouts.1col')

@section('title', __('Delete :name', ['name' => $location->name]))
@section('body-class', 'delete storagelocation')

@section('content')
  <h1 class="mb-2">{{ __('Delete storage location') }}</h1>
  <div class="mb-3">@include('ahg-storage-manage::storage-location._path', ['path' => $path])</div>

  @if(count($children) > 0)
    <div class="alert alert-warning">
      <p class="mb-2"><strong>{{ __(':name cannot be deleted while it holds other locations.', ['name' => $location->name]) }}</strong>
        {{ __('Delete or move these first:') }}</p>
      <ul class="mb-0">
        @foreach($descendants as $d)
          <li style="margin-left: {{ 1.25 * max(0, (int) $d->level - (int) $location->level - 1) }}rem;">
            <a href="{{ route('storagelocation.show', ['slug' => $d->slug]) }}">{{ $d->name }}</a>
            ({{ $types[$d->location_type] ?? $d->location_type }})
          </li>
        @endforeach
      </ul>
    </div>
    <a href="{{ route('storagelocation.show', ['slug' => $location->slug]) }}" class="btn btn-outline-secondary">{{ __('Back') }}</a>
  @else
    <div class="alert alert-danger">
      {{ __('Delete :name (:type)? This cannot be undone.', ['name' => $location->name, 'type' => $types[$location->location_type] ?? $location->location_type]) }}
    </div>
    <form method="post" action="{{ route('storagelocation.destroy', ['slug' => $location->slug]) }}" class="d-inline">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-danger">{{ __('Delete location') }}</button>
    </form>
    <a href="{{ route('storagelocation.show', ['slug' => $location->slug]) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
  @endif
@endsection
