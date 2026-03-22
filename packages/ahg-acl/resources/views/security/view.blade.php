@extends('theme::layouts.1col')
@section('title', 'Classification Record')
@section('content')
<div class="container py-4">
<h1><i class="fas fa-eye me-2"></i>Classification Record</h1><div class="card"><div class="card-body"><p><strong>Object:</strong> {{ e($record->object_title??"Untitled") }}</p><p><strong>Classification:</strong> <span class="badge" style="background-color:{{ $record->color??"#999" }}">{{ e($record->classification_name??"") }}</span></p><p><strong>Classified By:</strong> {{ e($record->classified_by??"") }}</p><p><strong>Date:</strong> {{ $record->classified_at??"" }}</p>@if($record->reason??null)<p><strong>Reason:</strong> {{ e($record->reason) }}</p>@endif</div></div>
</div>
@endsection
