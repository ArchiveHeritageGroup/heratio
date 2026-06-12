{{--
  Rights and Access report - a read-only, administrator-facing view of how the
  catalogue breaks down by access status, rights/licensing coverage, and ODRL
  policy governance. It answers an administrator's question: what is open, what
  is restricted, and what carries a rights statement.

  Three honest signals, three stores:
    - Publication (status type 158 / status 160) is the access baseline. There is
      no separate accessibility-status type on this schema, so none is invented.
    - Rights coverage and copyright status come from the `rights` table linked to
      a record through the `relation` "Right" edge. A record with no rights row is
      shown as "no rights statement recorded", not "no rights".
    - ODRL policies come from research_rights_policy. A record with no policy is
      OPEN by default (per OdrlService); the report frames it as open, not as
      restricted, and does not infer a right it cannot see.

  Each metric is a count, a share, and a CSS bar (no charting library). Read-only:
  it aggregates and changes nothing - no writes, no ALTER. International,
  jurisdiction-neutral copy (ODRL + rights statements are the neutral vocabulary;
  no single country's copyright regime is assumed). Never 500s; empty-state safe.

  @copyright  Johan Pieterse / Plain Sailing Information Systems
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Rights and access report')
@section('body-class', 'admin reports')

@php
  // Colour the published-share gauge by band. Pure presentation, no consequence.
  $pubPct = $published_pct ?? 0.0;
  if ($pubPct >= 75) {
      $pubClass = 'text-success';
      $pubBar   = 'bg-success';
  } elseif ($pubPct >= 40) {
      $pubClass = 'text-warning';
      $pubBar   = 'bg-warning';
  } else {
      $pubClass = 'text-secondary';
      $pubBar   = 'bg-secondary';
  }
@endphp

@section('sidebar')
<section class="card mb-3">
  <div class="card-body">
    @if(Route::has('reports.dashboard'))
    <a href="{{ route('reports.dashboard') }}" class="btn btn-outline-secondary btn-sm w-100">
      <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}
    </a>
    @endif
    @if(Route::has('trust.console'))
    <a href="{{ route('trust.console') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-patch-check me-1"></i>{{ __('Trust and Transparency Console') }}
    </a>
    @endif
    @if(Route::has('reports.catalogue-growth'))
    <a href="{{ route('reports.catalogue-growth') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-graph-up-arrow me-1"></i>{{ __('Catalogue growth') }}
    </a>
    @endif
    @if(Route::has('reports.preservation-health'))
    <a href="{{ route('reports.preservation-health') }}" class="btn btn-outline-secondary btn-sm w-100 mt-2">
      <i class="bi bi-shield-check me-1"></i>{{ __('Preservation health') }}
    </a>
    @endif
  </div>
</section>
<section class="card mb-3">
  <div class="card-header"><h6 class="mb-0">{{ __('About this report') }}</h6></div>
  <div class="card-body small text-muted">
    {{ __('A read-only administrator view of how the catalogue breaks down by access status, rights and licensing, and ODRL policy coverage: what is published, what carries a rights statement, and what is governed by a digital-rights policy. It surfaces what is open and what is restricted; it changes nothing. Where a signal is not recorded, it says so rather than inferring a right it cannot see.') }}
  </div>
</section>
@if($available && ($total ?? 0) > 0)
<section class="card mb-3">
  <div class="card-body small">
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Records') }}</span>
      <span class="fw-bold">{{ number_format($total) }}</span>
    </div>
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Published') }}</span>
      <span class="fw-bold {{ $pubClass }}">{{ number_format($published_pct, 1) }}%</span>
    </div>
    @if($rights_available)
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('With a rights statement') }}</span>
      <span class="fw-bold">{{ number_format($with_rights_pct, 1) }}%</span>
    </div>
    @endif
    @if($odrl_available)
    <div class="d-flex justify-content-between">
      <span class="text-muted">{{ __('Under an ODRL policy') }}</span>
      <span class="fw-bold">{{ number_format($governed_pct, 1) }}%</span>
    </div>
    @endif
  </div>
</section>
@endif
@endsection

@section('title-block')
<h1>{{ __('Rights and access report') }}</h1>
<p class="text-muted mb-0">{{ __('How the catalogue breaks down by access, rights and policy coverage') }}</p>
@endsection

@section('content')

@if(! $available)
  {{-- Fresh install: the information_object table itself is not present. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-shield-lock"></i></div>
      <h2 class="h5">{{ __('No catalogue yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('There are no records in the catalogue yet, so there is nothing to break down by access or rights. As records are catalogued and rights are recorded against them, this report will show what is open, what is restricted, and what carries a rights statement.') }}
      </p>
    </div>
  </div>
@elseif(($total ?? 0) <= 0)
  {{-- Table present but no real records yet. --}}
  <div class="card">
    <div class="card-body text-center py-5">
      <div class="display-6 text-muted mb-2"><i class="bi bi-inbox"></i></div>
      <h2 class="h5">{{ __('Nothing catalogued yet') }}</h2>
      <p class="text-muted mb-0">
        {{ __('No records have been catalogued yet. Once records exist, this report will show their publication, rights and ODRL-policy breakdown.') }}
      </p>
    </div>
  </div>
@else

{{-- Headline strip: published share + rights + policy coverage --}}
<div class="card mb-4">
  <div class="card-body">
    <div class="row align-items-center g-4">
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold">{{ number_format($total) }}</div>
        <div class="text-uppercase small text-muted">{{ __('records') }}</div>
      </div>
      <div class="col-md-3 text-center border-end">
        <div class="display-5 fw-bold {{ $pubClass }}">{{ number_format($published_pct, 1) }}%</div>
        <div class="text-uppercase small text-muted">{{ __('published') }}</div>
      </div>
      <div class="col-md-6">
        <div class="d-flex justify-content-between mb-1">
          <span class="fw-semibold"><i class="bi bi-eye me-1"></i>{{ __('Published (publicly visible) share') }}</span>
          <span class="fw-bold {{ $pubClass }}">{{ number_format($published_pct, 1) }}%</span>
        </div>
        <div class="progress" style="height: 1.25rem;" role="progressbar"
             aria-valuenow="{{ (int) round($published_pct) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('Published share') }}">
          <div class="progress-bar {{ $pubBar }}" style="width: {{ max(0, min(100, $published_pct)) }}%;">
            {{ number_format($published_pct, 1) }}%
          </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
          <strong>{{ number_format($published) }}</strong> {{ __('published') }},
          <strong>{{ number_format($unpublished) }}</strong> {{ __('draft or unpublished, of') }}
          <strong>{{ number_format($total) }}</strong> {{ __('records.') }}
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- Publication (access baseline) --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-eye me-1"></i>{{ __('Publication (access baseline)') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Whether a record is published (publicly visible) or still draft. Publication is the baseline access signal that lives on a record directly; on this schema there is no separate accessibility-status field, so publication stands in for the open-versus-not-yet-open split. Shares are of all records.') }}</p>
        @foreach($publication_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Rights coverage --}}
  @if($rights_available)
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-patch-check me-1"></i>{{ __('Rights statement coverage') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('How many records carry a rights statement (a linked rights record) versus how many have none recorded. A record with no rights statement is shown as "not recorded" - it is not assumed to be free of rights. Shares are of all records.') }}</p>
        @foreach($rights_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
        <p class="text-muted small mb-0">
          <strong>{{ number_format($with_rights_pct, 1) }}%</strong> {{ __('of records carry a rights statement.') }}
        </p>
      </div>
    </div>
  </div>
  @else
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-patch-question me-1"></i>{{ __('Rights statement coverage') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-0">
          <i class="bi bi-info-circle me-1"></i>{{ __('No rights store is present, so rights-statement coverage cannot be shown.') }}
        </p>
      </div>
    </div>
  </div>
  @endif
</div>

{{-- Copyright status of rights-bearing records --}}
@if($rights_available && ! empty($copyright_rows))
<div class="card mt-4">
  <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-c-circle me-1"></i>{{ __('Copyright status (of records that carry a rights statement)') }}</h3></div>
  <div class="card-body">
    <p class="text-muted small mb-3">{{ __('Where a rights statement records a copyright status, how the rights-bearing records break down by it (for example under copyright, public domain). Copyright status is a neutral vocabulary; the specific terms in use come from the dropdown manager and are not tied to any one country. Records whose rights statement does not record a copyright status are shown as "not recorded". Shares are of records that carry a rights statement.') }}</p>
    @foreach($copyright_rows as $row)
    <div class="mb-3">
      <div class="d-flex justify-content-between align-items-baseline mb-1">
        <span class="fw-semibold">
          @if(! empty($row['is_unset']))
            <i class="bi bi-dash-circle me-1 text-muted"></i><span class="text-muted">{{ $row['label'] }}</span>
          @else
            <i class="bi bi-c-circle me-1 text-info"></i>{{ $row['label'] }}
          @endif
        </span>
        <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
      </div>
      <div class="progress" style="height: 0.75rem;" role="progressbar"
           aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
           aria-label="{{ $row['label'] }}">
        <div class="progress-bar {{ ! empty($row['is_unset']) ? 'bg-secondary' : 'bg-info' }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
      </div>
    </div>
    @endforeach
    @unless($copyright_recorded)
    <p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>{{ __('No copyright status is recorded on any rights statement yet.') }}</p>
    @endunless
  </div>
</div>
@endif

{{-- ODRL policy coverage --}}
@if($odrl_available)
<div class="row g-4 mt-1">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-shield-lock me-1"></i>{{ __('ODRL policy coverage') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('How many records are governed by a digital-rights (ODRL) policy versus how many are open by default. A record with no policy is open access by default - this report frames it as open, not as restricted. ODRL is a jurisdiction-neutral rights vocabulary. Shares are of all records.') }}</p>
        @foreach($odrl_coverage_rows as $row)
        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-baseline mb-1">
            <span class="fw-semibold"><i class="bi bi-{{ $row['icon'] }} me-1 text-{{ $row['tone'] }}"></i>{{ $row['label'] }}</span>
            <span class="small text-muted">{{ number_format($row['count']) }} ({{ number_format($row['pct'], 1) }}%)</span>
          </div>
          <div class="progress" style="height: 0.75rem;" role="progressbar"
               aria-valuenow="{{ (int) round($row['pct']) }}" aria-valuemin="0" aria-valuemax="100"
               aria-label="{{ $row['label'] }}">
            <div class="progress-bar bg-{{ $row['tone'] }}" style="width: {{ max(0, min(100, $row['pct'])) }}%;"></div>
          </div>
        </div>
        @endforeach
        <p class="text-muted small mb-0">
          {{ number_format($odrl_policy_total) }} {{ __('ODRL policy row(s) on record in total.') }}
        </p>
      </div>
    </div>
  </div>

  {{-- ODRL policies by action --}}
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-list-check me-1"></i>{{ __('ODRL policies by action') }}</h3></div>
      <div class="card-body">
        <p class="text-muted small mb-3">{{ __('Of the records governed by a policy, how many are governed for each ODRL action: use (viewing) versus reproduce (printing), plus any other action present. A record may be governed for more than one action, so these do not sum to the governed total.') }}</p>
        @if(! empty($odrl_action_rows))
        <div class="table-responsive">
          <table class="table table-sm small mb-0 align-middle">
            <thead><tr>
              <th>{{ __('Action') }}</th>
              <th class="text-end">{{ __('Records governed') }}</th>
            </tr></thead>
            <tbody>
              @foreach($odrl_action_rows as $r)
              <tr>
                <td class="text-nowrap"><i class="bi bi-{{ $r['icon'] }} me-1 text-info"></i>{{ $r['label'] }}</td>
                <td class="text-end fw-semibold">{{ number_format($r['count']) }}</td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <p class="text-success small mb-0"><i class="bi bi-unlock me-1"></i>{{ __('No record is governed by an ODRL policy. Everything is open access by default.') }}</p>
        @endif
      </div>
    </div>
  </div>
</div>
@else
<div class="card mt-4">
  <div class="card-header"><h3 class="h6 mb-0"><i class="bi bi-shield-lock me-1"></i>{{ __('ODRL policy coverage') }}</h3></div>
  <div class="card-body">
    <p class="text-muted small mb-0">
      <i class="bi bi-info-circle me-1"></i>{{ __('No ODRL policy store is present, so policy coverage cannot be shown. With no policies in force, every record is open access by default.') }}
    </p>
  </div>
</div>
@endif

@endif

<p class="text-muted small mb-0 mt-4">
  {{ __('This report is read-only. It aggregates the current state of the publication, rights and ODRL-policy stores and makes no changes to any record. Where a signal is not recorded it is shown as such rather than inferred. The rights vocabulary (ODRL actions, rights statements, copyright status) is jurisdiction-neutral and is not tied to any one country\'s rules.') }}
</p>

@endsection
