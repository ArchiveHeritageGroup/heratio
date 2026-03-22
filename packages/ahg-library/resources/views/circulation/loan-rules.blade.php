@extends('theme::layouts.1col')
@section('title', 'Loan Rules')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-gavel me-2"></i>Loan Rules</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Patron Type</th><th>Material Type</th><th>Loan (days)</th><th>Renewals</th><th>Max Items</th><th>Fine/Day</th></tr></thead><tbody>@forelse($rules??[] as $r)<tr><td>{{ e($r->patron_type??"All") }}</td><td>{{ e($r->material_type??"All") }}</td><td>{{ $r->loan_days??14 }}</td><td>{{ $r->max_renewals??2 }}</td><td>{{ $r->max_items??5 }}</td><td>{{ number_format($r->fine_per_day??0,2) }}</td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">No rules.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
