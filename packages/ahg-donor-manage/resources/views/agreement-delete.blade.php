@extends('theme::layouts.1col')
@section('title', 'Delete Agreement')
@section('body-class', 'delete')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-file-signature me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Delete Agreement') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold bg-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion</div>
  <div class="card-body">
    <p>Are you sure you want to delete this agreement?</p>
    <dl class="row mb-3">
      <dt class="col-sm-3">Title</dt><dd class="col-sm-9">{{ $record->title ?? $record->agreement_number ?? 'Untitled' }}</dd>
      <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ ucfirst($record->status ?? 'draft') }}</dd>
    </dl>
    <p class="text-danger"><strong>{{ __('This action cannot be undone.') }}</strong> All related documents, linked records, and reminders will also be deleted.</p>
    <form method="POST" action="{{ route('donor.agreement.delete', $record->id) }}">@csrf @method('DELETE')
      <div class="d-flex gap-2"><button type="submit" class="btn btn-danger"><i class="fas fa-trash me-1"></i> {{ __('Delete') }}</button><a href="{{ route('donor.agreement.view', $record->id) }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> {{ __('Cancel') }}</a></div>
    </form>
  </div></div>
@endsection
