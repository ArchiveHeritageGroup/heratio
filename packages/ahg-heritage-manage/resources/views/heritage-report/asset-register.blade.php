@extends('theme::layouts.1col')
@section('title', __('Asset Register Report'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-list me-2"></i>{{ __('Asset Register Report') }}</h1>
      <button type="button" class="btn atom-btn-white d-print-none" data-heritage-print><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
    </div>

    <form method="get" class="card mb-4 d-print-none">
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label" for="rStd">{{ __('Standard') }}</label>
          <select id="rStd" name="standard_id" class="form-select">
            <option value="">{{ __('All Standards') }}</option>
            @foreach($standards as $s)
              <option value="{{ $s->id }}" {{ (string) ($filters['standard_id'] ?? '') === (string) $s->id ? 'selected' : '' }}>{{ $s->code }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="rClass">{{ __('Class') }}</label>
          <select id="rClass" name="class_id" class="form-select">
            <option value="">{{ __('All Classes') }}</option>
            @foreach($classes as $c)
              <option value="{{ $c->id }}" {{ (string) ($filters['class_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="rStatus">{{ __('Status') }}</label>
          <select id="rStatus" name="status" class="form-select">
            <option value="">{{ __('All') }}</option>
            @foreach($statusOptions as $sv => $sl)
              <option value="{{ $sv }}" {{ ($filters['recognition_status'] ?? '') === $sv ? 'selected' : '' }}>{{ __($sl) }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-3 d-flex align-items-end"><button type="submit" class="btn atom-btn-white">{{ __('Filter') }}</button></div>
      </div>
    </form>

    <div class="card">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">{{ __('Total: :n assets', ['n' => count($assets)]) }}@if($currency) <span class="ms-2 small">({{ $currency }})</span>@endif</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Basis') }}</th>
                <th class="text-end">{{ __('Acquisition Cost') }}</th>
                <th class="text-end">{{ __('Carrying Amount') }}</th>
                <th>{{ __('Acquisition Date') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($assets as $asset)
                <tr>
                  <td><a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->object_identifier ?: '#' . $asset->id }}</a></td>
                  <td>{{ $asset->object_title ?: '-' }}</td>
                  <td>{{ $asset->class_name ?: '-' }}</td>
                  <td>{{ $statusOptions[$asset->recognition_status] ?? ucfirst(str_replace('_', ' ', (string) $asset->recognition_status)) }}</td>
                  <td>{{ $asset->measurement_basis ? ucfirst(str_replace('_', ' ', $asset->measurement_basis)) : '-' }}</td>
                  <td class="text-end">{{ number_format((float) $asset->acquisition_cost, 2) }}</td>
                  <td class="text-end">{{ number_format((float) $asset->current_carrying_amount, 2) }}</td>
                  <td>{{ $asset->acquisition_date ?: '-' }}</td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No assets match these filters.') }}</td></tr>
              @endforelse
            </tbody>
            <tfoot class="table-secondary fw-bold">
              <tr>
                <td colspan="5" class="text-end">{{ __('Totals:') }}</td>
                <td class="text-end">{{ number_format((float) $assets->sum('acquisition_cost'), 2) }}</td>
                <td class="text-end">{{ number_format((float) $assets->sum('current_carrying_amount'), 2) }}</td>
                <td></td>
              </tr>
            </tfoot>
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
