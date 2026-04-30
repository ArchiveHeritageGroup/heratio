{{--
  Levels of Description settings
  @author Johan Pieterse <johan@theahg.co.za>
  @copyright Plain Sailing (Pty) Ltd
  @license AGPL-3.0
--}}
@extends('theme::layouts.2col')
@section('title', 'Levels of Description')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@php
function levelsGetSectorIcon(string $sector): string {
    return match($sector) {
        'archive' => 'archive',
        'museum'  => 'landmark',
        'library' => 'book',
        'gallery' => 'image',
        'dam'     => 'photo-video',
        default   => 'folder',
    };
}
@endphp

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="mb-0"><i class="fas fa-layer-group me-2"></i>{{ __('Levels of Description') }}</h1>
  <a href="{{ route('term.browse', ['taxonomy' => 'levels-of-description']) }}" class="btn atom-btn-outline-success" target="_blank">
    <i class="fas fa-plus me-1"></i>{{ __('Add new term in Taxonomy') }}
  </a>
</div>

<div class="alert alert-info mb-4">
  <i class="fas fa-info-circle me-2"></i>
  <strong>{{ __('How it works:') }}</strong> Select which levels appear in each sector. Only sectors with enabled plugins are shown. Archive levels are always available.
</div>

{{-- Sector tabs --}}
<ul class="nav nav-tabs mb-4">
  @foreach ($availableSectors as $sector)
    @php $count = $sectorCounts[$sector] ?? 0; @endphp
    <li class="nav-item">
      <a class="nav-link {{ $currentSector === $sector ? 'active' : '' }}"
         href="{{ route('settings.levels', ['sector' => $sector]) }}">
        <i class="fas fa-{{ levelsGetSectorIcon($sector) }} me-1"></i>
        {{ ucfirst($sector) }}
        <span class="badge bg-secondary ms-1">{{ $count }}</span>
      </a>
    </li>
  @endforeach
</ul>

<div class="row">
  {{-- Main column --}}
  <div class="col-lg-8">
    {{-- Sector levels checkbox card --}}
    <div class="card mb-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff;">
        <h5 class="mb-0">
          <i class="fas fa-{{ levelsGetSectorIcon($currentSector) }} me-2"></i>
          {{ ucfirst($currentSector) }} Levels
        </h5>
      </div>
      <div class="card-body">
        <form method="post" action="{{ route('settings.levels', ['sector' => $currentSector]) }}">
          @csrf
          <input type="hidden" name="action_type" value="update_sector">
          <input type="hidden" name="sector" value="{{ $currentSector }}">

          <p class="text-muted mb-3">Select which levels appear in the <strong>{{ ucfirst($currentSector) }}</strong> sector:</p>

          @if ($sectorAvailableLevels->isEmpty())
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              {{ __('No levels available for this sector. The required terms may not exist in the database. Please add them via the Taxonomy.') }}
            </div>
          @else
            <div class="row">
              @foreach ($sectorAvailableLevels as $level)
                <div class="col-md-6 col-lg-4 mb-2">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox"
                           name="levels[]" value="{{ $level->id }}"
                           id="level_{{ $level->id }}"
                           {{ in_array($level->id, $sectorLevelIds) ? 'checked' : '' }}>
                    <label class="form-check-label" for="level_{{ $level->id }}">
                      {{ e($level->name) }}
                      @if ($level->slug)
                        <a href="{{ route('term.show', ['slug' => $level->slug]) }}"
                           class="text-muted ms-1" title="{{ __('Edit in Taxonomy') }}" target="_blank">
                          <i class="fas fa-external-link-alt fa-xs"></i>
                        </a>
                      @endif
                    </label>
                  </div>
                </div>
              @endforeach
            </div>
          @endif

          <hr>
          <button type="submit" class="btn atom-btn-outline-success">
            <i class="fas fa-save me-1"></i> {{ __('Save Changes') }}
          </button>
        </form>
      </div>
    </div>

    {{-- Display order card --}}
    @if ($sectorLevels->count() > 0)
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-sort me-2"></i>{{ __('Display Order') }}</h5>
      </div>
      <div class="card-body">
        <form method="post" action="{{ route('settings.levels', ['sector' => $currentSector]) }}">
          @csrf
          <input type="hidden" name="action_type" value="update_order">
          <input type="hidden" name="sector" value="{{ $currentSector }}">

          <table class="table table-sm table-hover">
            <thead>
              <tr>
                <th>{{ __('Level') }}</th>
                <th style="width: 100px;">{{ __('Order') }}</th>
                <th style="width: 80px;">{{ __('Actions') }}</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($sectorLevels as $level)
                <tr>
                  <td>{{ e($level->name) }}</td>
                  <td>
                    <input type="number" class="form-control form-control-sm"
                           name="order[{{ $level->id }}]"
                           value="{{ $level->display_order }}"
                           min="0" step="10">
                  </td>
                  <td>
                    @if ($level->slug)
                      <a href="{{ route('term.show', ['slug' => $level->slug]) }}"
                         class="btn btn-sm btn-outline-secondary" title="{{ __('Edit') }}" target="_blank">
                        <i class="fas fa-edit"></i>
                      </a>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>

          <button type="submit" class="btn btn-secondary btn-sm">
            <i class="fas fa-sort me-1"></i> {{ __('Update Order') }}
          </button>
        </form>
      </div>
    </div>
    @endif
  </div>

  {{-- Sidebar --}}
  <div class="col-lg-4">
    {{-- About Sectors --}}
    <div class="card mb-3">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('About Sectors') }}</h5>
      </div>
      <div class="card-body small">
        <dl class="mb-0">
          <dt><i class="fas fa-archive me-1"></i> Archive</dt>
          <dd class="text-muted">Traditional archival levels (ISAD(G), RAD, DACS)</dd>

          @if (in_array('museum', $availableSectors))
          <dt><i class="fas fa-landmark me-1"></i> Museum</dt>
          <dd class="text-muted">Object-based descriptions (CCO/CDWA, Spectrum)</dd>
          @endif

          @if (in_array('library', $availableSectors))
          <dt><i class="fas fa-book me-1"></i> Library</dt>
          <dd class="text-muted">Bibliographic materials (books, journals, articles)</dd>
          @endif

          @if (in_array('gallery', $availableSectors))
          <dt><i class="fas fa-image me-1"></i> Gallery</dt>
          <dd class="text-muted">Artwork and visual materials</dd>
          @endif

          @if (in_array('dam', $availableSectors))
          <dt><i class="fas fa-photo-video me-1"></i> DAM</dt>
          <dd class="text-muted mb-0">Digital Asset Management (media files)</dd>
          @endif
        </dl>
      </div>
    </div>

    {{-- Quick Links --}}
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-link me-2"></i>{{ __('Quick Links') }}</h5>
      </div>
      <div class="list-group list-group-flush">
        <a href="{{ route('term.browse', ['taxonomy' => 'levels-of-description']) }}"
           class="list-group-item list-group-item-action" target="_blank">
          <i class="fas fa-list me-2"></i>Browse all levels in Taxonomy
          <i class="fas fa-external-link-alt fa-xs float-end mt-1"></i>
        </a>
        <a href="{{ route('term.browse', ['taxonomy' => 'levels-of-description']) }}"
           class="list-group-item list-group-item-action" target="_blank">
          <i class="fas fa-plus me-2"></i>Create new level term
          <i class="fas fa-external-link-alt fa-xs float-end mt-1"></i>
        </a>
      </div>
    </div>
  </div>
</div>

<hr>
<div class="d-flex justify-content-start">
  <a href="{{ route('settings.index') }}" class="btn atom-btn-white">
    <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Settings') }}
  </a>
</div>
@endsection
