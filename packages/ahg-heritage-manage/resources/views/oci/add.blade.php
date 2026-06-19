@extends('theme::layouts.1col')
@section('title', 'Record OCI Movement')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="bi bi-journal-plus me-2"></i>{{ __('Record OCI / Revaluation Movement') }}</h1>
    <p class="text-muted">{{ __('Service splits the entry between OCI, P&L, and Reserve per GRAP 103.51 / IPSAS 45.74. Fill the fields that match the chosen movement type.') }}</p>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction }}">
      @csrf

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="bi bi-pencil me-2"></i>{{ __('Movement Details') }}</div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label">{{ __('Movement Type') }} <span class="text-danger">*</span></label>
              <select name="movement_type" class="form-select" required>
                <option value="revaluation">{{ __('Revaluation (use previous + new value)') }}</option>
                <option value="impairment">{{ __('Impairment (use amount)') }}</option>
                <option value="reversal">{{ __('Reversal of prior impairment (use amount)') }}</option>
                <option value="disposal">{{ __('Disposal (use proceeds + carrying at disposal)') }}</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Heritage Asset ID') }} <span class="text-danger">*</span></label>
              <input type="number" name="heritage_asset_id" class="form-control" required value="{{ old('heritage_asset_id') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">{{ __('Information Object ID') }}</label>
              <input type="number" name="information_object_id" class="form-control" value="{{ old('information_object_id') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Valuation Date') }} <span class="text-danger">*</span></label>
              <input type="date" name="valuation_date" class="form-control" required value="{{ old('valuation_date', date('Y-m-d')) }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Currency') }}</label>
              <input type="text" name="currency" class="form-control" maxlength="3" value="{{ old('currency', 'ZAR') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Previous Value') }} <small class="text-muted">{{ __('(revaluation)') }}</small></label>
              <input type="number" step="0.01" name="previous_value" class="form-control" value="{{ old('previous_value') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('New Value') }} <small class="text-muted">{{ __('(revaluation)') }}</small></label>
              <input type="number" step="0.01" name="new_value" class="form-control" value="{{ old('new_value') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Amount') }} <small class="text-muted">{{ __('(impairment / reversal)') }}</small></label>
              <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Disposal Proceeds') }}</label>
              <input type="number" step="0.01" name="disposal_proceeds" class="form-control" value="{{ old('disposal_proceeds') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Carrying at Disposal') }}</label>
              <input type="number" step="0.01" name="carrying_at_disposal" class="form-control" value="{{ old('carrying_at_disposal') }}">
            </div>
            <div class="col-md-3">
              <label class="form-label">{{ __('Valuer') }}</label>
              <select name="valuer_id" class="form-select">
                <option value="">{{ __('-- none --') }}</option>
                @foreach($valuers as $v)
                  <option value="{{ $v->id }}">{{ $v->name }}{{ $v->credential ? ' (' . $v->credential . ')' : '' }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">{{ __('Valuation Method') }}</label>
              <input type="text" name="valuation_method" class="form-control" placeholder="{{ __('cost / fair_value / market / depreciated_replacement / nominal') }}" value="{{ old('valuation_method') }}">
            </div>
            <div class="col-md-12">
              <label class="form-label">{{ __('Reason / Notes') }}</label>
              <textarea name="reason" class="form-control" rows="3">{{ old('reason') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn atom-btn-white"><i class="bi bi-check2 me-1"></i>{{ __('Record') }}</button>
        <a href="{{ route('heritage.oci.index') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>
@endsection
