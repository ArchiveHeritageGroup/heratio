@extends('theme::layouts.1col')
@section('title', 'Batch Mint DOIs')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3">
    <i class="fas fa-3x fa-layer-group me-3" aria-hidden="true"></i>
    <div class="d-flex flex-column"><h1 class="mb-0">Batch Mint DOIs</h1></div>
  </div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>Batch Mint DOIs</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Object IDs <span class="badge bg-secondary ms-1">Required</span></label><textarea class="form-control" name="object_ids" rows="4" placeholder="One ID per line"></textarea></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> Save</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> Cancel</a></div>
  </form></div></div>
@endsection
