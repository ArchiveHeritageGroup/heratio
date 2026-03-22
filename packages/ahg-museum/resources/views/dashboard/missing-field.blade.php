@extends('theme::layouts.1col')
@section('title', 'Missing Field Report')
@section('content')
<div class="container py-4">
<h1>Missing Field: {{ e($fieldName??"") }}</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Title</th><th>Identifier</th><th>Repository</th><th>Actions</th></tr></thead><tbody>@forelse($records??[] as $r)<tr><td><a href="{{ route("museum.show",$r->slug??"") }}">{{ e($r->title??"Untitled") }}</a></td><td>{{ e($r->identifier??"") }}</td><td>{{ e($r->repository_name??"") }}</td><td><a href="{{ route("museum.edit",$r->slug??"") }}" class="btn btn-sm atom-btn-white"><i class="fas fa-pencil-alt me-1"></i>Edit</a></td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">All records have this field.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
