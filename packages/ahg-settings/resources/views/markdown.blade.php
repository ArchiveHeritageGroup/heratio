@extends('theme::layouts.2col')
@section('title', 'Markdown')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>Markdown</h1>
@endsection

@section('content')
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
                <label class="form-label">Enable Markdown support <span class="badge bg-secondary ms-1">Optional</span></label>
                <div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enabled]" id="md_no" value="0" {{ $settings['enabled'] != '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="md_no">No <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="settings[enabled]" id="md_yes" value="1" {{ $settings['enabled'] == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="md_yes">Yes <span class="badge bg-secondary ms-1">Optional</span></label>
                  </div>
                </div>
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
