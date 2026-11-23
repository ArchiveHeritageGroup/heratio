@extends('theme::layouts.1col')
@section('title', $template ? __('Edit Template') : __('Add Template'))
@section('content')
@php
  $fields = [
    'page_width_mm' => [__('Page width (mm)'), 210], 'page_height_mm' => [__('Page height (mm)'), 297],
    'margin_top_mm' => [__('Top margin to first label (mm)'), null], 'margin_left_mm' => [__('Left margin to first label (mm)'), null],
    'label_width_mm' => [__('Label width (mm)'), null], 'label_height_mm' => [__('Label height (mm)'), null],
    'pitch_x_mm' => [__('Horizontal pitch, label edge to next label edge (mm)'), null], 'pitch_y_mm' => [__('Vertical pitch, label edge to next label edge (mm)'), null],
  ];
@endphp
<div class="container py-4">
  <h1 class="mb-3">{{ $template ? __('Edit Template') : __('Add Template') }}</h1>
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  <form method="post" action="{{ $template ? route('library.labels.templates.update', $template->id) : route('library.labels.templates.store') }}" class="card card-body">
    @csrf
    <div class="row g-3">
      <div class="col-12">
        <label for="name" class="form-label">{{ __('Name') }}</label>
        <input type="text" id="name" name="name" maxlength="150" required class="form-control" value="{{ old('name', $template->name ?? '') }}">
      </div>
      @foreach($fields as $field => [$label, $default])
        <div class="col-md-6">
          <label for="{{ $field }}" class="form-label">{{ $label }}</label>
          <input type="number" step="0.01" min="0" required id="{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field, isset($values) ? (float) $values->$field : $default) }}">
        </div>
      @endforeach
      <div class="col-md-6">
        <label for="columns_across" class="form-label">{{ __('Labels across') }}</label>
        <input type="number" min="1" max="30" required id="columns_across" name="columns_across" class="form-control" value="{{ old('columns_across', $values->columns_across ?? '') }}">
      </div>
      <div class="col-md-6">
        <label for="rows_down" class="form-label">{{ __('Labels down') }}</label>
        <input type="number" min="1" max="60" required id="rows_down" name="rows_down" class="form-control" value="{{ old('rows_down', $values->rows_down ?? '') }}">
      </div>
    </div>
    <div class="mt-3 d-flex gap-2">
      <button type="submit" class="btn atom-btn-outline-success">{{ __('Save') }}</button>
      <a href="{{ route('library.labels.templates') }}" class="btn atom-btn-white">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
