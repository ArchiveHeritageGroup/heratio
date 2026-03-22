@extends('theme::layouts.1col')
@section('title', 'Review Request')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-clipboard-check me-2"></i>Review Access Request</h1><div class="card"><div class="card-header"><h5 class="mb-0">Request #{{ $request->id??"" }}</h5></div><div class="card-body"><div class="row"><div class="col-md-6"><p><strong>Requester:</strong> {{ e($request->requester_name??"") }}</p><p><strong>Object:</strong> {{ e($request->object_title??"") }}</p><p><strong>Submitted:</strong> {{ $request->created_at??"" }}</p></div><div class="col-md-6"><p><strong>Reason:</strong></p><p class="border p-2 bg-light">{{ e($request->reason??"No reason") }}</p></div></div><hr><form method="post" action="{{ route("acl.review-request",$request->id??0) }}">@csrf<div class="mb-3"><label class="form-label">Review Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="notes" class="form-control" rows="3"></textarea></div><div class="d-flex gap-2"><button type="submit" name="decision" value="approved" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Approve</button><button type="submit" name="decision" value="denied" class="btn atom-btn-outline-danger"><i class="fas fa-times me-1"></i>Deny</button></div></form></div></div>
</div>
@endsection
