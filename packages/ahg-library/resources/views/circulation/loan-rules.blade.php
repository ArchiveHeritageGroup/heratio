@extends('theme::layouts.1col')
@section('title', __('Loan Rules'))
@section('body-class', 'admin library')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="mb-0"><i class="fas fa-gavel me-2"></i>{{ __('Loan Rules') }}</h1>
  <a href="{{ route('library.calendar') }}" class="btn btn-sm atom-btn-white">
    <i class="fas fa-calendar-alt me-1"></i>{{ __('Opening hours and closures') }}
  </a>
</div>

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())
  <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

{{--
  The columns below bind the real library_loan_rule column names - #1477.
  This table previously read $r->loan_days and $r->max_items, neither of which
  exists: the column is loan_period_days, and there is no per-rule item cap at
  all. Both fell through to `?? 14` and `?? 5`, which happen to equal the
  library_default_loan_days and library_patron.max_checkouts defaults, so every
  rule rendered as "14 days / 5 max items" and looked plausible while
  contradicting what circulation actually enforced.

  Max Items is gone rather than fixed. A per-rule item cap is a missing
  concept, not a misnamed column, and displaying a number nothing enforces is
  what caused the problem in the first place. #1475 covers adding it properly.
--}}
<div class="card">
  <div class="card-body p-0">
    <table class="table table-striped mb-0">
      <thead>
        <tr>
          @if($branchAware ?? false)<th>{{ __('Branch') }}</th>@endif
          <th>{{ __('Patron Type') }}</th>
          <th>{{ __('Material Type') }}</th>
          <th>{{ __('Loan (days)') }}</th>
          <th>{{ __('Renewals') }}</th>
          <th>{{ __('Grace (open days)') }}</th>
          <th>{{ __('Fine/Day') }}</th>
          <th>{{ __('Loanable') }}</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        @forelse($rules ?? [] as $r)
          <tr>
            @if($branchAware ?? false)
              @php $branchId = isset($r->branch_id) ? (int) $r->branch_id : null; @endphp
              <td>
                @if($branchId === null || $branchId === 0)
                  <span class="text-muted">{{ __('All branches') }}</span>
                @else
                  {{ $branchLabels[$branchId] ?? ('#' . $branchId) }}
                @endif
              </td>
            @endif
            <td>{{ ($r->patron_type ?? '*') === '*' ? __('All') : $r->patron_type }}</td>
            <td>{{ $r->material_type ?? __('All') }}</td>
            <td>{{ $r->loan_period_days ?? '-' }}</td>
            <td>{{ $r->max_renewals ?? '-' }}</td>
            <td>{{ $r->grace_period_days ?? '-' }}</td>
            <td>{{ number_format((float) ($r->fine_per_day ?? 0), 2) }}</td>
            <td>
              @if(($r->is_loanable ?? 1))
                <span class="badge bg-success">{{ __('Yes') }}</span>
              @else
                <span class="badge bg-secondary">{{ __('No') }}</span>
              @endif
            </td>
            <td class="text-end text-nowrap">
              <a href="{{ route('library.loan-rules.edit', $r->id) }}" class="btn btn-sm atom-btn-white" title="{{ __('Edit') }}">
                <i class="fas fa-pen"></i>
              </a>
              <form method="post" action="{{ route('library.loan-rules.delete', $r->id) }}" class="d-inline"
                    onsubmit="return confirm('{{ __('Delete this loan rule?') }}');">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="{{ ($branchAware ?? false) ? 9 : 8 }}" class="text-muted text-center py-3">
              {{ __('No rules.') }}
            </td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- #1473: an editor, because until now library_loan_rule had no create,
     update or delete anywhere in the codebase. This form only adds: a
     combination that already exists is refused, because this form does not
     carry every field and would overwrite the rest. --}}
<div class="card mt-4">
  <div class="card-header"><i class="fas fa-plus me-2"></i>{{ __('Add a rule') }}</div>
  <div class="card-body">
    <form method="post" action="{{ route('library.loan-rules.save') }}" class="row g-3">
      @csrf
      @if($branchAware ?? false)
        <div class="col-md-3">
          <label class="form-label" for="lr_branch">{{ __('Branch') }}</label>
          <select name="branch_id" id="lr_branch" class="form-select">
            <option value="0">{{ __('All branches') }}</option>
            @foreach($branchLabels ?? [] as $bid => $bname)
              <option value="{{ $bid }}" @selected((int) old('branch_id', 0) === (int) $bid)>{{ $bname }}</option>
            @endforeach
          </select>
        </div>
      @endif
      <div class="col-md-3">
        <label class="form-label" for="lr_material">{{ __('Material Type') }} <span class="text-danger">*</span></label>
        <select name="material_type" id="lr_material" class="form-select" required>
          <option value="">{{ __('-- Select --') }}</option>
          @foreach($materialOptions ?? [] as $code => $label)
            <option value="{{ $code }}" @selected(old('material_type') === (string) $code)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_patron">{{ __('Patron Type') }}</label>
        <select name="patron_type" id="lr_patron" class="form-select">
          @foreach($patronOptions ?? ['*' => __('All')] as $code => $label)
            <option value="{{ $code }}" @selected(old('patron_type', '*') === (string) $code)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_days">{{ __('Loan (days)') }} <span class="text-danger">*</span></label>
        <input type="number" name="loan_period_days" id="lr_days" class="form-control" min="0" value="{{ old('loan_period_days', 14) }}" required>
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_renew">{{ __('Renewals') }}</label>
        <input type="number" name="max_renewals" id="lr_renew" class="form-control" min="0" value="{{ old('max_renewals', 2) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_grace">{{ __('Grace (open days)') }}</label>
        <input type="number" name="grace_period_days" id="lr_grace" class="form-control" min="0" value="{{ old('grace_period_days', 0) }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_fine">{{ __('Fine/Day') }}</label>
        <input type="number" step="0.01" name="fine_per_day" id="lr_fine" class="form-control" min="0" value="{{ old('fine_per_day', '0.00') }}">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="lr_cap">{{ __('Fine cap') }}</label>
        <input type="number" step="0.01" name="fine_cap" id="lr_cap" class="form-control" min="0"
               placeholder="{{ __('none') }}" value="{{ old('fine_cap') }}">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check">
          <input type="checkbox" name="is_loanable" id="lr_loanable" class="form-check-input" value="1" @checked(session()->hasOldInput() ? old('is_loanable') : true)>
          <label class="form-check-label" for="lr_loanable">{{ __('Loanable') }}</label>
        </div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save rule') }}</button>
      </div>
    </form>
  </div>
</div>
@endsection