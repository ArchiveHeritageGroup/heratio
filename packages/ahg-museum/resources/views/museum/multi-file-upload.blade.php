@extends('theme::layouts.1col')
@section('title', 'Multi-file Upload')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-upload me-2"></i>Import multiple digital objects</h1>@if($resource??null)<p class="text-muted">{{ e($resource->title??"") }}</p>@endif<div class="card"><div class="card-body"><form method="post" action="{{ route("museum.multi-upload-store",$resource->slug??"") }}" enctype="multipart/form-data">@csrf<div class="mb-3"><label class="form-label">Select files <span class="badge bg-danger ms-1">Required</span></label><input type="file" name="files[]" class="form-control" multiple required></div><button type="submit" class="btn atom-btn-white"><i class="fas fa-upload me-1"></i>Upload</button></form></div></div>
</div>
@endsection
