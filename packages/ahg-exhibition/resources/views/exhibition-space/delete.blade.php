{{-- heratio#146 — Exhibition space delete confirmation --}}
@extends('theme::layouts.1col')

@section('title', __('Delete :name', ['name' => $space->name]))
@section('body-class', 'delete exhibition-space')

@section('content')
  <h1>{{ __('Delete exhibition space') }}</h1>

  <div class="alert alert-warning">
    {{ __('You are about to delete :name. This cannot be undone.', ['name' => $space->name]) }}
  </div>

  @if($placementCount > 0)
    <div class="alert alert-danger">
      <strong>{{ __('Blocked:') }}</strong>
      {{ trans_choice(
          ':count placement is still attached to this space. Remove placements first.|:count placements are still attached to this space. Remove placements first.',
          $placementCount, ['count' => $placementCount]) }}
      <div class="mt-2">
        <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-sm btn-outline-secondary">
          {{ __('Back to space') }}
        </a>
      </div>
    </div>
  @else
    <form method="post" action="{{ route('exhibition-space.destroy', ['slug' => $space->slug]) }}"
          onsubmit="return confirm('{{ __('Are you sure you want to delete this exhibition space?') }}');">
      @csrf
      @method('DELETE')
      <button type="submit" class="btn btn-danger">
        <i class="fas fa-trash me-1"></i>{{ __('Delete exhibition space') }}
      </button>
      <a href="{{ route('exhibition-space.show', ['slug' => $space->slug]) }}" class="btn btn-outline-secondary">
        {{ __('Cancel') }}
      </a>
    </form>
  @endif
@endsection
