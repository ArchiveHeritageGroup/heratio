@extends('theme::layouts.1col')
@section('title', 'Subjects Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-tags me-2"></i>Subjects</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Subject</th><th class="text-end">Items</th></tr></thead><tbody>@forelse($subjects??[] as $s)<tr><td><strong>{{ e($s->name??"") }}</strong></td><td class="text-end"><span class="badge bg-primary">{{ $s->item_count??0 }}</span></td></tr>@empty<tr><td colspan="2" class="text-muted text-center py-3">No subjects.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
