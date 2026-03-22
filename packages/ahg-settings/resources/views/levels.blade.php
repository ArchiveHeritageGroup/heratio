@extends('theme::layouts.1col')
@section('title', 'Levels of Description')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-settings::_menu')
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="mb-0"><i class="fas fa-layer-group me-2"></i>Levels of Description</h1>
      <a href="{{ route('term.browse', ['taxonomy' => 'levels-of-description']) }}" class="btn atom-btn-outline-success" target="_blank">
        <i class="fas fa-plus me-1"></i>Add new term in Taxonomy
      </a>
    </div>

    <div class="alert alert-info mb-4">
      <i class="fas fa-info-circle me-2"></i>
      <strong>How it works:</strong> Select which levels appear in each sector. Only sectors with enabled plugins are shown. Archive levels are always available.
    </div>

    <form method="post" action="{{ route('settings.levels') }}">
      @csrf

      @foreach($sectors ?? [] as $code => $label)
        <div class="card mb-3">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
            <h5 class="mb-0"><i class="fas fa-archive me-2"></i>{{ ucfirst($label) }} Levels</h5>
          </div>
          <div class="card-body">
            @foreach($allLevels ?? [] as $level)
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="levels[{{ $code }}][]" value="{{ $level->id }}" id="lvl-{{ $code }}-{{ $level->id }}"
                  {{ in_array($level->id, $sectorLevels[$code] ?? []) ? 'checked' : '' }}>
                <label class="form-check-label" for="lvl-{{ $code }}-{{ $level->id }}">{{ $level->name }}</label>
              </div>
            @endforeach
          </div>
        </div>
      @endforeach

      @if(empty($sectors))
        <div class="alert alert-warning">
          <i class="fas fa-exclamation-triangle me-2"></i>No GLAM/DAM sectors detected. Enable sector plugins to configure levels.
        </div>
      @endif

      <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn atom-btn-white ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
