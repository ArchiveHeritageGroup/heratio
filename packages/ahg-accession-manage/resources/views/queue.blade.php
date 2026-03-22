@extends('theme::layouts.1col')
@section('title', 'Intake Queue (Extended)')
@section('content')
<div class="container py-4">
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route("accession.dashboard") }}">Accessions</a></li><li class="breadcrumb-item active">Intake Queue</li></ol></nav><h1><i class="fas fa-inbox me-2"></i>Intake Queue</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Identifier</th><th>Title</th><th>Status</th><th>Priority</th><th>Date</th><th>Actions</th></tr></thead><tbody>@forelse($rows??[] as $row)<tr><td><a href="{{ route("accession.show",$row->slug??"") }}">{{ e($row->identifier??"") }}</a></td><td>{{ e($row->title??"") }}</td><td><span class="badge bg-secondary">{{ e($row->status_name??"") }}</span></td><td>{{ e($row->priority_name??"") }}</td><td>{{ $row->date??"" }}</td><td><a href="{{ route("accession.queue-detail",$row->id??0) }}" class="btn btn-sm atom-btn-white">Detail</a></td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">Queue is empty.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
