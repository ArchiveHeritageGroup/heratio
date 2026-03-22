@extends('theme::layouts.1col')
@section('title', 'Object Security')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-lock me-2"></i>Object Security Details</h1><div class="card"><div class="card-header"><h5 class="mb-0">{{ e($object->title??"Untitled") }}</h5></div><div class="card-body">@if($objectClassification??null)<p><strong>Classification:</strong> <span class="badge" style="background-color:{{ $objectClassification->color??"#999" }}">{{ e($objectClassification->name??"") }}</span></p><p><strong>Classified By:</strong> {{ e($objectClassification->classified_by_name??"System") }}</p><p><strong>Date:</strong> {{ $objectClassification->classified_at??"" }}</p>@else<div class="alert alert-info">Not classified.</div>@endif</div></div>
</div>
@endsection
