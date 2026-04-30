@extends('theme::layouts.2col')
@section('title', ($isNew ?? true) ? 'Add Numbering Scheme' : 'Edit Numbering Scheme')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1><i class="fas fa-hashtag me-2"></i>{{ ($isNew ?? true) ? 'Add Numbering Scheme' : 'Edit Numbering Scheme' }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.numbering-scheme-edit', ['id' => $schemeId ?? null]) }}">
      @csrf
      <div class="row">
        <div class="col-md-8">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-info-circle me-2"></i>{{ __('Basic Information') }}</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Name <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $scheme->name ?? '' }}" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label class="form-label">Sector <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                    <select name="sector" class="form-select" required>
                      @foreach(['archive' => 'Archive', 'museum' => 'Museum', 'library' => 'Library', 'gallery' => 'Gallery', 'dam' => 'DAM'] as $val => $label)
                        <option value="{{ $val }}" {{ ($scheme->sector ?? '') === $val ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="description" class="form-control" value="{{ $scheme->description ?? '' }}">
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-code me-2"></i>{{ __('Pattern Builder') }}</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Pattern <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label>
                <input type="text" name="pattern" id="pattern-field" class="form-control font-monospace" value="{{ $scheme->pattern ?? '' }}" required>
                <div class="form-text">Use tokens below to build your pattern</div>
              </div>
              <div class="mb-3">
                <label class="form-label">Insert Token <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <div class="d-flex flex-wrap gap-1">
                  @foreach(['{SEQ:4}', '{SEQ:5}', '{SEQ:6}', '{YEAR}', '{YY}', '{MONTH}', '{PREFIX}', '{REPO}', '{FONDS}', '{TYPE}', '{UUID}'] as $token)
                    <button type="button" class="btn btn-sm atom-btn-outline-secondary" onclick="document.getElementById('pattern-field').value += '{{ $token }}'">{{ str_replace(['{','}'], '', $token) }}</button>
                  @endforeach
                </div>
              </div>
            </div>
          </div>

          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-cog me-2"></i>{{ __('Options') }}</div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Counter start <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <input type="number" name="counter_start" class="form-control" value="{{ $scheme->counter_start ?? 1 }}" min="0">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="mb-3">
                    <label class="form-label">Reset period <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                    <select name="reset_period" class="form-select">
                      <option value="never" {{ ($scheme->reset_period ?? 'never') === 'never' ? 'selected' : '' }}>Never</option>
                      <option value="yearly" {{ ($scheme->reset_period ?? '') === 'yearly' ? 'selected' : '' }}>Yearly</option>
                      <option value="monthly" {{ ($scheme->reset_period ?? '') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    </select>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default" {{ ($scheme->is_default ?? false) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">Set as default <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-md-4">
          <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-eye me-2"></i>{{ __('Preview') }}</div>
            <div class="card-body">
              <p class="text-muted small">Save scheme to see numbering preview.</p>
              @if(isset($previews) && count($previews))
                @foreach($previews as $p)
                  <code class="d-block mb-1">{{ $p }}</code>
                @endforeach
              @endif
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
      <a href="{{ route('settings.numbering-schemes') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
@endsection
