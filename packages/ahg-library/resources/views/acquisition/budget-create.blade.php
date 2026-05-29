@extends('theme::layouts.1col')
@section('title', 'New Budget')
@section('content')
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-wallet me-2"></i>{{ __('New Budget') }}</h1>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body"><form method="post" action="{{ route('library.acquisition-budget-store') }}">@csrf
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('Fund Name') }} <span class="text-danger">*</span></label><input type="text" name="fund_name" class="form-control" value="{{ old('fund_name') }}" required></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Budget Code') }} <span class="badge bg-secondary ms-1">{{ __('Auto if blank') }}</span></label><input type="text" name="budget_code" class="form-control" value="{{ old('budget_code') }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Fiscal Year') }} <span class="text-danger">*</span></label><input type="text" name="fiscal_year" class="form-control" value="{{ old('fiscal_year', date('Y')) }}" maxlength="9" required></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('Allocated Amount') }} <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" name="allocated_amount" class="form-control" value="{{ old('allocated_amount') }}" required></div>
<div class="col-md-2 mb-3"><label class="form-label">{{ __('Currency') }}</label><input type="text" name="currency" class="form-control" value="{{ old('currency','ZAR') }}" maxlength="3"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Category') }}</label><input type="text" name="category" class="form-control" value="{{ old('category') }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Department') }}</label><input type="text" name="department" class="form-control" value="{{ old('department') }}"></div></div>
<div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
<button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Create Budget') }}</button> <a href="{{ route('library.acquisition-budgets') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
</form></div></div>
</div>
@endsection
