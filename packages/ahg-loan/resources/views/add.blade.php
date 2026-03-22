@extends('theme::layouts.1col')
@section('title', 'New Loan')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-plus-circle me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">New Loan</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>New Loan</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Borrower <span class="badge bg-secondary ms-1">Required</span></label><input type="text" class="form-control" name="borrower"></div><div class="mb-3"><label class="form-label">Purpose <span class="badge bg-secondary ms-1">Recommended</span></label><textarea class="form-control" name="purpose" rows="2"></textarea></div><div class="row"><div class="col-md-6 mb-3"><label class="form-label">Start Date <span class="badge bg-secondary ms-1">Recommended</span></label><input type="date" class="form-control" name="start_date"></div><div class="col-md-6 mb-3"><label class="form-label">End Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="end_date"></div></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
