@extends('theme::layouts.1col')

@section('title', 'Extended Rights Management')
@section('body-class', 'extended-rights index')

@section('title-block')
  <h1 class="mb-0">Extended Rights Management</h1>
@endsection

@section('content')
<div class="row">
  {{-- Rights Statements --}}
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header bg-primary text-white"><h5 class="mb-0">RightsStatements.org</h5></div>
      <div class="card-body">
        <p class="text-muted small">Standardized rights statements for cultural heritage institutions.</p>
        @if(!empty($rightsStatements) && count($rightsStatements) > 0)
          <ul class="list-unstyled">
            @foreach($rightsStatements as $rs)
              <li class="mb-2">
                @if(!empty($rs->uri))
                  <a href="{{ $rs->uri }}" target="_blank" title="{{ $rs->description ?? '' }}">{{ $rs->name ?? $rs->code }}</a>
                @else
                  {{ $rs->name ?? $rs->code }}
                @endif
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted">No rights statements configured.</p>
        @endif
      </div>
    </div>
  </div>

  {{-- Creative Commons --}}
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header bg-success text-white"><h5 class="mb-0">Creative Commons</h5></div>
      <div class="card-body">
        <p class="text-muted small">Open licensing for sharing and reuse.</p>
        @if(!empty($ccLicenses) && count($ccLicenses) > 0)
          <ul class="list-unstyled">
            @foreach($ccLicenses as $cc)
              <li class="mb-2">
                @if(!empty($cc->uri))
                  <a href="{{ $cc->uri }}" target="_blank" title="{{ $cc->description ?? '' }}">{{ $cc->name ?? $cc->code }}</a>
                @else
                  {{ $cc->name ?? $cc->code }}
                @endif
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted">No Creative Commons licenses configured.</p>
        @endif
      </div>
    </div>
  </div>

  {{-- TK Labels --}}
  <div class="col-md-4 mb-4">
    <div class="card h-100">
      <div class="card-header" style="background-color: #1a4d2e; color: white;"><h5 class="mb-0">Traditional Knowledge Labels</h5></div>
      <div class="card-body">
        <p class="text-muted small">Labels for Indigenous cultural heritage.</p>
        @if(!empty($tkLabels) && count($tkLabels) > 0)
          <ul class="list-unstyled">
            @foreach($tkLabels as $tk)
              <li class="mb-2">
                @if(!empty($tk->uri))
                  <a href="{{ $tk->uri }}" target="_blank">{{ $tk->name ?? $tk->code }}</a>
                @else
                  {{ $tk->name ?? $tk->code }}
                @endif
                @if(!empty($tk->category_name))
                  <small class="text-muted">({{ $tk->category_name }})</small>
                @endif
              </li>
            @endforeach
          </ul>
        @else
          <p class="text-muted">No TK Labels configured.</p>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- Statistics --}}
@if(isset($stats))
<div class="card mt-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Rights Coverage Statistics</h5>
  </div>
  <div class="card-body">
    <div class="row text-center">
      <div class="col"><h3>{{ number_format($stats->total_objects ?? 0) }}</h3><small class="text-muted">Total Objects</small></div>
      <div class="col"><h3>{{ number_format($stats->with_rights_statement ?? 0) }}</h3><small class="text-muted">With Rights Statement</small></div>
      <div class="col"><h3>{{ number_format($stats->with_creative_commons ?? 0) }}</h3><small class="text-muted">With CC License</small></div>
      <div class="col"><h3>{{ number_format($stats->with_tk_labels ?? 0) }}</h3><small class="text-muted">With TK Labels</small></div>
      <div class="col"><h3>{{ number_format($stats->active_embargoes ?? 0) }}</h3><small class="text-muted">Active Embargoes</small></div>
    </div>
  </div>
</div>
@endif

@auth
<div class="card mt-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">Administration</h5>
  </div>
  <div class="card-body">
    <a href="{{ route('extended-rights.batch') }}" class="btn atom-btn-white"><i class="fas fa-layer-group"></i> Batch Assign Rights</a>
    <a href="{{ route('extended-rights.embargoes') }}" class="btn atom-btn-white"><i class="fas fa-lock"></i> Manage Embargoes</a>
  </div>
</div>
@endauth
@endsection
