@extends('theme::layouts.1col')
@section('title', 'Edit Standard')
@section('body-class', 'admin heritage')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-heritage-manage::partials._heritage-accounting-menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-pencil-alt me-2"></i>Edit Standard</h1>
    <p class="text-muted">Edit an existing accounting standard.</p>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="post" action="{{ $formAction ?? '#' }}">
      @csrf
      @if(isset($item)) @method('PUT') @endif

      <div class="card mb-3">
        <div class="card-header" style="background:var(--ahg-primary);color:#fff"><i class="fas fa-pencil-alt me-2"></i>Edit Standard</div>
        <div class="card-body">
          <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required value="{{ old('name', $item->name ?? '') }}"></div>
          <div class="mb-3"><label class="form-label">Code</label><input type="text" name="code" class="form-control" value="{{ old('code', $item->code ?? '') }}"></div>
          <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3">{{ old('description', $item->description ?? '') }}</textarea></div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="is_active" class="form-select">
              <option value="1" {{ old('is_active', $item->is_active ?? 1) == 1 ? 'selected' : '' }}>Active</option>
              <option value="0" {{ old('is_active', $item->is_active ?? 1) == 0 ? 'selected' : '' }}>Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
        <a href="javascript:history.back()" class="btn atom-btn-white">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection