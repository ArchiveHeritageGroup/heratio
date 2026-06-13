@extends('theme::layouts.1col')
@section('title', 'Edit Budget')
@section('content')
@php $v = fn(string $f, $d=null) => old($f, $budget->{$f} ?? $d); @endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-wallet me-2"></i>{{ __('Edit Budget') }}</h1>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body"><form method="post" action="{{ route('library.acquisition-budget-update',$budget->id) }}" autocomplete="off">@csrf @method('PUT')
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('Fund Name') }} <span class="text-danger">*</span></label><input type="text" name="fund_name" autocomplete="off" class="form-control" value="{{ e($v('fund_name')) }}" required></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Budget Code') }}</label><input type="text" class="form-control" value="{{ e($budget->budget_code??'') }}" disabled></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Fiscal Year') }} <span class="text-danger">*</span></label><input type="text" name="fiscal_year" class="form-control" value="{{ e($v('fiscal_year')) }}" maxlength="9" required></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('Allocated Amount') }} <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" name="allocated_amount" class="form-control" value="{{ $v('allocated_amount') }}" required></div>
<div class="col-md-2 mb-3"><label class="form-label">{{ __('Currency') }}</label><input type="text" name="currency" class="form-control" value="{{ e($v('currency','ZAR')) }}" maxlength="3"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Category') }}</label><input type="text" name="category" class="form-control" value="{{ e($v('category')) }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Department') }}</label><input type="text" name="department" class="form-control" value="{{ e($v('department')) }}"></div></div>
<div class="mb-3"><label class="form-label">{{ __('Status') }}</label><input type="text" name="status" class="form-control" value="{{ e($v('status','active')) }}"></div>
<div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ e($v('notes')) }}</textarea></div>
<button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button> <a href="{{ route('library.acquisition-budget',$budget->id) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
</form></div></div>
</div>
@endsection
