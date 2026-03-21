@extends('theme::layouts.1col')
@section('title', 'Site information')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Site information</h1>

    <form method="post" action="{{ route('settings.site-information') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="site-information-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#site-information-collapse" aria-expanded="false">
              Site information settings
            </button>
          </h2>
          <div id="site-information-collapse" class="accordion-collapse collapse" aria-labelledby="site-information-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Site title</label>
                <input type="text" name="siteTitle" class="form-control" value="{{ e($settings['siteTitle']) }}">
                <small class="text-muted">The name of the website for display in the header</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Site description</label>
                <input type="text" name="siteDescription" class="form-control" value="{{ e($settings['siteDescription']) }}">
                <small class="text-muted">A brief site description or "tagline" for the header</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Site base URL (used in MODS and EAD exports)</label>
                <input type="text" name="siteBaseUrl" class="form-control" value="{{ e($settings['siteBaseUrl']) }}">
                <small class="text-muted">Used to create absolute URLs, pointing to resources, in XML exports</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="actions mb-3" style="background:#495057 !important;border-radius:.375rem;padding:1rem;display:block;">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </div>
    </form>
  </div>
</div>
@endsection
