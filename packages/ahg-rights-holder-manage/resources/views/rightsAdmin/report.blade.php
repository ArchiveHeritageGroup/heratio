@extends('theme::layouts.2col')

@section('title', 'Rights Report')
@section('body-class', 'admin rights-admin report')

@section('sidebar')
  @include('ahg-rights-holder-manage::rightsAdmin._sidebar')
@endsection

@section('title-block')
  <h1 class="mb-0">{{ __('Rights Report') }}</h1>
@endsection

@section('content')
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card text-center"><div class="card-body py-3"><h3 class="mb-0">{{ number_format($stats['total_objects'] ?? 0) }}</h3><small class="text-muted">Total Objects</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-success"><div class="card-body py-3"><h3 class="text-success mb-0">{{ number_format($stats['with_rights'] ?? 0) }}</h3><small class="text-muted">With Rights</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-warning"><div class="card-body py-3"><h3 class="text-warning mb-0">{{ number_format($stats['without_rights'] ?? 0) }}</h3><small class="text-muted">Without Rights</small></div></div></div>
  <div class="col-md-3"><div class="card text-center border-info"><div class="card-body py-3"><h3 class="text-info mb-0">{{ $stats['coverage_pct'] ?? 0 }}%</h3><small class="text-muted">Coverage</small></div></div></div>
</div>

@if(isset($byBasis) && count($byBasis) > 0)
<div class="card mb-4">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">{{ __('By Rights Basis') }}</h5>
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('Basis') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
      <tbody>
        @foreach($byBasis as $row)
          <tr><td>{{ $row->name ?? $row->basis ?? '' }}</td><td class="text-end">{{ number_format($row->count ?? 0) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif

@if(isset($byHolder) && count($byHolder) > 0)
<div class="card">
  <div class="card-header" style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);">
    <h5 class="mb-0">{{ __('By Rights Holder') }}</h5>
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead><tr style="background-color:var(--ahg-card-header-bg, #005837);color:var(--ahg-card-header-text, #fff);"><th>{{ __('Rights Holder') }}</th><th class="text-end">{{ __('Count') }}</th></tr></thead>
      <tbody>
        @foreach($byHolder as $row)
          <tr><td>{{ $row->name ?? '' }}</td><td class="text-end">{{ number_format($row->count ?? 0) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endif
@endsection
