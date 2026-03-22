@extends('theme::layouts.1col')
@section('title', 'Compartment Access')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-door-open me-2"></i>Compartment Access</h1><div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Active Grants</h5></div><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>User</th><th>Compartment</th><th>Granted By</th><th>Granted</th><th>Expires</th></tr></thead><tbody>@forelse($grants??[] as $g)<tr><td>{{ e($g->username??"") }}</td><td><span class="badge bg-dark">{{ e($g->compartment_name??"") }}</span></td><td>{{ e($g->granted_by_name??"") }}</td><td>{{ $g->granted_at??"" }}</td><td>{{ $g->expires_at??"Never" }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No grants.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
