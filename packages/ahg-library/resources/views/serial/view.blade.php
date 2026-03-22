@extends('theme::layouts.1col')
@section('title', 'Serial Details')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-newspaper me-2"></i>{{ e($serial->title??"") }}</h1><div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">ISSN</dt><dd class="col-sm-9">{{ e($serial->issn??"") }}</dd><dt class="col-sm-3">Frequency</dt><dd class="col-sm-9">{{ e($serial->frequency??"") }}</dd><dt class="col-sm-3">Publisher</dt><dd class="col-sm-9">{{ e($serial->publisher??"") }}</dd><dt class="col-sm-3">Status</dt><dd class="col-sm-9"><span class="badge bg-secondary">{{ ucfirst($serial->status??"") }}</span></dd></dl></div></div>
</div>
@endsection
