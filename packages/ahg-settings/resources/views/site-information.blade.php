@extends('theme::layouts.2col')
@section('title', 'Site information')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Site information') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.site-information') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="site-information-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#site-information-collapse" aria-expanded="false">
              {{ __('Site information settings') }}
            </button>
          </h2>
          <div id="site-information-collapse" class="accordion-collapse collapse" aria-labelledby="site-information-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Site title <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="siteTitle" class="form-control" value="{{ e($settings['siteTitle']) }}">
                <small class="text-muted">{{ __('The name of the website for display in the header') }}</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Site description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="siteDescription" class="form-control" value="{{ e($settings['siteDescription']) }}">
                <small class="text-muted">{{ __('A brief site description or "tagline" for the header') }}</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Site base URL (used in MODS and EAD exports) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="siteBaseUrl" class="form-control" value="{{ e($settings['siteBaseUrl']) }}">
                <small class="text-muted">{{ __('Used to create absolute URLs, pointing to resources, in XML exports') }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
@endsection
