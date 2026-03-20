@extends('theme::layouts.1col')
@section('title', 'Digital Object Settings')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-photo-video me-2"></i>Digital Object Derivatives</h1>

    <form method="post" action="{{ route('settings.digital-objects') }}">
      @csrf
      <div class="card mb-4">
        <div class="card-header">Derivative settings</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">PDF page number for reference image</label>
            <input type="number" name="settings[digital_object_derivatives_pdf_page_number]" class="form-control" value="{{ e($settings['digital_object_derivatives_pdf_page_number'] ?? '1') }}" min="1">
            <small class="text-muted">Which page of a PDF to use when generating a reference image derivative</small>
          </div>
          <div class="mb-3">
            <label class="form-label">Reference image maximum width (pixels)</label>
            <input type="number" name="settings[reference_image_maxwidth]" class="form-control" value="{{ e($settings['reference_image_maxwidth'] ?? '480') }}" min="100" max="2000">
            <small class="text-muted">Maximum width in pixels for reference image derivatives</small>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
      <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
  </div>
</div>
@endsection
