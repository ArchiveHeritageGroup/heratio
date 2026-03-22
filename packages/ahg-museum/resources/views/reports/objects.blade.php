@extends('theme::layouts.1col')
@section('title', 'Objects Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-cube me-2"></i>Museum Objects Report</h1><div class="alert alert-info"><strong>{{ count($objects??[]) }}</strong> objects found</div><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Title</th><th>Work Type</th><th>Creator</th><th>Date</th><th>Condition</th></tr></thead><tbody>@forelse($objects??[] as $o)<tr><td><strong>{{ e($o->title??"Untitled") }}</strong></td><td>{{ e($o->work_type??"") }}</td><td>{{ e($o->creator_identity??"") }}</td><td>{{ e($o->creation_date_display??"") }}</td><td>@if($o->condition_term??null)<span class="badge bg-secondary">{{ ucfirst($o->condition_term) }}</span>@endif</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No objects.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
