@extends('theme::layouts.1col')
@section('title', 'Circulation')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-exchange-alt me-2"></i>Circulation</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Patron</th><th>Item</th><th>Checkout</th><th>Due</th><th>Status</th></tr></thead><tbody>@forelse($loans??[] as $l)<tr><td>{{ e($l->patron_name??"") }}</td><td>{{ e($l->title??"") }}</td><td>{{ $l->checkout_date??"" }}</td><td>{{ $l->due_date??"" }}</td><td><span class="badge bg-{{ ($l->status??"")=="overdue"?"danger":"success" }}">{{ ucfirst($l->status??"active") }}</span></td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No circulations.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
