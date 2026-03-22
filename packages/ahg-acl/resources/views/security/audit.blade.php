@extends('theme::layouts.1col')
@section('title', 'Security Audit Trail')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-history me-2"></i>Security Audit Trail</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr style="background:var(--ahg-primary);color:#fff"><th>Date</th><th>User</th><th>Action</th><th>Target</th><th>Details</th></tr></thead><tbody>@forelse($auditEntries??[] as $e)<tr><td><small>{{ $e->created_at??"" }}</small></td><td>{{ e($e->username??"") }}</td><td><span class="badge bg-secondary">{{ e($e->action??"") }}</span></td><td>{{ e($e->target??"") }}</td><td><small>{{ \Illuminate\Support\Str::limit($e->details??"",80) }}</small></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No entries.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
