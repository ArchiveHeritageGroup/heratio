@extends('theme::layouts.1col')
@section('title', 'Markdown')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Markdown</h1>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="alert alert-info">
      <p>Please rebuild the search index if you are enabling/disabling Markdown support.</p>
      <pre>$ php artisan search:populate</pre>
    </div>

    <form method="post" action="{{ route('settings.markdown') }}">
      @csrf

      <div class="accordion mb-3" id="markdownAccordion">
        <div class="accordion-item">
          <h2 class="accordion-header" id="markdown-heading">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#markdown-collapse" aria-expanded="false" aria-controls="markdown-collapse">
              Markdown settings
            </button>
          </h2>
          <div id="markdown-collapse" class="accordion-collapse collapse" aria-labelledby="markdown-heading">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Enable Markdown support</label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enabled]" id="md_no" value="0" {{ $settings['enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="md_no">No</label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enabled]" id="md_yes" value="1" {{ $settings['enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="md_yes">Yes</label>
                  </div>
                </div>
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
