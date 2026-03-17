@extends('theme::layouts.1col')
@section('title', 'Visible elements')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Visible elements</h1>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="post" action="{{ route('settings.visible-elements') }}">
      @csrf

      <div class="accordion mb-3" id="visElementsAccordion">
        @php
          $groupLabels = [
            'dacs' => 'DACS',
            'isad' => 'ISAD(G)',
            'rad' => 'RAD',
            'dc' => 'Dublin Core',
            'mods' => 'MODS',
            'digital' => 'Digital object metadata',
            'physical' => 'Physical storage',
          ];
          $idx = 0;
        @endphp

        @foreach($groups as $prefix => $groupSettings)
          @php $idx++; $label = $groupLabels[$prefix] ?? ucfirst($prefix); @endphp
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading{{ $idx }}">
              <button class="accordion-button {{ $idx > 1 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{ $idx }}" aria-expanded="{{ $idx === 1 ? 'true' : 'false' }}">
                {{ $label }}
              </button>
            </h2>
            <div id="collapse{{ $idx }}" class="accordion-collapse collapse {{ $idx === 1 ? 'show' : '' }}" aria-labelledby="heading{{ $idx }}" data-bs-parent="#visElementsAccordion">
              <div class="accordion-body">
                @foreach($groupSettings as $setting)
                  <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="settings[{{ $setting->id }}]" value="1" id="ve_{{ $setting->id }}" {{ ($setting->value ?? '0') == '1' ? 'checked' : '' }}>
                    <label class="form-check-label" for="ve_{{ $setting->id }}">
                      {{ ucwords(str_replace('_', ' ', preg_replace('/^[a-z]+_/', '', $setting->name))) }}
                    </label>
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <section class="actions mb-3">
        <button type="submit" class="btn btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      </section>
    </form>
  </div>
</div>
@endsection
