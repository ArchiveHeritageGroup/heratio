@extends('theme::layouts.1col')
@section('title', 'Creators Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-user-edit me-2"></i>Creators Report</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Creator</th><th>Role</th><th>Attribution</th><th>School</th><th class="text-end">Objects</th></tr></thead><tbody>@forelse($creators??[] as $c)<tr><td><strong>{{ e($c->creator_identity??"") }}</strong></td><td><span class="badge bg-secondary">{{ e($c->creator_role??"-") }}</span></td><td>{{ e($c->creator_attribution??"-") }}</td><td>{{ e($c->school??"-") }}</td><td class="text-end"><span class="badge bg-primary">{{ $c->object_count??0 }}</span></td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No creators.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
