@extends('theme::layouts.1col')
@section('title', 'Edit IPTC Metadata')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-tags me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Edit IPTC Metadata</h1></div>
  </div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Edit IPTC Metadata</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">Required</span></label><input type="text" class="form-control" name="title"></div><div class="mb-3"><label class="form-label">Creator <span class="badge bg-secondary ms-1">Recommended</span></label><input type="text" class="form-control" name="creator"></div><div class="mb-3"><label class="form-label">Keywords <span class="badge bg-secondary ms-1">Recommended</span></label><input type="text" class="form-control" name="keywords"></div><div class="mb-3"><label class="form-label">Copyright <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="copyright"></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
