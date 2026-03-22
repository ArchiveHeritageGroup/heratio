@extends('theme::layouts.1col')
@section('title', 'Provenance')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-history me-2"></i>Provenance &amp; Custody History</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>#</th><th>Owner</th><th>Location</th><th>Period</th><th>Transfer</th><th>Certainty</th></tr></thead><tbody>@forelse($provenanceChain??[] as $i=>$p)<tr><td>{{ $i+1 }}</td><td><strong>{{ e($p->owner??"") }}</strong></td><td>{{ e($p->location??"") }}</td><td>{{ e($p->period??"") }}</td><td>{{ e($p->transfer_type??"") }}</td><td><span class="badge bg-{{ ($p->certainty??"")=="certain"?"success":"warning" }}">{{ ucfirst($p->certainty??"") }}</span></td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">No provenance data.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
