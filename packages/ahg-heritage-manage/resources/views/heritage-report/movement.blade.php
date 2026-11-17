@extends('theme::layouts.1col')
@section('title', __('Movement Report'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-exchange-alt me-2"></i>{{ __('Movement Report') }}</h1>
      <button type="button" class="btn atom-btn-white d-print-none" data-heritage-print><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
    </div>

    <form method="get" class="card mb-4 d-print-none">
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label" for="mFrom">{{ __('From') }}</label>
          <input type="date" id="mFrom" name="from" class="form-control" value="{{ $from }}">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="mTo">{{ __('To') }}</label>
          <input type="date" id="mTo" name="to" class="form-control" value="{{ $to }}">
        </div>
        <div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn atom-btn-white">{{ __('Filter') }}</button></div>
      </div>
    </form>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __(':from to :to', ['from' => $from, 'to' => $to]) }} - {{ __(':n movements', ['n' => count($movements)]) }}</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Type') }}</th>
                <th>{{ __('From') }}</th>
                <th>{{ __('To') }}</th>
                <th>{{ __('Authorized By') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($movements as $m)
                <tr>
                  <td>{{ $m->movement_date }}</td>
                  <td><a href="{{ route('heritage.accounting.view', $m->heritage_asset_id) }}">{{ $m->identifier ?: '#' . $m->heritage_asset_id }}</a></td>
                  <td>{{ $m->title ?: '-' }}</td>
                  <td><span class="badge bg-info">{{ $movementTypes[$m->movement_type] ?? ucfirst(str_replace('_', ' ', $m->movement_type)) }}</span></td>
                  <td>{{ $m->from_location ?: '-' }}</td>
                  <td>{{ $m->to_location ?: '-' }}</td>
                  <td>{{ $m->authorized_by ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No movements in this period.') }}</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="mt-4 d-print-none">
      <a href="{{ route('heritage.hreport.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('Back to Reports') }}</a>
    </div>
  </div>
</div>
@include('ahg-heritage-manage::partials._print-script')
@endsection
