{{--
  North Star Cockpit - a single demo-ready overview of the platform's vision
  (north-star) capabilities. Each capability renders as an independent status
  card: title, one-line description, a LIVE "Open" link (only when its route
  is registered) and a best-effort metric badge when one is cheaply available.

  Read-only. International, jurisdiction-neutral copy. Every card is fully
  self-contained so an absent feature simply renders without a link/metric.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'North Star Cockpit')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this cockpit') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A single demo-ready view of the platform\'s vision capabilities. Each card links straight to its public surface when that feature is installed, and shows a live count where one is cheaply available. Cards for features that are not installed still appear - just without a live link.') }}
  </div>
</section>
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Capabilities') }}</span>
      <span class="fw-bold">{{ $summary['total'] }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Live now') }}</span>
      <span class="fw-bold text-success">{{ $summary['live'] }}</span>
    </div>
  </div>
</section>
@endsection

@section('title-block')
<h1>{{ __('North Star Cockpit') }}</h1>
<p class="text-muted mb-0">{{ __('The platform vision, in one place') }}</p>
@endsection

@section('content')

{{-- Hero --}}
<div class="card bg-dark text-white mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center flex-wrap gap-3">
      <div class="flex-grow-1">
        <h2 class="h4 mb-1"><i class="bi bi-stars me-2"></i>{{ __('Where this platform is heading') }}</h2>
        <p class="mb-0 text-white-50">
          {{ __('These are the north-star capabilities that set the platform apart: discovery that meets people where they are, reconstructions that bring lost context back to life, verifiable trust in every digital object, and open data for the wider world. Open any card below to demonstrate it live.') }}
        </p>
      </div>
      <div class="text-center px-3">
        <div class="display-5 fw-bold">{{ $summary['live'] }}<span class="fs-5 text-white-50">/{{ $summary['total'] }}</span></div>
        <div class="text-uppercase small text-white-50">{{ __('live capabilities') }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Capability groups --}}
@foreach($groups as $group)
<section class="mb-4">
  <div class="d-flex align-items-baseline mb-2">
    <h3 class="h5 mb-0 me-2">{{ __($group['label']) }}</h3>
    <span class="text-muted small">{{ __($group['desc']) }}</span>
  </div>

  <div class="row g-3">
    @foreach($group['cards'] as $card)
    <div class="col-md-6 col-xl-4">
      <div class="card h-100">
        <div class="card-body d-flex flex-column">
          <div class="d-flex align-items-start mb-2">
            <span class="fs-3 me-2 text-primary"><i class="bi bi-{{ $card['icon'] }}"></i></span>
            <div class="flex-grow-1">
              <h5 class="card-title mb-1">{{ __($card['title']) }}</h5>
              @if($card['url'])
                <span class="badge bg-success-subtle text-success-emphasis">{{ __('Live') }}</span>
              @else
                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Not installed') }}</span>
              @endif
              @if($card['metric'])
                <span class="badge bg-info-subtle text-info-emphasis">
                  {{ number_format($card['metric']['value']) }} {{ __($card['metric']['label']) }}
                </span>
              @endif
            </div>
          </div>

          <p class="card-text text-muted small flex-grow-1">{{ __($card['description']) }}</p>

          <div class="mt-2">
            @if($card['url'])
              <a href="{{ $card['url'] }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right me-1"></i>{{ __('Open') }}
              </a>
            @else
              <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                <i class="bi bi-slash-circle me-1"></i>{{ __('Not available') }}
              </button>
            @endif
          </div>
        </div>
      </div>
    </div>
    @endforeach
  </div>
</section>
@endforeach

<p class="text-muted small mb-0">
  {{ __('This cockpit is read-only. Live links open the public surface of each capability in a new tab. Counts are best-effort and reflect data currently in the system.') }}
</p>
@endsection
