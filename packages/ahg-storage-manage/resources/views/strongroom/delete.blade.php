{{-- heratio#144 — Strongroom delete confirmation --}}
@extends('theme::layouts.1col')

@section('title', __('Delete :name', ['name' => $room->name]))
@section('body-class', 'delete strongroom')

@section('content')
  <h1>{{ __('Delete strongroom') }}</h1>

  <div class="alert alert-warning">
    {{ __('You are about to delete :name. This cannot be undone.', ['name' => $room->name]) }}
  </div>

  @if($occupantCount > 0)
    <div class="alert alert-danger">
      <strong>{{ __('Blocked:') }}</strong>
      {{ trans_choice(
            ':count physical object is still assigned to this strongroom. Move it out before deleting.|:count physical objects are still assigned to this strongroom. Move them out before deleting.',
            $occupantCount, ['count' => $occupantCount]) }}
      <div class="mt-2">
        <a href="{{ route('strongroom.show', ['slug' => $room->slug]) }}" class="btn btn-sm btn-outline-secondary">
          {{ __('Back to strongroom') }}
        </a>
      </div>
    </div>
  @else
    <form method="post" action="{{ route('strongroom.destroy', ['slug' => $room->slug]) }}"
          onsubmit="return confirm('{{ __('Are you sure you want to delete this strongroom?') }}');">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-danger">
        <i class="fas fa-trash me-1"></i>{{ __('Delete strongroom') }}
      </button>
      <a href="{{ route('strongroom.show', ['slug' => $room->slug]) }}" class="btn btn-outline-secondary">
        {{ __('Cancel') }}
      </a>
    </form>
  @endif
@endsection
