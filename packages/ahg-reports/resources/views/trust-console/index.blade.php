{{--
  Trust and Transparency Console - a single read-only operator console that
  ties together the trust, preservation, accessibility and open-data surfaces
  that already exist across the platform. Each surface renders as an
  independent card: title, one-line description, a LIVE "Open" link (only when
  its route is registered) and a best-effort metric badge when one is cheaply
  available.

  This is a HUB. It links only - it re-implements no surface. Every card is
  fully self-contained so an absent feature simply renders as "Not configured"
  without a dead link. Read-only. International, jurisdiction-neutral copy.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Trust and Transparency Console')
@section('body-class', 'admin reports')

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    @if(Route::has('reports.dashboard'))
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
    @endif
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this console') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('One place to find every trust, preservation, accessibility and open-data surface in the platform. Each card links straight to its surface when that feature is installed, and shows a live count where one is cheaply available. Cards for features that are not configured still appear - just without a live link.') }}
  </div>
</section>
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Surfaces') }}</span>
      <span class="fw-bold">{{ $summary['total'] }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Available now') }}</span>
      <span class="fw-bold text-success">{{ $summary['live'] }}</span>
    </div>
  </div>
</section>
@endsection

@section('title-block')
<h1>{{ __('Trust and Transparency Console') }}</h1>
<p class="text-muted mb-0">{{ __('The institution\'s transparency control panel, in one place') }}</p>
@endsection

@section('content')

{{-- Hero --}}
<div class="card bg-dark text-white mb-4">
  <div class="card-body">
    <div class="d-flex align-items-center flex-wrap gap-3">
      <div class="flex-grow-1">
        <h2 class="h4 mb-1"><i class="bi bi-shield-check me-2"></i>{{ __('Trust and transparency, in one place') }}</h2>
        <p class="mb-0 text-white-50">
          {{ __('This console is the institution\'s transparency control panel. It gathers the surfaces that let anyone see how the collection is cared for and accounted for: verifiable content credentials and provenance, digital-preservation integrity and maturity, accessibility coverage, and open data for the wider world. Every card opens an existing surface - this console builds nothing of its own.') }}
        </p>
      </div>
      <div class="text-center px-3">
        <div class="display-5 fw-bold">{{ $summary['live'] }}<span class="fs-5 text-white-50">/{{ $summary['total'] }}</span></div>
        <div class="text-uppercase small text-white-50">{{ __('surfaces available') }}</div>
      </div>
    </div>
  </div>
</div>

{{-- Surface groups --}}
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
                <span class="badge bg-success-subtle text-success-emphasis">{{ __('Available') }}</span>
              @else
                <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ __('Not configured') }}</span>
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
  {{ __('This console is read-only. Live links open the existing surface for each capability in a new tab. Counts are best-effort and reflect data currently in the system. Per-record surfaces open a sample record.') }}
</p>
@endsection
