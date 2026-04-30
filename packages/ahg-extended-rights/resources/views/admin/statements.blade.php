@extends('theme::layouts.2col')

@section('title', 'Rights Statements & Licenses')
@section('body-class', 'admin rights-admin statements')

@section('sidebar')
  @include('ahg-extended-rights::admin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Rights Statements &amp; Licenses</h1>
@endsection

@section('content')
  {{-- Rights Statements --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">
        <img src="https://rightsstatements.org/files/icons/rightss.logo.svg" alt="{{ __('Rights Statements') }}" height="24" class="me-2">
        Rights Statements
      </h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-4">
        Rights Statements are a set of 12 standardized statements designed to communicate the copyright
        and re-use status of digital objects. Learn more at
        <a href="https://rightsstatements.org" target="_blank">rightsstatements.org</a>.
      </p>

      @php
      $categories = [
        'in_copyright' => ['title' => 'In Copyright', 'color' => 'danger'],
        'no_copyright' => ['title' => 'No Copyright', 'color' => 'success'],
        'other' => ['title' => 'Other', 'color' => 'secondary'],
      ];
      @endphp

      @foreach($categories as $category => $meta)
      <h6 class="text-{{ $meta['color'] }} mt-4 mb-3">{{ $meta['title'] }}</h6>
      <div class="row">
        @foreach($rightsStatements as $stmt)
          @if(($stmt->category ?? '') === $category)
          <div class="col-md-6 mb-3">
            <div class="card h-100">
              <div class="card-body">
                <h6 class="card-title">
                  <span class="badge bg-{{ $meta['color'] }} me-2">{{ $stmt->code ?? '' }}</span>
                  {{ $stmt->name ?? '' }}
                </h6>
                <p class="card-text small text-muted">{{ $stmt->description ?? $stmt->definition ?? '' }}</p>
                @if($stmt->uri ?? null)
                <a href="{{ $stmt->uri }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                  <i class="fas fa-external-link-alt me-1"></i>{{ __('View Statement') }}
                </a>
                @endif
              </div>
            </div>
          </div>
          @endif
        @endforeach
      </div>
      @endforeach
    </div>
  </div>

  {{-- Creative Commons --}}
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0">
        <img src="https://mirrors.creativecommons.org/presskit/logos/cc.logo.svg" alt="{{ __('Creative Commons') }}" height="24" class="me-2">
        Creative Commons Licenses
      </h5>
    </div>
    <div class="card-body">
      <p class="text-muted mb-4">
        Creative Commons licenses provide a simple, standardized way to give the public permission
        to use creative work. Learn more at
        <a href="https://creativecommons.org" target="_blank">creativecommons.org</a>.
      </p>

      <div class="row">
        @foreach($ccLicenses as $license)
        <div class="col-md-6 mb-3">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex align-items-start">
                @if($license->badge_url ?? null)
                <img src="{{ $license->badge_url }}" alt="{{ $license->code ?? '' }}" height="31" class="me-3">
                @endif
                <div>
                  <h6 class="card-title mb-1">{{ $license->name ?? '' }}</h6>
                  <p class="card-text small text-muted mb-2">{{ $license->human_readable ?? '' }}</p>
                  <div class="small">
                    @if($license->allows_commercial ?? false)
                      <span class="badge bg-success me-1">{{ __('Commercial OK') }}</span>
                    @else
                      <span class="badge bg-warning text-dark me-1">{{ __('Non-Commercial') }}</span>
                    @endif
                    @if($license->allows_derivatives ?? false)
                      <span class="badge bg-info me-1">{{ __('Derivatives OK') }}</span>
                    @else
                      <span class="badge bg-danger me-1">{{ __('No Derivatives') }}</span>
                    @endif
                    @if($license->requires_share_alike ?? false)
                      <span class="badge bg-primary">{{ __('Share Alike') }}</span>
                    @endif
                  </div>
                </div>
              </div>
            </div>
            <div class="card-footer bg-transparent">
              @if($license->uri ?? null)
              <a href="{{ $license->uri }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-external-link-alt me-1"></i>{{ __('View License') }}
              </a>
              @endif
            </div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Usage Guide --}}
  <div class="card">
    <div class="card-header">
      <h5 class="mb-0">{{ __('Usage Guide') }}</h5>
    </div>
    <div class="card-body">
      <h6>{{ __('When to use Rights Statements:') }}</h6>
      <ul>
        <li><strong>{{ __('In Copyright statements') }}</strong> - For works that are still under copyright protection</li>
        <li><strong>{{ __('No Copyright statements') }}</strong> - For works in the public domain or with specific use restrictions</li>
        <li><strong>{{ __('Other statements') }}</strong> - When copyright status is unclear or not yet evaluated</li>
      </ul>

      <h6>{{ __('When to use Creative Commons:') }}</h6>
      <ul>
        <li>When you (or the rights holder) want to grant specific permissions for reuse</li>
        <li>For works you own or have permission to license</li>
        <li>When you want to enable open access with clear terms</li>
      </ul>

      <div class="alert alert-info mb-0">
        <i class="fas fa-info-circle me-2"></i>
        <strong>{{ __('Note:') }}</strong> Rights Statements describe the copyright status of a work.
        Creative Commons licenses are applied by the rights holder to grant permissions.
        They serve different purposes and may be used together.
      </div>
    </div>
  </div>
@endsection
