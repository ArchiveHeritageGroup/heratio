@extends('theme::layouts.1col')
@section('title', isset($vendor) && $vendor ? 'Edit Vendor' : 'New Vendor')
@section('content')
@php
    $editing = isset($vendor) && $vendor;
    $action  = $editing ? route('library.acquisition-vendor-update',$vendor->id) : route('library.acquisition-vendor-store');
    $v = fn(string $f, $d=null) => old($f, $editing ? ($vendor->{$f} ?? $d) : $d);
    $types = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','library_vendor_type')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
@endphp
<div class="container py-4">
<h1 class="mb-3"><i class="fas fa-truck me-2"></i>{{ $editing ? __('Edit Vendor') : __('New Vendor') }}</h1>
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
<div class="card"><div class="card-body"><form method="post" action="{{ $action }}">@csrf @if($editing)@method('PUT')@endif
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ e($v('name')) }}" required></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Vendor Code') }}</label><input type="text" name="vendor_code" class="form-control" value="{{ e($v('vendor_code')) }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Type') }}</label><select name="vendor_type" class="form-select">@foreach($types as $t)<option value="{{ $t->code }}" {{ $v('vendor_type')===$t->code?'selected':'' }}>{{ $t->label }}</option>@endforeach</select></div></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('Contact Name') }}</label><input type="text" name="contact_name" class="form-control" value="{{ e($v('contact_name')) }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Email') }}</label><input type="email" name="email" class="form-control" value="{{ e($v('email')) }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Phone') }}</label><input type="text" name="phone" class="form-control" value="{{ e($v('phone')) }}"></div></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">{{ __('Website') }}</label><input type="text" name="website" class="form-control" value="{{ e($v('website')) }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('Account Number') }}</label><input type="text" name="account_number" class="form-control" value="{{ e($v('account_number')) }}"></div>
<div class="col-md-3 mb-3"><label class="form-label">{{ __('SAN') }}</label><input type="text" name="san" class="form-control" value="{{ e($v('san')) }}"></div></div>
<div class="mb-3"><label class="form-label">{{ __('Address') }}</label><textarea name="address" class="form-control" rows="2">{{ e($v('address')) }}</textarea></div>
<div class="row"><div class="col-md-4 mb-3"><label class="form-label">{{ __('City') }}</label><input type="text" name="city" class="form-control" value="{{ e($v('city')) }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Country') }}</label><input type="text" name="country" class="form-control" value="{{ e($v('country')) }}"></div>
<div class="col-md-4 mb-3"><label class="form-label">{{ __('Currency') }}</label><input type="text" name="currency" class="form-control" value="{{ e($v('currency','ZAR')) }}" maxlength="3"></div></div>
<div class="mb-3"><label class="form-label">{{ __('Notes') }}</label><textarea name="notes" class="form-control" rows="2">{{ e($v('notes')) }}</textarea></div>
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="is_active" value="1" id="is_active" {{ $v('is_active', true) ? 'checked' : '' }}><label class="form-check-label" for="is_active">{{ __('Active') }}</label></div>
<button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button> <a href="{{ route('library.acquisition-vendors') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
@if($editing)<form method="post" action="{{ route('library.acquisition-vendor-destroy',$vendor->id) }}" class="d-inline float-end" onsubmit="return confirm('{{ __('Delete this vendor?') }}')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button></form>@endif
</form></div></div>
</div>
@endsection
