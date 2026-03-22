@extends('theme::layouts.1col')
@section('title', 'Intake Checklist')
@section('content')
<div class="container py-4">
<nav aria-label="breadcrumb" class="mb-3"><ol class="breadcrumb"><li class="breadcrumb-item"><a href="{{ route("accession.intake-queue") }}">Intake queue</a></li><li class="breadcrumb-item"><a href="{{ route("accession.show",$accession->slug??"") }}">{{ e($accession->identifier??"") }}</a></li><li class="breadcrumb-item active">Checklist</li></ol></nav><h1><i class="fas fa-tasks me-2"></i>Intake Checklist <small class="text-muted fs-5">{{ e($accession->identifier??"") }}</small></h1><form method="post" action="{{ route("accession.checklist-store",$accession->id??0) }}">@csrf<div class="card"><div class="card-body">@forelse($checklistItems??[] as $item)<div class="form-check mb-2"><input type="checkbox" class="form-check-input" id="check_{{ $item->id }}" name="items[]" value="{{ $item->id }}" {{ ($item->completed??false)?"checked":"" }}><label class="form-check-label" for="check_{{ $item->id }}">{{ e($item->label??"") }}</label></div>@empty<p class="text-muted">No checklist items defined.</p>@endforelse<button type="submit" class="btn atom-btn-white mt-3"><i class="fas fa-save me-1"></i>Save</button></div></div></form>
</div>
@endsection
