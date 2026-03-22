@extends('theme::layouts.1col')
@section('title', 'Feedback Details')
@section('body-class', 'show')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-eye me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Feedback Details</h1></div></div>
  <div class="row"><div class="col-lg-8"><div class="card mb-4"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff">Details</div><div class="card-body"><dl class="row mb-0"><dt class="col-sm-4">Subject</dt><dd class="col-sm-8">{{ $record->name ?? "-" }}</dd><dt class="col-sm-4">Name</dt><dd class="col-sm-8">{{ $record->feed_name ?? "" }} {{ $record->feed_surname ?? "" }}</dd><dt class="col-sm-4">Email</dt><dd class="col-sm-8">{{ $record->feed_email ?? "-" }}</dd><dt class="col-sm-4">Remarks</dt><dd class="col-sm-8">{{ $record->remarks ?? "-" }}</dd><dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ $record->status ?? "-" }}</dd></dl></div></div></div>
  <div class="col-lg-4"><a href="{{ url()->previous() }}" class="btn atom-btn-white w-100"><i class="fas fa-arrow-left me-1"></i>Back</a></div></div>
@endsection
