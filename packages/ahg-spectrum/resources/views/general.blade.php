@extends('theme::layouts.1col')

@section('title', __('General Spectrum Procedures'))

@section('content')

<h1><i class="fas fa-building me-2"></i>{{ __('General Spectrum Procedures') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('homepage') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ahgspectrum.dashboard') }}">{{ __('Spectrum Dashboard') }}</a></li>
    <li class="breadcrumb-item active">{{ __('General Procedures') }}</li>
  </ol>
</nav>

<div class="alert alert-info mb-4">
    <i class="fas fa-info-circle me-2"></i>
    {{ __('General procedures apply at the institution level and are not tied to a specific object. Use these for organisation-wide workflows such as institutional audits, risk management, and documentation planning.') }}
</div>

<!-- Procedures Grid -->
<div class="card mb-4">
  <div class="card-header bg-info text-white">
    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Institution-Level Procedures') }}</h5>
  </div>
  <div class="card-body">
    <div class="row g-3">
      @php
      $colors = ['primary', 'success', 'info', 'warning', 'secondary', 'dark'];
      $i = 0;
      @endphp
      @foreach ($procedures as $key => $proc)
        @php
        $color = $colors[$i % count($colors)];
        $currentState = $procedureStatuses[$key] ?? null;
        $i++;
        @endphp
      <div class="col-lg-3 col-md-4 col-sm-6">
        <div class="card h-100 border-{{ $color }}">
          <div class="card-body text-center p-3">
            <i class="fas {{ $proc['icon'] }} fa-2x mb-2 text-{{ $color }}"></i>
            <h6 class="card-title mb-2">{{ $proc['label'] }}</h6>
            @if ($currentState)
            <span class="badge bg-{{ $color }} mb-2">{{ ucwords(str_replace('_', ' ', $currentState)) }}</span>
            @endif
            <div>
              <a href="{{ route('ahgspectrum.general-workflow', ['procedure_type' => $key]) }}"
                 class="btn btn-sm btn-outline-{{ $color }}">
                <i class="fas fa-cog me-1"></i>{{ __('Manage') }}
              </a>
            </div>
          </div>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</div>

<!-- Recent General Activity -->
<div class="card">
  <div class="card-header">
    <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent General Procedure Activity') }}</h5>
  </div>
  <div class="card-body">
    @if (empty($recentHistory))
      <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('No general procedure activity recorded yet.') }}</p>
    @else
      <div class="table-responsive">
        <table class="table table-sm table-hover">
          <thead>
            <tr>
              <th>{{ __('Date') }}</th>
              <th>{{ __('Procedure') }}</th>
              <th>{{ __('Action') }}</th>
              <th>{{ __('User') }}</th>
              <th>{{ __('Notes') }}</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($recentHistory as $entry)
            <tr>
              <td><small>{{ date('Y-m-d H:i', strtotime($entry->created_at)) }}</small></td>
              <td>{{ ucwords(str_replace('_', ' ', $entry->procedure_type)) }}</td>
              <td>
                <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $entry->from_state)) }}</span>
                <i class="fas fa-arrow-right mx-1"></i>
                <span class="badge bg-primary">{{ ucwords(str_replace('_', ' ', $entry->to_state)) }}</span>
              </td>
              <td><small>{{ $entry->user_name ?? '' }}</small></td>
              <td><small>{{ $entry->note ?? '' }}</small></td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>

@endsection
