@extends('theme::layouts.1col')
@section('title', 'Budgets')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-wallet me-2"></i>Acquisition Budgets</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Budget</th><th>Year</th><th class="text-end">Allocated</th><th class="text-end">Spent</th><th class="text-end">Remaining</th></tr></thead><tbody>@forelse($budgets??[] as $b)<tr><td><strong>{{ e($b->name??"") }}</strong></td><td>{{ $b->fiscal_year??"" }}</td><td class="text-end">{{ number_format($b->allocated??0,2) }}</td><td class="text-end">{{ number_format($b->spent??0,2) }}</td><td class="text-end"><strong>{{ number_format(($b->allocated??0)-($b->spent??0),2) }}</strong></td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No budgets.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
