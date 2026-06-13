@extends('theme::layouts.1col')
@section('title', 'New Purchase Order')
@section('content')
@php
    $types    = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','library_order_type')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $statuses = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','library_order_status')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $vendors  = \Illuminate\Support\Facades\DB::table('library_vendor')->where('is_active',1)->orderBy('name')->get(['id','name']);
@endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-plus me-2"></i>{{ __('New Purchase Order') }}</h1>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body"><form method="post" action="{{ route('library.acquisition-order-store') }}" autocomplete="off">@csrf
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('Order Number') }} <span class="badge bg-secondary ms-1">{{ __('Auto if blank') }}</span></label><input type="text" name="order_number" autocomplete="off" class="form-control" value="{{ old('order_number') }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Order Date') }}</label><input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Expected Date') }}</label><input type="date" name="expected_date" class="form-control" value="{{ old('expected_date') }}"></div></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('Vendor (registered)') }}</label><select name="vendor_id" class="form-select"><option value="">{{ __('-- none / free text --') }}</option>@foreach($vendors as $v)<option value="{{ $v->id }}" {{ old('vendor_id')==$v->id?'selected':'' }}>{{ e($v->name) }}</option>@endforeach</select></div>
<div class="col-md-6 mb-3"><label class="form-label">{{ __('Vendor name (free text)') }}</label><input type="text" name="vendor_name" class="form-control" value="{{ old('vendor_name') }}"></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('Order Type') }}</label><select name="order_type" class="form-select">@foreach($types as $t)<option value="{{ $t->code }}" {{ old('order_type')===$t->code?'selected':'' }}>{{ $t->label }}</option>@endforeach</select></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Status') }}</label><select name="status" class="form-select">@foreach($statuses as $s)<option value="{{ $s->code }}" {{ old('status')===$s->code?'selected':'' }}>{{ $s->label }}</option>@endforeach</select></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Budget') }}</label><select name="budget_code" class="form-select"><option value="">{{ __('-- none --') }}</option>@foreach($budgets??[] as $b)<option value="{{ e($b->budget_code) }}" {{ old('budget_code')===$b->budget_code?'selected':'' }}>{{ e($b->fund_name) }} ({{ e($b->budget_code) }})</option>@endforeach</select></div></div>
<div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
<button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Create Order') }}</button> <a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
</form></div></div>
</div>
@endsection
