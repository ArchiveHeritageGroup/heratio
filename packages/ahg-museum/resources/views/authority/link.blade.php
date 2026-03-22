@extends('theme::layouts.1col')
@section('title', 'Authority Linkage')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-link me-2"></i>Authority Linkage</h1>@if($actor??null)<p class="text-muted">{{ e($actor->authorized_form_of_name??"") }}</p>@endif<div class="card"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Linked Authorities</h5></div><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Source</th><th>ID</th><th>URI</th><th>Status</th></tr></thead><tbody>@forelse($linkedAuthorities??[] as $sourceId=>$auth)<tr><td>{{ e($sources[$sourceId]["label"]??$sourceId) }}</td><td><code>{{ e($auth["id"]??"") }}</code></td><td><a href="{{ $auth["uri"]??"#" }}" target="_blank">{{ \Illuminate\Support\Str::limit($auth["uri"]??"",40) }}</a></td><td><span class="badge bg-success">Linked</span></td></tr>@empty<tr><td colspan="4" class="text-muted text-center py-3">No linked authorities.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
