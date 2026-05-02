@extends('theme::layouts.2col')
@section('title', __('User interface labels'))
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('User interface labels') }}</h1>
  <p class="small text-muted">
    {{ __('Customise the entity-type names used across the UI (e.g. "Archival description", "Authority record"). Edits write to setting_i18n for the selected culture — the en row is the canonical fallback when a culture has no override.') }}
  </p>
@endsection

@section('content')
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  {{-- Culture picker — switching reloads the page with ?culture= --}}
  <form method="GET" class="d-flex align-items-center gap-2 mb-3" id="ui-label-culture-form">
    <label for="culture" class="form-label mb-0 fw-semibold">{{ __('Edit labels for culture:') }}</label>
    <select id="culture" name="culture" class="form-select form-select-sm" style="max-width:200px;">
      @foreach($cultures as $c)
        <option value="{{ $c }}" {{ $culture === $c ? 'selected' : '' }}>{{ $c }}</option>
      @endforeach
    </select>
    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-sync-alt me-1"></i>{{ __('Switch') }}</button>
    <span class="small text-muted">{{ __('Switching cultures reloads with the values stored for that culture.') }}</span>
  </form>

  @push('js')
  <script>
    // CSP-strict: no inline onchange. Auto-submit when the select changes.
    document.addEventListener('DOMContentLoaded', function () {
      var sel = document.getElementById('culture');
      var form = document.getElementById('ui-label-culture-form');
      if (sel && form) sel.addEventListener('change', function () { form.submit(); });
    });
  </script>
  @endpush

  <form method="post" action="{{ route('settings.interface-labels') }}">
    @csrf
    <input type="hidden" name="culture" value="{{ $culture }}">

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="interface-label-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#interface-label-collapse" aria-expanded="true" aria-controls="interface-label-collapse">
            {{ __('User interface labels') }} — <code class="ms-1">{{ $culture }}</code>
          </button>
        </h2>
        <div id="interface-label-collapse" class="accordion-collapse collapse show" aria-labelledby="interface-label-heading">
          <div class="accordion-body">

            <div class="table-responsive">
              <table class="table table-sm table-bordered align-middle">
                <thead class="table-light">
                  <tr>
                    <th style="width:25%;">{{ __('Key') }}</th>
                    <th style="width:35%;"><span class="badge bg-light text-dark">en</span> {{ __('Source (English canonical)') }}</th>
                    <th style="width:40%;"><span class="badge bg-light text-dark">{{ $culture }}</span> {{ __('Translation — edit here') }}</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($settings as $setting)
                    <tr>
                      <td><code class="small">{{ $setting->name }}</code></td>
                      <td class="small text-muted">{{ $setting->source_value ?? '(no en row)' }}</td>
                      <td>
                        <input type="text" name="settings[{{ $setting->id }}]" class="form-control form-control-sm" value="{{ $setting->value ?? '' }}" placeholder="{{ $setting->source_value ?? '' }}">
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>

          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <button type="submit" class="btn atom-btn-outline-success">
        <i class="fas fa-save me-1"></i>{{ __('Save labels for') }} <code>{{ $culture }}</code>
      </button>
      <a href="{{ route('settings.interface-labels') }}" class="btn atom-btn-outline-light">
        <i class="fas fa-arrow-left me-1"></i>{{ __('Cancel') }}
      </a>
    </section>

  </form>
@endsection
