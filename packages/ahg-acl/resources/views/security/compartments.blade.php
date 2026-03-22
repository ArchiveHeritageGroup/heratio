@extends('theme::layouts.1col')
@section('title', 'Security Compartments')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-boxes me-2"></i>Security Compartments</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr style="background:var(--ahg-primary);color:#fff"><th>Name</th><th>Code</th><th>Description</th><th class="text-center">Members</th><th class="text-center">Objects</th></tr></thead><tbody>@forelse($compartments??[] as $c)<tr><td><strong>{{ e($c->name??"") }}</strong></td><td><code>{{ e($c->code??"") }}</code></td><td>{{ \Illuminate\Support\Str::limit($c->description??"",60) }}</td><td class="text-center">{{ $c->member_count??0 }}</td><td class="text-center">{{ $c->object_count??0 }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No compartments.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
