@extends('theme::layouts.master')

@section('title', 'Levels of Description')
@section('body-class', 'admin display levels')

@section('breadcrumbs')
  <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
  <li class="breadcrumb-item"><a href="{{ route('glam.index') }}">Display Configuration</a></li>
  <li class="breadcrumb-item active" aria-current="page">Levels</li>
@endsection

@section('layout-content')
<div id="main-column" role="main">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center">
      <i class="fas fa-3x fa-sitemap me-3 text-primary" aria-hidden="true"></i>
      <div>
        <h1 class="mb-0">Levels of Description</h1>
        <span class="small text-muted">Manage hierarchical levels and their relationships</span>
      </div>
    </div>
    <a href="{{ route('glam.index') }}" class="btn atom-btn-white">
      <i class="fas fa-arrow-left me-1"></i> Back
    </a>
  </div>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  {{-- Domain filter --}}
  <div class="card mb-4">
    <div class="card-body">
      <form method="GET" action="{{ route('glam.levels') }}" class="row g-2 align-items-end">
        <div class="col-auto">
          <label for="domain" class="form-label mb-0"><strong>Filter by domain:</strong> <span class="badge bg-secondary ms-1">Optional</span></label>
        </div>
        <div class="col-auto">
          <select name="domain" id="domain" class="form-select form-select-sm">
            <option value="">All domains</option>
            @if(!empty($domains))
              @foreach($domains as $domain)
                <option value="{{ $domain }}" {{ ($currentDomain ?? '') === $domain ? 'selected' : '' }}>
                  {{ ucfirst($domain) }}
                </option>
              @endforeach
            @endif
          </select>
        </div>
        <div class="col-auto">
          <button type="submit" class="btn atom-btn-outline-light btn-sm">
            <i class="fas fa-filter me-1"></i> Filter
          </button>
        </div>
        @if(!empty($currentDomain))
          <div class="col-auto">
            <a href="{{ route('glam.levels') }}" class="btn btn-sm atom-btn-white">
              <i class="fas fa-times me-1"></i> Clear
            </a>
          </div>
        @endif
      </form>
    </div>
  </div>

  {{-- Levels table --}}
  <div class="card">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
      <h5 class="mb-0">
        <i class="fas fa-list me-2"></i>
        Levels
        @if(!empty($currentDomain))
          <span class="badge bg-secondary ms-2">{{ ucfirst($currentDomain) }}</span>
        @endif
        @if(!empty($levels))
          <span class="badge bg-primary ms-2">{{ count($levels) }}</span>
        @endif
      </h5>
    </div>
    <div class="card-body p-0">
      @if(!empty($levels) && count($levels))
        <table class="table table-bordered table-striped mb-0">
          <thead>
            <tr style="background:var(--ahg-primary);color:#fff">
              <th style="width: 40px;"></th>
              <th>Name</th>
              <th>Code</th>
              <th>Domain</th>
              <th>Valid Parents</th>
              <th>Valid Children</th>
            </tr>
          </thead>
          <tbody>
            @foreach($levels as $level)
              <tr>
                <td class="text-center">
                  @if(!empty($level->icon))
                    <i class="fas {{ $level->icon }} text-muted"></i>
                  @else
                    <i class="fas fa-folder text-muted"></i>
                  @endif
                </td>
                <td><strong>{{ $level->name ?? '-' }}</strong></td>
                <td><code>{{ $level->code ?? '-' }}</code></td>
                <td>
                  @if(!empty($level->domain))
                    @php
                      $domainColor = match($level->domain) {
                        'archive' => 'success',
                        'museum'  => 'warning',
                        'gallery' => 'info',
                        'library' => 'primary',
                        'dam'     => 'danger',
                        default   => 'secondary',
                      };
                    @endphp
                    <span class="badge bg-{{ $domainColor }}">{{ ucfirst($level->domain) }}</span>
                  @else
                    <span class="text-muted">-</span>
                  @endif
                </td>
                <td>
                  @if(!empty($level->valid_parents))
                    @foreach(is_array($level->valid_parents) ? $level->valid_parents : explode(',', $level->valid_parents) as $parent)
                      <span class="badge bg-outline-secondary border text-dark me-1">{{ trim($parent) }}</span>
                    @endforeach
                  @else
                    <span class="text-muted">Any</span>
                  @endif
                </td>
                <td>
                  @if(!empty($level->valid_children))
                    @foreach(is_array($level->valid_children) ? $level->valid_children : explode(',', $level->valid_children) as $child)
                      <span class="badge bg-outline-secondary border text-dark me-1">{{ trim($child) }}</span>
                    @endforeach
                  @else
                    <span class="text-muted">Any</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      @else
        <div class="p-3 text-muted">
          <i class="fas fa-info-circle me-2"></i>No levels of description found.
        </div>
      @endif
    </div>
  </div>
</div>
@endsection
