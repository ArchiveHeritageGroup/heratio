@extends('theme::layouts.1col')
@section('title', 'Purchase Order')
@section('content')
<div class="container py-4">
<h1>Purchase Order: {{ e($order->order_number??"") }}</h1><div class="card mb-4"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Vendor</dt><dd class="col-sm-9">{{ e($order->vendor_name??"") }}</dd><dt class="col-sm-3">Date</dt><dd class="col-sm-9">{{ $order->order_date??"" }}</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst($order->status??"") }}</span></dd></dl></div></div><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Order Lines</h5></div><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>ISBN</th><th>Title</th><th>Qty</th><th class="text-end">Price</th><th class="text-end">Total</th></tr></thead><tbody>@forelse($lines??[] as $l)<tr><td><code>{{ e($l->isbn??"") }}</code></td><td>{{ e($l->title??"") }}</td><td>{{ $l->quantity??1 }}</td><td class="text-end">{{ number_format($l->unit_price??0,2) }}</td><td class="text-end">{{ number_format(($l->unit_price??0)*($l->quantity??1),2) }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No lines.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
