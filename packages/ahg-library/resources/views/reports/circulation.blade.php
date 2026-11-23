{{-- Circulation statistics (#1476). @copyright Johan Pieterse / Plain Sailing @license AGPL-3.0-or-later --}}
@extends('theme::layouts.2col')
@section('title', __('Circulation statistics'))
@section('body-class', 'library-reports circulation')
@section('sidebar')
<div class="sidebar-content">
  <h4>{{ __('Library Reports') }}</h4>
  <ul class="list-unstyled">
    <li><a href="{{ route('library.report-circulation') }}"><i class="fas fa-exchange-alt me-2"></i>{{ __('Circulation') }}</a></li>
    <li><a href="{{ route('library.report-catalogue') }}"><i class="fas fa-book me-2"></i>{{ __('Catalogue') }}</a></li>
    <li><a href="{{ route('library.report-creators') }}"><i class="fas fa-user-edit me-2"></i>{{ __('Creators') }}</a></li>
    <li><a href="{{ route('library.report-subjects') }}"><i class="fas fa-tags me-2"></i>{{ __('Subjects') }}</a></li>
    <li><a href="{{ route('library.report-publishers') }}"><i class="fas fa-building me-2"></i>{{ __('Publishers') }}</a></li>
    <li><a href="{{ route('library.report-call-numbers') }}"><i class="fas fa-sort-alpha-down me-2"></i>{{ __('Call Numbers') }}</a></li>
  </ul>
  <a href="{{ route('library.reports') }}" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-arrow-left me-2"></i>{{ __('Back to Dashboard') }}</a>
</div>
@endsection
@section('title-block')<h1><i class="fas fa-exchange-alt me-2"></i>{{ __('Circulation statistics') }}</h1>@endsection
@section('content')
@php
  $query = array_filter(['period' => $filters['period'], 'from' => $filters['from'], 'to' => $filters['to'], 'branch' => $filters['branch']], fn ($v) => $v !== null);
  $measure = fn ($k) => __(\AhgLibrary\Services\LibraryCirculationStatsService::measureLabel($k));
@endphp

@if($errors->any())
<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="get" action="{{ route('library.report-circulation') }}" class="row g-2 align-items-end mb-3">
  <div class="col-md-3">
    <label class="form-label" for="period">{{ __('Period') }}</label>
    <select class="form-select" id="period" name="period">
      @foreach($periods as $code => $label)
      <option value="{{ $code }}" @selected($filters['period'] === $code)>{{ __($label) }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label" for="from">{{ __('From') }}</label>
    <input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] }}">
  </div>
  <div class="col-md-2">
    <label class="form-label" for="to">{{ __('To') }}</label>
    <input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] }}">
  </div>
  @if($branchOptions)
  <div class="col-md-3">
    <label class="form-label" for="branch">{{ __('Branch') }}</label>
    <select class="form-select" id="branch" name="branch">
      @if($allBranchesAllowed)<option value="">{{ __('All branches') }}</option>@else<option value="">{{ __('All my branches') }}</option>@endif
      @foreach($branchOptions as $id => $name)
      <option value="{{ $id }}" @selected((int) $filters['branch'] === (int) $id)>{{ $name }}</option>
      @endforeach
    </select>
  </div>
  @endif
  <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>{{ __('Apply') }}</button></div>
  <div class="col-12"><small class="text-muted">{{ __('Dates are used when the period is set to a custom range.') }}</small></div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div><strong>{{ $report['period']['from'] }}</strong> - <strong>{{ $report['period']['to'] }}</strong> | {{ $report['branch_label'] }}</div>
  <div>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('library.report-circulation-export', ['format' => 'csv'] + $query) }}"><i class="fas fa-file-csv me-1"></i>{{ __('CSV') }}</a>
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('library.report-circulation-export', ['format' => 'pdf'] + $query) }}"><i class="fas fa-file-pdf me-1"></i>{{ __('PDF') }}</a>
  </div>
</div>

<div class="row row-cols-2 row-cols-md-4 g-2 mb-4">
  @foreach($report['summary'] as $key => $value)
  <div class="col"><div class="card h-100 text-center"><div class="card-body py-2">
    <div class="fs-4 fw-bold">{{ $value ?? __('n/a') }}</div><small class="text-muted">{{ $measure($key) }}</small>
  </div></div></div>
  @endforeach
</div>

@if($report['stock_note'])
<p class="text-muted small">{{ __($report['stock_note']) }}</p>
@endif

<h2 class="h5">{{ __('Issues per month') }}</h2>
<div class="table-responsive mb-4"><table class="table table-striped table-sm">
  <thead class="table-dark"><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Issues') }}</th><th class="text-end">{{ __('Returns') }}</th></tr></thead>
  <tbody>@foreach($report['monthly'] as $r)<tr><td>{{ $r['month'] }}</td><td class="text-end">{{ $r['issues'] }}</td><td class="text-end">{{ $r['returns'] }}</td></tr>@endforeach</tbody>
</table></div>

@if($report['by_branch'])
<h2 class="h5">{{ __('By branch') }}</h2>
<div class="table-responsive mb-4"><table class="table table-striped table-sm">
  <thead class="table-dark"><tr><th>{{ __('Branch') }}</th><th class="text-end">{{ __('Issues') }}</th><th class="text-end">{{ __('Returns') }}</th><th class="text-end">{{ __('Copies') }}</th><th class="text-end">{{ __('Turnover') }}</th></tr></thead>
  <tbody>@foreach($report['by_branch'] as $r)<tr><td>{{ $r['branch_id'] ? $r['name'] : __($r['name']) }}</td><td class="text-end">{{ $r['issues'] }}</td><td class="text-end">{{ $r['returns'] }}</td><td class="text-end">{{ $r['copies'] }}</td><td class="text-end">{{ $r['turnover'] ?? __('n/a') }}</td></tr>@endforeach</tbody>
</table></div>
@endif

<h2 class="h5">{{ __('Busiest titles') }}</h2>
<div class="table-responsive mb-4"><table class="table table-striped table-sm">
  <thead class="table-dark"><tr><th>{{ __('Title') }}</th><th>{{ __('Call number') }}</th><th class="text-end">{{ __('Issues') }}</th></tr></thead>
  <tbody>@forelse($report['top_titles'] as $r)<tr><td>{{ $r['title'] }}</td><td>{{ $r['call_number'] }}</td><td class="text-end">{{ $r['issues'] }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center">{{ __('No loans in this period.') }}</td></tr>@endforelse</tbody>
</table></div>

<h2 class="h5">{{ __('Dead stock') }} <span class="badge bg-secondary">{{ $report['dead_stock']['total'] }}</span></h2>
<p class="text-muted small">{{ __('Copies in stock with no loan in this period, longest unused first.') }}</p>
<div class="table-responsive mb-4"><table class="table table-striped table-sm">
  <thead class="table-dark"><tr><th>{{ __('Title') }}</th><th>{{ __('Barcode') }}</th><th>{{ __('Call number') }}</th><th>{{ __('Last loan') }}</th></tr></thead>
  <tbody>@forelse($report['dead_stock']['rows'] as $r)<tr><td>{{ $r['title'] }}</td><td>{{ $r['barcode'] }}</td><td>{{ $r['call_number'] }}</td><td>{{ $r['last_loan'] ?? __('Never') }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center">{{ __('Every copy in stock was borrowed.') }}</td></tr>@endforelse</tbody>
</table></div>

<div class="row">
  <div class="col-md-5">
    <h2 class="h5">{{ __('Patron activity') }}</h2>
    <div class="table-responsive mb-4"><table class="table table-striped table-sm">
      <thead class="table-dark"><tr><th>{{ __('Loans in period') }}</th><th class="text-end">{{ __('Patrons') }}</th></tr></thead>
      <tbody>@foreach($report['patron_activity'] as $r)<tr><td>{{ $r['band'] }}</td><td class="text-end">{{ $r['patrons'] }}</td></tr>@endforeach</tbody>
    </table></div>
  </div>
  <div class="col-md-7">
    <h2 class="h5">{{ __('Fines') }}</h2>
    <div class="table-responsive mb-4"><table class="table table-striped table-sm">
      <thead class="table-dark"><tr><th>{{ __('Currency') }}</th><th class="text-end">{{ __('Assessed') }}</th>@if($report['fines_settled'])<th class="text-end">{{ __('Paid') }}</th><th class="text-end">{{ __('Waived') }}</th>@endif<th class="text-end">{{ __('Outstanding') }}</th></tr></thead>
      <tbody>@forelse($report['fines'] as $r)<tr><td>{{ $r['currency'] }}</td><td class="text-end">{{ $r['assessed'] }}</td>@if($report['fines_settled'])<td class="text-end">{{ $r['paid'] }}</td><td class="text-end">{{ $r['waived'] }}</td>@endif<td class="text-end">{{ $r['outstanding'] }}</td></tr>@empty<tr><td colspan="3" class="text-muted text-center">{{ __('No fines in this period.') }}</td></tr>@endforelse</tbody>
    </table></div>
    @if($report['fines_note'])<p class="text-muted small">{{ __($report['fines_note']) }}</p>@endif
  </div>
</div>
@endsection
