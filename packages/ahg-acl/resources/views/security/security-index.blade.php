@extends('theme::layouts.1col')
@section('title', 'Security Clearances')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-shield-alt me-2"></i>Security Clearance Management</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr style="background:var(--ahg-primary);color:#fff"><th>User</th><th>Clearance</th><th>Granted By</th><th>Date</th><th>Status</th></tr></thead><tbody>@forelse($clearances??[] as $c)<tr><td><strong>{{ e($c->username??"") }}</strong></td><td><span class="badge" style="background-color:{{ $c->color??"#6c757d" }}">{{ e($c->clearance_name??"None") }}</span></td><td>{{ e($c->granted_by_name??"") }}</td><td>{{ $c->granted_at??"" }}</td><td>@if($c->active??true)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No clearances.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
