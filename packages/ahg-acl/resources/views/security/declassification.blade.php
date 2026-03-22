@extends('theme::layouts.1col')
@section('title', 'Declassification')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-unlock-alt me-2"></i>Declassification</h1><div class="card"><div class="card-header"><h5 class="mb-0">{{ e($object->title??"Untitled") }}</h5></div><div class="card-body">@if($currentClassification??null)<div class="alert alert-info">Current: <strong>{{ e($currentClassification->name??"") }}</strong></div>@endif<form method="post" action="{{ route("acl.declassify-store") }}">@csrf<input type="hidden" name="object_id" value="{{ $object->id??"" }}"><div class="mb-3"><label class="form-label">New Classification</label><select name="new_classification_id" class="form-select"><option value="">Unclassified</option>@foreach($classifications??[] as $cls)<option value="{{ $cls->id }}">{{ e($cls->name) }}</option>@endforeach</select></div><div class="mb-3"><label class="form-label">Reason</label><textarea name="reason" class="form-control" rows="3" required></textarea></div><button type="submit" class="btn atom-btn-white"><i class="fas fa-check me-1"></i>Declassify</button></form></div></div>
</div>
@endsection
