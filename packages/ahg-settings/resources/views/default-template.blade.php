@extends('theme::layouts.1col')
@section('title', 'Default template')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Default template</h1>


    <form method="post" action="{{ route('settings.default-template') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="default-template-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#default-template-collapse" aria-expanded="true" aria-controls="default-template-collapse">
              Default template settings
            </button>
          </h2>
          <div id="default-template-collapse" class="accordion-collapse collapse show" aria-labelledby="default-template-heading">
            <div class="accordion-body">

              @if(isset($templateSettings['informationobject']))
                <div class="mb-3">
                  <label class="form-label">Information object</label>
                  <select name="settings[{{ $templateSettings['informationobject']->id }}]" class="form-select">
                    @foreach($ioChoices as $val => $label)
                      <option value="{{ $val }}" {{ ($templateSettings['informationobject']->value ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

              @if(isset($templateSettings['actor']))
                <div class="mb-3">
                  <label class="form-label">Actor</label>
                  <select name="settings[{{ $templateSettings['actor']->id }}]" class="form-select">
                    @foreach($actorChoices as $val => $label)
                      <option value="{{ $val }}" {{ ($templateSettings['actor']->value ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

              @if(isset($templateSettings['repository']))
                <div class="mb-3">
                  <label class="form-label">Repository</label>
                  <select name="settings[{{ $templateSettings['repository']->id }}]" class="form-select">
                    @foreach($repoChoices as $val => $label)
                      <option value="{{ $val }}" {{ ($templateSettings['repository']->value ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      </section>

    </form>
  </div>
</div>
@endsection
