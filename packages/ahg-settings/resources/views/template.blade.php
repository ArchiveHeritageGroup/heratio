@extends('theme::layouts.1col')
@section('title', 'Default template')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <h1><i class="fas fa-file-alt me-2"></i>Default template</h1>

    <form method="post" action="{{ route('settings.default-template') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#template-collapse">Default template settings</button></h2>
          <div id="template-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">Information object <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[{{ $templateSettings['informationobject']->id ?? '' }}]" class="form-select">
                  @foreach($ioChoices as $val => $label)
                    <option value="{{ $val }}" {{ ($templateSettings['informationobject']->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Actor <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[{{ $templateSettings['actor']->id ?? '' }}]" class="form-select">
                  @foreach($actorChoices as $val => $label)
                    <option value="{{ $val }}" {{ ($templateSettings['actor']->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Repository <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="settings[{{ $templateSettings['repository']->id ?? '' }}]" class="form-select">
                  @foreach($repoChoices as $val => $label)
                    <option value="{{ $val }}" {{ ($templateSettings['repository']->value ?? '') == $val ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
