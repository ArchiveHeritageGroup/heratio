@extends('theme::layouts.1col')
@section('title', 'Sector Export')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-file-export me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Sector Export</h1></div>
  </div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Sector Export</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Sector <span class="badge bg-secondary">field</span></label><input type="text" class="form-control" name="sector"></div><div class="mb-3"><label class="form-label">Format <span class="badge bg-secondary">field</span></label><select class="form-select" name="format"><option value="csv">CSV</option><option value="xml">XML</option></select></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
