@extends('theme::layouts.1col')
@section('title', 'Acquisitions')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-shopping-cart me-2"></i>Acquisitions</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Order #</th><th>Vendor</th><th>Date</th><th class="text-end">Items</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>@forelse($orders??[] as $o)<tr><td><a href="{{ route("library.acquisition-order",$o->id??0) }}">{{ e($o->order_number??"") }}</a></td><td>{{ e($o->vendor_name??"") }}</td><td>{{ $o->order_date??"" }}</td><td class="text-end">{{ $o->line_count??0 }}</td><td class="text-end">{{ number_format($o->total_amount??0,2) }}</td><td><span class="badge bg-secondary">{{ ucfirst($o->status??"") }}</span></td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">No acquisitions.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
