@extends('theme::layouts.1col')

@section('title', 'Settings')
@section('body-class', 'admin settings')

@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-cogs me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column">
      <h1 class="mb-0">Settings</h1>
      <span class="small text-muted">System configuration</span>
    </div>
  </div>

  {{-- AtoM Settings Sections --}}
  <h2 class="h5 mb-3">AtoM Settings</h2>
  <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
    @foreach($scopeCards as $card)
      <div class="col">
        <div class="card h-100">
          <div class="card-body">
            <div class="d-flex align-items-start">
              <i class="fas {{ $card->icon }} fa-2x text-secondary me-3 mt-1" aria-hidden="true"></i>
              <div>
                <h5 class="card-title mb-1">
                  <a href="{{ route('settings.section', $card->key) }}" class="text-decoration-none">
                    {{ $card->label }}
                  </a>
                </h5>
                <p class="card-text text-muted small mb-1">{{ $card->description }}</p>
                <span class="badge bg-secondary">{{ $card->count }} {{ Str::plural('setting', $card->count) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  {{-- AHG Settings Sections --}}
  @if($ahgGroups->isNotEmpty())
    <h2 class="h5 mb-3">AHG Settings</h2>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
      @foreach($ahgGroups as $group)
        <div class="col">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                <i class="fas fa-puzzle-piece fa-2x text-secondary me-3 mt-1" aria-hidden="true"></i>
                <div>
                  <h5 class="card-title mb-1">
                    <a href="{{ route('settings.ahg', $group->key) }}" class="text-decoration-none">
                      {{ $group->label }}
                    </a>
                  </h5>
                  <p class="card-text text-muted small mb-1">AHG plugin settings for {{ strtolower($group->label) }}.</p>
                  <span class="badge bg-secondary">{{ $group->count }} {{ Str::plural('setting', $group->count) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif
@endsection
