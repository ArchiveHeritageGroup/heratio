@extends('theme::layouts.1col')
@section('title', 'Creators Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-user-edit me-2"></i>Creators/Authors</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Name</th><th class="text-end">Works</th></tr></thead><tbody>@forelse($creators??[] as $c)<tr><td><strong>{{ e($c->name??"") }}</strong></td><td class="text-end"><span class="badge bg-primary">{{ $c->work_count??0 }}</span></td></tr>@empty<tr><td colspan="2" class="text-muted text-center py-3">No creators.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
