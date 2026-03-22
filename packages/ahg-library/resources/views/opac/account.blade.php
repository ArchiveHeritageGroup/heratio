@extends('theme::layouts.1col')
@section('title', 'My Account')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-user me-2"></i>My Library Account</h1><div class="row"><div class="col-md-6"><div class="card mb-4"><div class="card-header"><h5 class="mb-0">Current Loans</h5></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Title</th><th>Due</th><th>Action</th></tr></thead><tbody>@forelse($loans??[] as $l)<tr><td>{{ e($l->title??"") }}</td><td>{{ $l->due_date??"" }}</td><td><form method="post" action="{{ route("library.opac-renew",$l->id??0) }}" class="d-inline">@csrf<button type="submit" class="btn btn-sm atom-btn-white">Renew</button></form></td></tr>@empty<tr><td colspan="3" class="text-muted text-center">No active loans</td></tr>@endforelse</tbody></table></div></div></div><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="mb-0">Holds</h5></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Title</th><th>Status</th></tr></thead><tbody>@forelse($holds??[] as $h)<tr><td>{{ e($h->title??"") }}</td><td><span class="badge bg-secondary">{{ ucfirst($h->status??"") }}</span></td></tr>@empty<tr><td colspan="2" class="text-muted text-center">No holds</td></tr>@endforelse</tbody></table></div></div></div></div>
</div>
@endsection
