@extends('theme::layouts.2col')
@section('title', 'Default template')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Default template') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.default-template') }}">
      @csrf

      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header" id="default-template-heading">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#default-template-collapse" aria-expanded="true" aria-controls="default-template-collapse">
              {{ __('Default template settings') }}
            </button>
          </h2>
          <div id="default-template-collapse" class="accordion-collapse collapse show" aria-labelledby="default-template-heading">
            <div class="accordion-body">

              @if(isset($templateSettings['informationobject']))
                <div class="mb-3">
                  <label class="form-label">Information object <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="settings[{{ $templateSettings['informationobject']->id }}]" class="form-select">
                    @foreach($ioChoices as $val => $label)
                      <option value="{{ $val }}" {{ ($templateSettings['informationobject']->value ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

              @if(isset($templateSettings['actor']))
                <div class="mb-3">
                  <label class="form-label">Actor <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="settings[{{ $templateSettings['actor']->id }}]" class="form-select">
                    @foreach($actorChoices as $val => $label)
                      <option value="{{ $val }}" {{ ($templateSettings['actor']->value ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                  </select>
                </div>
              @endif

              @if(isset($templateSettings['repository']))
                <div class="mb-3">
                  <label class="form-label">Repository <span class="badge bg-secondary ms-1">Optional</span></label>
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
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>

    </form>
@endsection
