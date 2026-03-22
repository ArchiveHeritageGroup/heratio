@extends('theme::layouts.1col')
@section('title', 'ILL Request')
@section('content')
<div class="container py-4">
<h1>ILL Request: {{ e($request->ill_number??"") }}</h1><div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Type</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst($request->type??"") }}</span></dd><dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ e($request->title??"") }}</dd><dt class="col-sm-3">Author</dt><dd class="col-sm-9">{{ e($request->author??"") }}</dd><dt class="col-sm-3">ISBN</dt><dd class="col-sm-9">{{ e($request->isbn??"") }}</dd><dt class="col-sm-3">Library</dt><dd class="col-sm-9">{{ e($request->library_name??"") }}</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst($request->status??"") }}</span></dd></dl></div></div>
</div>
@endsection
