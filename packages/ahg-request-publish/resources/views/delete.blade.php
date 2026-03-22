@extends('theme::layouts.1col')
@section('title', 'Confirm Delete')
@section('body-class', 'delete')
@section('content')
  <div class="card border-danger"><div class="card-header bg-danger text-white"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</div><div class="card-body">
    <p>Are you sure you want to delete <strong>{{ $record->name ?? $record->title ?? 'this record' }}</strong>?</p>
    <p class="text-danger">This action cannot be undone.</p>
    <form method="POST" action="{{ $deleteUrl ?? '#' }}">@csrf @method('DELETE')
      <div class="d-flex gap-2"><button type="submit" class="btn atom-btn-outline-danger"><i class="fas fa-trash me-1"></i> Delete</button><a href="{{ url()->previous() }}" class="btn atom-btn-outline-light"><i class="fas fa-times me-1"></i> Cancel</a></div>
    </form>
  </div></div>
@endsection
