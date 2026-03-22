@extends('theme::layouts.1col')
@section('title', 'Patrons')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-users me-2"></i>Patrons</h1><div class="card"><div class="card-body p-0"><table class="table table-striped mb-0"><thead><tr><th>Name</th><th>Type</th><th>Card #</th><th>Email</th><th>Status</th><th class="text-end">Loans</th></tr></thead><tbody>@forelse($patrons??[] as $p)<tr><td><a href="{{ route("library.patron-view",$p->id??0) }}"><strong>{{ e($p->name??"") }}</strong></a></td><td>{{ e($p->type??"") }}</td><td><code>{{ e($p->card_number??"") }}</code></td><td>{{ e($p->email??"") }}</td><td>@if($p->active??true)<span class="badge bg-success">Active</span>@else<span class="badge bg-danger">Suspended</span>@endif</td><td class="text-end">{{ $p->active_loans??0 }}</td></tr>@empty<tr><td colspan="6" class="text-muted text-center py-3">No patrons.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
