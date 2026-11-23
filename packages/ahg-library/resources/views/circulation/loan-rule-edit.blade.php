@extends('theme::layouts.1col')
@section('title', __('Edit Loan Rule'))
@section('body-class', 'admin library')

@section('content')
<h1><i class="fas fa-gavel me-2"></i>{{ __('Edit Loan Rule') }}</h1>

@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="card">
  <div class="card-body">
    <form method="post" action="{{ route('library.loan-rules.update', $rule->id) }}" class="row g-3">
      @csrf
      @method('PUT')
      @if($branchAware)
        <div class="col-md-4">
          <label class="form-label" for="lr_branch">{{ __('Branch') }}</label>
          <select name="branch_id" id="lr_branch" class="form-select">
            <option value="0">{{ __('All branches') }}</option>
            @foreach($branchLabels as $bid => $bname)
              <option value="{{ $bid }}" @selected((int) old('branch_id', $rule->branch_id ?? 0) === (int) $bid)>{{ $bname }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="col-md-4">
        <label class="form-label" for="lr_material">{{ __('Material Type') }} <span class="text-danger">*</span></label>
        <select name="material_type" id="lr_material" class="form-select" required>
          @foreach($materialOptions as $code => $label)
            <option value="{{ $code }}" @selected(old('material_type', $rule->material_type) === (string) $code)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label" for="lr_patron">{{ __('Patron Type') }}</label>
        <select name="patron_type" id="lr_patron" class="form-select">
          @foreach($patronOptions as $code => $label)
            <option value="{{ $code }}" @selected(old('patron_type', $rule->patron_type) === (string) $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_days">{{ __('Loan (days)') }} <span class="text-danger">*</span></label>
        <input type="number" name="loan_period_days" id="lr_days" class="form-control" min="0" max="3650" required
               value="{{ old('loan_period_days', $rule->loan_period_days) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_renew">{{ __('Renewals') }}</label>
        <input type="number" name="max_renewals" id="lr_renew" class="form-control" min="0" max="99"
               value="{{ old('max_renewals', $rule->max_renewals) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_grace">{{ __('Grace (open days)') }}</label>
        <input type="number" name="grace_period_days" id="lr_grace" class="form-control" min="0" max="365"
               value="{{ old('grace_period_days', $rule->grace_period_days) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_fine">{{ __('Fine/Day') }}</label>
        <input type="number" step="0.01" name="fine_per_day" id="lr_fine" class="form-control" min="0"
               value="{{ old('fine_per_day', $rule->fine_per_day) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_cap">{{ __('Fine cap') }}</label>
        <input type="number" step="0.01" name="fine_cap" id="lr_cap" class="form-control" min="0"
               placeholder="{{ __('none') }}" value="{{ old('fine_cap', $rule->fine_cap) }}">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
          <input type="hidden" name="is_loanable" value="0">
          <input type="checkbox" name="is_loanable" id="lr_loanable" class="form-check-input" value="1"
                 @checked((bool) old('is_loanable', $rule->is_loanable))>
          <label class="form-check-label" for="lr_loanable">{{ __('Loanable') }}</label>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label" for="lr_notes">{{ __('Notes') }}</label>
        <textarea name="notes" id="lr_notes" class="form-control" rows="2">{{ old('notes', $rule->notes) }}</textarea>
      </div>
      <div class="col-12">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save rule') }}</button>
        <a href="{{ route('library.loan-rules') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
      </div>
    </form>
  </div>
</div>
@endsection
