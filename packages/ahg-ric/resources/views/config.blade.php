@extends('theme::layouts.1col')
@section('title', 'RiC Configuration')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-cog me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">RiC Configuration</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>RiC Configuration</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">RiC Version <span class="badge bg-secondary">field</span></label><input type="text" class="form-control" name="ric_version"></div><div class="mb-3"><label class="form-label">Endpoint URL <span class="badge bg-secondary">field</span></label><input type="url" class="form-control" name="endpoint_url"></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
