@extends('theme::layouts.2col')
@section('title', $sectionLabel)
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ $sectionLabel }}</h1>
@endsection

@section('content')
    @if($settings->isEmpty())
      <div class="alert alert-info">No editable settings found in this section.</div>
    @else
      <form method="post" action="{{ route('settings.section', $section) }}">
        @csrf

        <div class="accordion mb-3">
          <div class="accordion-item">
            <h2 class="accordion-header" id="section-heading">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#section-collapse" aria-expanded="true" aria-controls="section-collapse">
                {{ $sectionLabel }} settings
              </button>
            </h2>
            <div id="section-collapse" class="accordion-collapse collapse show" aria-labelledby="section-heading">
              <div class="accordion-body">
                @foreach($settings as $setting)
                  @php
                    $val = $setting->value ?? '';
                    $name = $setting->name;
                    $label = ucfirst(str_replace('_', ' ', $name));
                    $isBoolean = in_array(strtolower($val), ['0', '1', 'true', 'false', 'yes', 'no'])
                                 || str_contains($name, '_enabled')
                                 || str_contains($name, '_disabled');
                    $isNumeric = !$isBoolean && is_numeric($val) && $val !== '';
                  @endphp

                  @if($isBoolean)
                    <div class="mb-3">
                      <label class="form-label">{{ $label }} <span class="badge bg-secondary ms-1">Optional</span></label>
                      <div>
                        <div class="form-check form-check-inline">
                          <input class="form-check-input" type="radio" name="settings[{{ $setting->id }}]" id="setting-{{ $setting->id }}-no" value="0" {{ !in_array(strtolower($val), ['1', 'true', 'yes']) ? 'checked' : '' }}>
                          <label class="form-check-label" for="setting-{{ $setting->id }}-no">No</label>
                        </div>
                        <div class="form-check form-check-inline">
                          <input class="form-check-input" type="radio" name="settings[{{ $setting->id }}]" id="setting-{{ $setting->id }}-yes" value="1" {{ in_array(strtolower($val), ['1', 'true', 'yes']) ? 'checked' : '' }}>
                          <label class="form-check-label" for="setting-{{ $setting->id }}-yes">Yes</label>
                        </div>
                      </div>
                    </div>
                  @elseif($isNumeric)
                    <div class="mb-3">
                      <label for="setting-{{ $setting->id }}" class="form-label">{{ $label }} <span class="badge bg-secondary ms-1">Optional</span></label>
                      <input type="number" class="form-control" name="settings[{{ $setting->id }}]"
                             id="setting-{{ $setting->id }}" value="{{ e($val) }}" style="max-width: 300px;">
                    </div>
                  @else
                    <div class="mb-3">
                      <label for="setting-{{ $setting->id }}" class="form-label">{{ $label }} <span class="badge bg-secondary ms-1">Optional</span></label>
                      <input type="text" class="form-control" name="settings[{{ $setting->id }}]"
                             id="setting-{{ $setting->id }}" value="{{ e($val) }}">
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        </div>

        <section class="actions mb-3">
          <input class="btn atom-btn-outline-success" type="submit" value="Save">
        </section>
      </form>
    @endif
@endsection
