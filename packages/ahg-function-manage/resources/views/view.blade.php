@extends('theme::layouts.1col')
@section('title', 'Function Details')
@section('body-class', 'show')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-eye me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Function Details</h1></div></div>
  <div class="row"><div class="col-lg-8"><div class="card mb-4"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">Details</div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $record->authorized_form_of_name ?? "-" }}</dd><dt class="col-sm-4">Type</dt><dd class="col-sm-8">{{ $record->type ?? "-" }}</dd><dt class="col-sm-4">Description</dt><dd class="col-sm-8">{!! $record->description ?? "-" !!}</dd></dl></div></div></div>
  <div class="col-lg-4"><a href="{{ url()->previous() }}" class="btn atom-btn-white w-100"><i class="fas fa-arrow-left me-1"></i>Back</a></div></div>
@endsection
