@extends('theme::layouts.1col')
@section('title', 'Intake Timeline')
@section('content')
<div class="container py-4">
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route("accession.intake-queue") }}">Intake queue</a></li><li class="breadcrumb-item"><a href="{{ route("accession.show",$accession->slug??"") }}">{{ e($accession->identifier??"") }}</a></li><li class="breadcrumb-item active">Timeline</li></ol></nav><h1><i class="fas fa-clock me-2"></i>Timeline <small class="text-muted fs-5">{{ e($accession->identifier??"") }}</small></h1><div class="card"><div class="card-body">@forelse($events??[] as $event)<div class="d-flex mb-3 pb-3 border-bottom"><div class="me-3 text-center" style="min-width:80px;"><small class="text-muted">{{ $event->created_at??"" }}</small></div><div><strong>{{ e($event->action??"") }}</strong>@if($event->user_name??null) <small class="text-muted">by {{ e($event->user_name) }}</small>@endif @if($event->notes??null)<p class="mb-0 mt-1 text-muted">{{ e($event->notes) }}</p>@endif</div></div>@empty<p class="text-muted">No timeline events.</p>@endforelse</div></div>
</div>
@endsection
