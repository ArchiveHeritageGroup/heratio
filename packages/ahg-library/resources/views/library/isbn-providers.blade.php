@extends('theme::layouts.1col')
@section('title', 'ISBN Providers')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-barcode me-2"></i>ISBN Lookup Providers</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Provider</th><th>API URL</th><th class="text-center">Active</th><th class="text-center">Priority</th><th>Actions</th></tr></thead><tbody>@forelse($providers??[] as $p)<tr><td><strong>{{ e($p->name??"") }}</strong></td><td><small>{{ e($p->api_url??"") }}</small></td><td class="text-center">@if($p->active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Disabled</span>@endif</td><td class="text-center">{{ $p->priority??0 }}</td><td><a href="{{ route("library.isbn-provider-edit",$p->id??0) }}" class="btn btn-sm atom-btn-white"><i class="fas fa-pencil-alt"></i></a></td></tr>@empty<tr><td colspan="5" class="text-muted text-center py-3">No providers.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
