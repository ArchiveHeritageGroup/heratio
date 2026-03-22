@extends('theme::layouts.1col')
@section('title', 'Overdue Items')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-exclamation-triangle me-2"></i>Overdue Items</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Patron</th><th>Item</th><th>Due</th><th>Days Overdue</th><th>Fine</th></tr></thead><tbody>@forelse($overdueItems??[] as $o)<tr><td>{{ e($o->patron_name??"") }}</td><td>{{ e($o->title??"") }}</td><td>{{ $o->due_date??"" }}</td><td><span class="badge bg-danger">{{ $o->days_overdue??0 }}</span></td><td>{{ number_format($o->fine_amount??0,2) }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No overdue items.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
