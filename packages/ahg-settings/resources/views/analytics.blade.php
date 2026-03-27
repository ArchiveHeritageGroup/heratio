@extends('theme::layout_2col')

@section('sidebar')
  @include('ahg-settings::_menu')
@endsection

@section('title')
  <h1>{{ __('Web analytics') }}</h1>
@endsection

@section('content')

  <div class="alert alert-info">
    {{ __('Please clear the cache and restart PHP-FPM after adding tracking ID.') }}
  </div>

  @if(!empty(config('atom.google_analytics_api_key')) && empty(\AhgCore\Models\Setting::getByName('google_analytics')))
    <div class="alert alert-info">
      {{ __('Google analytics is currently set in the app.yml.') }}
    </div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
      @endforeach
    </div>
  @endif

  <form method="post" action="{{ route('settings.analytics') }}">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="analytics-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#analytics-collapse" aria-expanded="true" aria-controls="analytics-collapse">
            {{ __('Web analytics') }}
          </button>
        </h2>
        <div id="analytics-collapse" class="accordion-collapse collapse show" aria-labelledby="analytics-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Google Analytics tracking ID') }}</label>
              <input type="text" name="google_analytics" class="form-control" value="{{ old('google_analytics', $googleAnalytics ?? '') }}" placeholder="G-XXXXXXXXXX or UA-XXXXXXXX-X">
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="{{ __('Save') }}">
    </section>

  </form>

@endsection
