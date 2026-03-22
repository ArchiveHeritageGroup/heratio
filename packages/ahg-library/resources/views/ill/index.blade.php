@extends('theme::layouts.1col')
@section('title', 'Interlibrary Loans')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-globe me-2"></i>Interlibrary Loans</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>ILL #</th><th>Type</th><th>Title</th><th>Library</th><th>Date</th><th>Status</th></tr></thead><tbody>@forelse($requests??[] as $r)<tr><td><a href="{{ route("library.ill-view",$r->id??0) }}">{{ e($r->ill_number??"") }}</a></td><td><span class="badge bg-secondary">{{ ucfirst($r->type??"") }}</span></td><td>{{ e($r->title??"") }}</td><td>{{ e($r->library_name??"") }}</td><td>{{ $r->request_date??"" }}</td><td><span class="badge bg-secondary">{{ ucfirst($r->status??"") }}</span></td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">No ILL requests.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
