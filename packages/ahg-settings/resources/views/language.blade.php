@extends('theme::layouts.1col')
@section('title', 'I18n languages')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-language me-2"></i>I18n languages</h1>

    <div class="alert alert-info">
      <p>Please rebuild the search index if you are adding new languages.</p>
      <pre class="mb-0">$ php symfony search:populate</pre>
    </div>

    <form method="post" action="{{ route('settings.languages') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#lang-collapse">i18n language settings</button></h2>
          <div id="lang-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              @foreach($languages as $lang)
                <div class="row mb-3">
                  <label class="col-11 col-form-label">
                    {{ $lang->name ?? $lang->value ?? '?' }}
                    <code class="ms-1">{{ $lang->name }}</code>
                  </label>
                  <div class="col-1 px-2 text-end">
                    @if(isset($lang->deleteable) && $lang->deleteable)
                      <button type="submit" name="action" value="delete" class="btn atom-btn-white btn-sm" onclick="document.getElementById('delete_id').value='{{ $lang->id }}'">
                        <i class="fas fa-times"></i>
                      </button>
                    @else
                      <span class="btn disabled"><i class="fas fa-lock"></i></span>
                    @endif
                  </div>
                </div>
              @endforeach

              <hr>

              <div class="mb-3">
                <label class="form-label">Add language <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="input-group" style="max-width:300px;">
                  <input type="text" name="languageCode" class="form-control" placeholder="e.g. fr, de, af" pattern="[a-z]{2,3}">
                  <button type="submit" name="action" value="add" class="btn atom-btn-outline-success">Add</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <input type="hidden" name="delete_id" id="delete_id" value="">
    </form>
  </div>
</div>
@endsection
