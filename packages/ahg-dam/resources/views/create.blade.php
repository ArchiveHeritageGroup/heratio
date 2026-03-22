@extends('theme::layouts.1col')
@section('title', 'Create Digital Asset')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-plus-circle me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Create Digital Asset</h1></div>
  </div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Create Digital Asset</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">Required</span></label><input type="text" class="form-control" name="title"></div><div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">Recommended</span></label><textarea class="form-control" name="description" rows="3"></textarea></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
