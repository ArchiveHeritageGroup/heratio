@extends('theme::layouts.1col')
@section('title', 'Place Hold')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-hand-paper me-2"></i>Place Hold</h1><div class="card"><div class="card-body"><p><strong>Item:</strong> {{ e($item->title??"") }}</p><form method="post" action="{{ route("library.opac-hold-store",$item->slug??"") }}">@csrf<div class="mb-3"><label class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea name="notes" class="form-control" rows="3"></textarea></div><div class="d-flex gap-2"><button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Place Hold</button><a href="{{ route("library.opac-view",$item->slug??"") }}" class="btn btn-outline-secondary">Cancel</a></div></form></div></div>
</div>
@endsection
