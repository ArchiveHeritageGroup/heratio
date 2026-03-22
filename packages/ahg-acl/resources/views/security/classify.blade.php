@extends('theme::layouts.1col')
@section('title', 'Classify Object')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-stamp me-2"></i>Classify Object</h1><div class="card"><div class="card-header"><h5 class="mb-0">{{ e($object->title??"Untitled") }}</h5></div><div class="card-body"><form method="post" action="{{ route("acl.classify-store") }}">@csrf<input type="hidden" name="object_id" value="{{ $object->id??"" }}"><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Classification Level <span class="text-danger">*</span></label><select name="classification_id" class="form-select" required><option value="">-- Select --</option>@foreach($classifications??[] as $cls)<option value="{{ $cls->id }}">{{ e($cls->name) }} ({{ e($cls->code) }})</option>@endforeach</select></div></div><div class="mb-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="3"></textarea></div><button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Apply</button></form></div></div>
</div>
@endsection
