@extends('theme::layouts.1col')
@section('title', 'Catalogue Report')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-book me-2"></i>Catalogue Report</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Title</th><th>Author</th><th>Type</th><th>Call Number</th><th>ISBN</th></tr></thead><tbody>@forelse($items??[] as $i)<tr><td><a href="{{ route("library.show",$i->slug??"") }}">{{ e($i->title??"") }}</a></td><td>{{ e($i->author??"") }}</td><td><span class="badge bg-secondary">{{ ucfirst($i->material_type??"") }}</span></td><td>{{ e($i->call_number??"") }}</td><td>{{ e($i->isbn??"") }}</td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No items.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
