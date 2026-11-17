@extends('theme::layouts.1col')
@section('title', __('Valuation Report'))
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3 d-print-none">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h1 class="h3 mb-0"><i class="fas fa-dollar-sign me-2"></i>{{ __('Valuation Report') }}</h1>
      <button type="button" class="btn atom-btn-white d-print-none" data-heritage-print><i class="fas fa-print me-1"></i>{{ __('Print') }}</button>
    </div>
    <p class="text-muted">{{ __('The 100 most recently valued assets.') }}@if($currency) {{ __('Amounts in :c.', ['c' => $currency]) }}@endif</p>

    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
              <tr>
                <th>{{ __('Identifier') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Class') }}</th>
                <th>{{ __('Last Valuation') }}</th>
                <th class="text-end">{{ __('Valuation Amount') }}</th>
                <th class="text-end">{{ __('Carrying Amount') }}</th>
                <th class="text-end">{{ __('Revaluation Surplus') }}</th>
                <th class="text-end">{{ __('Impairment') }}</th>
              </tr>
            </thead>
            <tbody>
              @forelse($assets as $asset)
                <tr>
                  <td><a href="{{ route('heritage.accounting.view', $asset->id) }}">{{ $asset->identifier ?: '#' . $asset->id }}</a></td>
                  <td>{{ $asset->title ?: '-' }}</td>
                  <td>{{ $asset->class_name ?: '-' }}</td>
                  <td>{{ $asset->last_valuation_date }}</td>
                  <td class="text-end">{{ number_format((float) $asset->last_valuation_amount, 2) }}</td>
                  <td class="text-end">{{ number_format((float) $asset->current_carrying_amount, 2) }}</td>
                  <td class="text-end text-success">{{ number_format((float) $asset->revaluation_surplus, 2) }}</td>
                  <td class="text-end text-danger">{{ number_format((float) $asset->impairment_loss, 2) }}</td>
                </tr>
              @empty
                <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No assets have been valued yet.') }}</td></tr>
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
