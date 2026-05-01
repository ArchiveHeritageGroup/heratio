@extends('theme::layouts.1col')

@section('title', __('My Plugins'))
@section('body-class', 'view profile-plugins')

@section('content')
  <h1>{{ __('My Plugins') }}</h1>
  <p class="text-muted">
    {{ __('Hide globally-enabled plugins from your own navigation. Other users are unaffected.') }}
    {{ __('Admins can still enable/disable plugins globally at') }} <a href="{{ route('settings.plugins') }}">{{ __('Settings → Plugins') }}</a>.
  </p>

  @if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
  @endif

  <form method="POST" action="{{ route('user.plugin-preferences.save') }}">
    @csrf

    @php
      $byCategory = collect($plugins)->groupBy(fn($p) => $p->category ?: 'general');
    @endphp

    @foreach($byCategory as $category => $items)
      <fieldset class="mb-4 border rounded p-3">
        <legend class="float-none w-auto fs-5 px-2">{{ ucfirst($category) }}</legend>

        @foreach($items as $plugin)
          <div class="form-check py-1">
            <input
              type="checkbox"
              class="form-check-input"
              id="plugin-{{ $plugin->name }}"
              name="hidden[]"
              value="{{ $plugin->name }}"
              {{ in_array($plugin->name, $hidden, true) ? 'checked' : '' }}>
            <label class="form-check-label" for="plugin-{{ $plugin->name }}">
              <strong>{{ $plugin->name }}</strong>
              @if($plugin->description)
                <span class="text-muted small d-block">{{ $plugin->description }}</span>
              @endif
            </label>
          </div>
        @endforeach
      </fieldset>
    @endforeach

    <p class="text-muted small mb-3">
      {{ __('Tick to HIDE the plugin from your nav. Untick to show it (the default).') }}
    </p>

    <button type="submit" class="btn btn-primary">{{ __('Save preferences') }}</button>
    <a href="{{ url()->previous() }}" class="btn btn-link">{{ __('Cancel') }}</a>
  </form>
@endsection
