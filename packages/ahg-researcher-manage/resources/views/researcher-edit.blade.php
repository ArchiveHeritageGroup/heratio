@extends('theme::layouts.1col')
@section('title', 'Edit Researcher')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-edit me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">Edit Researcher</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Edit Researcher</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Name <span class="badge bg-secondary ms-1">Required</span></label><input type="text" class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Email <span class="badge bg-secondary ms-1">Required</span></label><input type="email" class="form-control" name="email" required></div><div class="mb-3"><label class="form-label">Institution <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="institution"></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
