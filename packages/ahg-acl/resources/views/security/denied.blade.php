@extends('theme::layouts.1col')
@section('title', 'Access Denied')
@section('content')
<div class="container py-4">
<div class="row justify-content-center"><div class="col-md-8"><div class="card border-danger"><div class="card-header bg-danger text-white"><h4 class="mb-0"><i class="fas fa-ban me-2"></i>Access Denied</h4></div><div class="card-body"><p class="lead">Your security clearance does not permit access.</p>@if($requiredLevel??null)<div class="alert alert-warning">Required: <strong>{{ e($requiredLevel) }}</strong></div>@endif<div class="d-flex gap-2"><a href="javascript:history.back()" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Go Back</a><a href="{{ route("security.my-requests") }}" class="btn atom-btn-white"><i class="fas fa-hand-paper me-1"></i>Request Access</a></div></div></div></div></div>
</div>
@endsection
