@extends('theme::layouts.1col')
@section('title', 'Add Workflow Step')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-plus-circle me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Add Workflow Step') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold"><i class="fas fa-edit me-2"></i>Add Workflow Step</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">Step Name <span class="badge bg-danger ms-1">{{ __('Required') }}</span></label><input type="text" class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Description <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea class="form-control" name="description" rows="2"></textarea></div><div class="mb-3"><label class="form-label">Order <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="number" class="form-control" name="order"></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> {{ __('Cancel') }}</a></div>
  </form></div></div>
@endsection
