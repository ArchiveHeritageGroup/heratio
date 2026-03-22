@extends('theme::layouts.1col')
@section('title', 'Rename Library Item')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-edit me-2"></i>Rename Library Item</h1><div class="card"><div class="card-body"><form method="post" action="{{ route("library.rename-store",$item->slug??"") }}">@csrf<div class="mb-3"><label class="form-label">Current Title <span class="badge bg-danger ms-1">Required</span></label><p class="form-control-plaintext">{{ e($item->title??"") }}</p></div><div class="mb-3"><label class="form-label">New Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" name="title" class="form-control" value="{{ e($item->title??"") }}" required></div><div class="d-flex gap-2"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i>Rename</button><a href="{{ route("library.show",$item->slug??"") }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div>
</div>
@endsection
