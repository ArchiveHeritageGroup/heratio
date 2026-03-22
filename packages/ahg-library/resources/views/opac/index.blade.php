@extends('theme::layouts.1col')
@section('title', 'OPAC')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-search me-2"></i>Online Public Access Catalogue</h1><form method="get" class="mb-4"><div class="input-group"><input type="text" name="q" class="form-control form-control-lg" value="{{ request("q") }}" placeholder="Search the catalogue..."><button type="submit" class="btn atom-btn-white"><i class="fas fa-search me-1"></i>Search</button></div></form>@if($results??null)<div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Title</th><th>Author</th><th>Type</th><th>Available</th></tr></thead><tbody>@forelse($results as $r)<tr><td><a href="{{ route("library.opac-view",$r->slug??"") }}">{{ e($r->title??"") }}</a></td><td>{{ e($r->author??"") }}</td><td><span class="badge bg-secondary">{{ ucfirst($r->material_type??"") }}</span></td><td>@if($r->available??true)<span class="badge bg-success">Available</span>@else<span class="badge bg-danger">Out</span>@endif</td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No results.</td></tr>@endforelse</tbody></table></div></div>@endif
</div>
@endsection
