@extends('theme::layouts.1col')
@section('title', 'Purchase Order')
@section('content')
@php
    $statuses = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','library_order_status')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $disposalReasons = \Illuminate\Support\Facades\DB::table('ahg_dropdown')->where('taxonomy','acq_disposal_reason')->where('is_active',1)->orderBy('sort_order')->get(['code','label']);
    $editable = !in_array($order->status??'', ['received','cancelled']);
@endphp
<div class="container py-4">
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('info'))<div class="alert alert-info">{{ session('info') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="d-flex justify-content-between align-items-start mb-3"><div><h1 class="mb-1"><i class="fas fa-file-invoice me-2"></i>{{ e($order->order_number??'') }}</h1><span class="badge bg-secondary">{{ e($order->status??'') }}</span> @if($order->written_off_reason ?? null)<span class="badge bg-danger ms-1">{{ __('Written off') }}: {{ e($order->written_off_reason) }}</span>@endif</div><div><a href="{{ route('library.acquisition-order-edit',$order->id) }}" class="btn btn-outline-secondary"><i class="fas fa-edit me-1"></i>{{ __('Edit') }}</a> <a href="{{ route('library.acquisitions') }}" class="btn btn-outline-secondary"><i class="fas fa-list me-1"></i>{{ __('All Orders') }}</a></div></div>

<div class="row">
<div class="col-md-8">
<div class="card mb-3"><div class="card-header"><i class="fas fa-info-circle me-1"></i>{{ __('Order Details') }}</div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">{{ __('Vendor') }}</dt><dd class="col-sm-9">{{ e($order->vendor_name??'-') }}</dd><dt class="col-sm-3">{{ __('Order Date') }}</dt><dd class="col-sm-9">{{ $order->order_date??'-' }}</dd><dt class="col-sm-3">{{ __('Expected') }}</dt><dd class="col-sm-9">{{ $order->expected_date??'-' }}</dd><dt class="col-sm-3">{{ __('Type') }}</dt><dd class="col-sm-9">{{ e($order->order_type??'-') }}</dd><dt class="col-sm-3">{{ __('Budget') }}</dt><dd class="col-sm-9">{{ e($order->budget_code??'-') }}</dd>@if($order->notes ?? null)<dt class="col-sm-3">{{ __('Notes') }}</dt><dd class="col-sm-9">{!! nl2br(e($order->notes)) !!}</dd>@endif</dl></div></div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><i class="fas fa-list-ol me-1"></i>{{ __('Order Lines') }}</span>@if($editable)<form method="post" action="{{ route('library.acquisition-order-receive-all',$order->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Mark all pending lines as received?') }}')">@csrf<button class="btn btn-sm btn-success"><i class="fas fa-box-open me-1"></i>{{ __('Receive All') }}</button></form>@endif</div><div class="card-body p-0"><div id="acq-lines-container">@include('ahg-library::acquisition._order-lines', ['lines'=>$lines, 'order'=>$order, 'editable'=>$editable])</div></div></div>
</div>

<div class="col-md-4">
<div class="card mb-3"><div class="card-header"><i class="fas fa-calculator me-1"></i>{{ __('Totals') }}</div><div class="card-body"><dl class="row mb-0"><dt class="col-7">{{ __('Subtotal') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->subtotal??0),2) }}</dd><dt class="col-7">{{ __('Tax') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->tax??0),2) }}</dd><dt class="col-7">{{ __('Shipping') }}</dt><dd class="col-5 text-end">{{ number_format((float)($order->shipping??0),2) }}</dd><dt class="col-7"><strong>{{ __('Total') }}</strong></dt><dd class="col-5 text-end"><strong>{{ number_format((float)($order->total??0),2) }} {{ e($order->currency??'') }}</strong></dd></dl></div></div>

@if($budget)<div class="card mb-3"><div class="card-header"><i class="fas fa-wallet me-1"></i>{{ __('Budget') }}</div><div class="card-body"><strong>{{ e($budget->fund_name??'') }}</strong> <span class="text-muted">({{ e($budget->budget_code??'') }})</span>@php $alloc=(float)($budget->allocated_amount??0); $spent=(float)($budget->spent_amount??0); $pct=$alloc>0?min(100,round($spent/$alloc*100)):0; @endphp<div class="progress mt-2" style="height:18px"><div class="progress-bar {{ $pct>=90?'bg-danger':($pct>=70?'bg-warning':'bg-success') }}" role="progressbar" style="width:{{ $pct }}%">{{ $pct }}%</div></div><small class="text-muted">{{ number_format($spent,2) }} / {{ number_format($alloc,2) }} {{ e($budget->currency??'') }}</small></div></div>@endif

<div class="card mb-3"><div class="card-header"><i class="fas fa-exchange-alt me-1"></i>{{ __('Status') }}</div><div class="card-body"><form method="post" action="{{ route('library.acquisition-order-transition',$order->id) }}" class="d-flex gap-2">@csrf<select name="status" class="form-select form-select-sm">@foreach($statuses as $s)<option value="{{ $s->code }}" {{ ($order->status??'')===$s->code?'selected':'' }}>{{ $s->label }}</option>@endforeach</select><button class="btn btn-sm btn-primary">{{ __('Set') }}</button></form></div></div>

@if(($order->status??'') !== 'cancelled')<div class="card border-danger"><div class="card-header bg-danger text-white"><i class="fas fa-ban me-1"></i>{{ __('Write Off (GRAP 103 / IPSAS 17)') }}</div><div class="card-body"><form method="post" action="{{ route('library.acquisition-order-write-off',$order->id) }}" onsubmit="return confirm('{{ __('Write off this order? This releases its budget commitment.') }}')">@csrf<div class="mb-2"><label class="form-label form-label-sm">{{ __('Reason') }}</label><select name="reason" class="form-select form-select-sm" required>@foreach($disposalReasons as $r)<option value="{{ $r->code }}">{{ $r->label }}</option>@endforeach</select></div><div class="mb-2"><textarea name="note" class="form-control form-control-sm" rows="2" placeholder="{{ __('Note (optional)') }}"></textarea></div><button class="btn btn-sm btn-outline-danger w-100"><i class="fas fa-ban me-1"></i>{{ __('Write Off') }}</button></form></div></div>@endif
</div>
</div>
</div>
@endsection
