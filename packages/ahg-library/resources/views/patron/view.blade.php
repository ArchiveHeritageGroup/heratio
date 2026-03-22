@extends('theme::layouts.1col')
@section('title', 'Patron Details')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-user me-2"></i>{{ e($patron->name??"") }}</h1><div class="row"><div class="col-md-6"><div class="card mb-4"><div class="card-header"><h5 class="mb-0">Details</h5></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ e($patron->type??"") }}</dd><dt class="col-sm-4">Card #</dt><dd class="col-sm-8"><code>{{ e($patron->card_number??"") }}</code></dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ e($patron->email??"") }}</dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8">@if($patron->active??true)<span class="badge bg-success">Active</span>@else<span class="badge bg-danger">Suspended</span>@endif</dd></dl></div></div></div><div class="col-md-6"><div class="card"><div class="card-header"><h5 class="mb-0">Current Loans</h5></div><div class="card-body p-0"><table class="table table-sm mb-0"><thead><tr><th>Item</th><th>Due</th></tr></thead><tbody>@forelse($loans??[] as $l)<tr><td>{{ e($l->title??"") }}</td><td>{{ $l->due_date??"" }}</td></tr>@empty<tr><td colspan="2" class="text-muted text-center">No active loans</td></tr>@endforelse</tbody></table></div></div></div></div>
</div>
@endsection
