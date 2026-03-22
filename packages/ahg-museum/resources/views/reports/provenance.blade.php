@extends('theme::layouts.1col')
@section('title', 'Provenance Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-history me-2"></i>Provenance Report</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Object</th><th>Provenance</th><th>Legal Status</th><th>Rights Holder</th></tr></thead><tbody>@forelse($records??[] as $r)<tr><td><strong>{{ e($r->title??"Untitled") }}</strong></td><td>{{ \Illuminate\Support\Str::limit($r->provenance??"-",150) }}</td><td><span class="badge bg-info">{{ e($r->legal_status??"-") }}</span></td><td>{{ e($r->rights_holder??"-") }}</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No data.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
