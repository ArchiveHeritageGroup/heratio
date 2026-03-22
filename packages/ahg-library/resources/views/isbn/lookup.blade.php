@extends('theme::layouts.1col')
@section('title', 'ISBN Lookup')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-search me-2"></i>ISBN Lookup</h1><div class="card"><div class="card-body"><form method="post" action="{{ route("library.isbn-lookup-search") }}">@csrf<div class="row g-3"><div class="col-md-8"><label class="form-label">ISBN <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="isbn" class="form-control" value="{{ $isbn??"" }}" required></div><div class="col-md-4 d-flex align-items-end"><button type="submit" class="btn atom-btn-white w-100"><i class="fas fa-search me-1"></i>Lookup</button></div></div></form></div></div>@if($result??null)<div class="card mt-4"><div class="card-header" style="background:var(--ahg-primary);color:#fff"><h5 class="mb-0">Result</h5></div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ e($result->title??"") }}</dd><dt class="col-sm-3">Author</dt><dd class="col-sm-9">{{ e($result->author??"") }}</dd><dt class="col-sm-3">Publisher</dt><dd class="col-sm-9">{{ e($result->publisher??"") }}</dd></dl></div></div>@endif
</div>
@endsection
