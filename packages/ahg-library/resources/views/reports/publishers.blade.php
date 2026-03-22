@extends('theme::layouts.1col')
@section('title', 'Publishers Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-building me-2"></i>Publishers</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Publisher</th><th>Place</th><th class="text-end">Titles</th></tr></thead><tbody>@forelse($publishers??[] as $p)<tr><td><strong>{{ e($p->publisher??"") }}</strong></td><td>{{ e($p->publication_place??"") }}</td><td class="text-end"><span class="badge bg-primary">{{ $p->title_count??0 }}</span></td></tr>@empty<tr><td colspan="3" class="text-muted text-center py-3">No publishers.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
