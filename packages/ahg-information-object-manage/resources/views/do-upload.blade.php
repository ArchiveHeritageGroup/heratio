@extends('theme::layouts.1col')
@section('title', 'Upload Digital Object')
@section('body-class', 'edit')
@section('content')
  <div class="multiline-header d-flex align-items-center mb-3"><i class="fas fa-3x fa-upload me-3" aria-hidden="true"></i><div class="d-flex flex-column"><h1 class="mb-0">{{ __('Upload Digital Object') }}</h1></div></div>
  <div class="card"><div class="card-header fw-semibold" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-edit me-2"></i>{{ __('Upload Digital Object') }}</div>
  <div class="card-body"><form method="POST" action="{{ $formAction ?? '#' }}">@csrf
    <div class="mb-3"><label class="form-label">File <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="file" class="form-control" name="file" required></div><div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">{{ __('Required') }}</span></label><input type="text" class="form-control" name="title"></div>
    <div class="d-flex gap-2 mt-3"><button type="submit" class="btn atom-btn-white"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button><a href="{{ url()->previous() }}" class="btn atom-btn-white"><i class="fas fa-times me-1"></i> {{ __('Cancel') }}</a></div>
  </form></div></div>
@endsection
