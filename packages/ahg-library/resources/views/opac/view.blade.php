@extends('theme::layouts.1col')
@section('title', 'Catalogue Record')
@section('content')
<div class="container py-4">
<h1>{{ e($item->title??"") }}</h1><div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Author</dt><dd class="col-sm-9">{{ e($item->author??$item->responsibility_statement??"") }}</dd><dt class="col-sm-3">Type</dt><dd class="col-sm-9">{{ ucfirst($item->material_type??"") }}</dd><dt class="col-sm-3">ISBN</dt><dd class="col-sm-9">{{ e($item->isbn??"") }}</dd><dt class="col-sm-3">Publisher</dt><dd class="col-sm-9">{{ e($item->publisher??"") }}</dd><dt class="col-sm-3">Call Number</dt><dd class="col-sm-9">{{ e($item->call_number??"") }}</dd><dt class="col-sm-3">Available</dt><dd class="col-sm-9">@if($item->available??true)<span class="badge bg-success">Available</span>@else<span class="badge bg-danger">Checked Out</span>@endif</dd></dl></div></div>@auth<div class="mt-3"><a href="{{ route("library.opac-hold",$item->slug??"") }}" class="btn atom-btn-white"><i class="fas fa-hand-paper me-1"></i>Place Hold</a></div>@endauth
</div>
@endsection
