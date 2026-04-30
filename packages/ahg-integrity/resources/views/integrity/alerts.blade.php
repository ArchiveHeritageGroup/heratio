@extends('theme::layouts.1col')
@section('title', 'Integrity - Alerts')
@section('body-class', 'admin integrity alerts')
@section('title-block')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-shield-alt me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">{{ __('Alerts') }}</h1><span class="small text-muted">{{ __('Digital object integrity management') }}</span></div>
  </div>
@endsection
@section('content')
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><h5 class="mb-0">{{ __('Integrity Alerts') }}</h5></div>
  <div class="card-body p-0">
    @if(isset($alerts) && count($alerts) > 0)
    <table class="table table-striped table-hover mb-0"><thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('Date') }}</th><th>{{ __('Severity') }}</th><th>{{ __('Message') }}</th><th>{{ __('Object') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>@foreach($alerts as $a)<tr class="{{ ($a->severity ?? '') === 'critical' ? 'table-danger' : '' }}"><td>{{ $a->created_at ?? '' }}</td><td><span class="badge bg-{{ ($a->severity ?? '') === 'critical' ? 'danger' : (($a->severity ?? '') === 'warning' ? 'warning' : 'info') }}">{{ ucfirst($a->severity ?? 'info') }}</span></td><td>{{ $a->message ?? '' }}</td><td>{{ $a->object_id ?? '' }}</td><td>{{ ucfirst($a->status ?? 'open') }}</td></tr>@endforeach</tbody></table>
    @else<div class="text-center py-4 text-muted">No alerts found.</div>@endif
  </div>
</div>
<div class="mt-3"><a href="{{ route('integrity.index') }}" class="btn atom-btn-white"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}</a></div>
@endsection
