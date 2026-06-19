@extends('ahg-theme-b5::layout')

@section('title', $template ? 'Edit Label Template' : 'New Label Template')

@section('content')
<div class="container py-4">

  <nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
      <li class="breadcrumb-item"><a href="{{ route('ahglabel.templates.index') }}">{{ __('Label Templates') }}</a></li>
      <li class="breadcrumb-item active">{{ $template ? __('Edit') : __('New') }}</li>
    </ol>
  </nav>

  <h2 class="mb-4"><i class="fas fa-tags me-2"></i> {{ $template ? __('Edit label template') : __('New label template') }}</h2>

  @if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="post" action="{{ $template ? route('ahglabel.templates.update', $template->id) : route('ahglabel.templates.store') }}">
    @csrf
    @if ($template) @method('PUT') @endif

    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required value="{{ old('name', $template->name ?? '') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">{{ __('Page size') }}</label>
        <select name="page_size" class="form-select">
          @foreach ($pageSizes as $p)
            <option value="{{ $p }}" @selected(old('page_size', $template->page_size ?? 'A4') === $p)>{{ $p }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-3"><label class="form-label">{{ __('Columns') }}</label><input type="number" name="columns" class="form-control" min="1" max="20" value="{{ old('columns', $template->columns ?? 3) }}"></div>
      <div class="col-md-3"><label class="form-label">{{ __('Rows') }}</label><input type="number" name="rows" class="form-control" min="1" max="30" value="{{ old('rows', $template->rows ?? 8) }}"></div>
      <div class="col-md-3"><label class="form-label">{{ __('Label width (mm)') }}</label><input type="number" step="0.01" name="label_width_mm" class="form-control" value="{{ old('label_width_mm', $template->label_width_mm ?? 63.50) }}"></div>
      <div class="col-md-3"><label class="form-label">{{ __('Label height (mm)') }}</label><input type="number" step="0.01" name="label_height_mm" class="form-control" value="{{ old('label_height_mm', $template->label_height_mm ?? 33.90) }}"></div>

      <div class="col-md-3"><label class="form-label">{{ __('Page margin (mm)') }}</label><input type="number" step="0.01" name="margin_mm" class="form-control" value="{{ old('margin_mm', $template->margin_mm ?? 10.00) }}"></div>
      <div class="col-md-3"><label class="form-label">{{ __('Gutter (mm)') }}</label><input type="number" step="0.01" name="gutter_mm" class="form-control" value="{{ old('gutter_mm', $template->gutter_mm ?? 2.50) }}"></div>
      <div class="col-md-3"><label class="form-label">{{ __('Font size (pt)') }}</label><input type="number" name="font_size_pt" class="form-control" min="4" max="48" value="{{ old('font_size_pt', $template->font_size_pt ?? 9) }}"></div>

      <div class="col-12"><hr><h6 class="text-muted">{{ __('Content') }}</h6></div>

      @php $sf = fn($k, $d) => old($k, isset($template) ? $template->$k : $d); @endphp
      <div class="col-md-3 form-check ms-3"><input type="hidden" name="show_title" value="0"><input type="checkbox" name="show_title" value="1" class="form-check-input" id="show_title" @checked($sf('show_title', true))><label class="form-check-label" for="show_title">{{ __('Title') }}</label></div>
      <div class="col-md-3 form-check"><input type="hidden" name="show_identifier" value="0"><input type="checkbox" name="show_identifier" value="1" class="form-check-input" id="show_identifier" @checked($sf('show_identifier', true))><label class="form-check-label" for="show_identifier">{{ __('Identifier') }}</label></div>
      <div class="col-md-3 form-check"><input type="hidden" name="show_repository" value="0"><input type="checkbox" name="show_repository" value="1" class="form-check-input" id="show_repository" @checked($sf('show_repository', false))><label class="form-check-label" for="show_repository">{{ __('Repository') }}</label></div>

      <div class="col-md-4">
        <label class="form-label">{{ __('Barcode') }}</label>
        <div class="form-check"><input type="hidden" name="show_barcode" value="0"><input type="checkbox" name="show_barcode" value="1" class="form-check-input" id="show_barcode" @checked($sf('show_barcode', true))><label class="form-check-label" for="show_barcode">{{ __('Show barcode') }}</label></div>
      </div>
      <div class="col-md-4">
        <label class="form-label">{{ __('Barcode source') }}</label>
        <select name="barcode_source" class="form-select">
          @foreach ($barcodeSources as $b)<option value="{{ $b }}" @selected(old('barcode_source', $template->barcode_source ?? 'identifier') === $b)>{{ $b }}</option>@endforeach
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">{{ __('QR') }}</label>
        <div class="form-check"><input type="hidden" name="show_qr" value="0"><input type="checkbox" name="show_qr" value="1" class="form-check-input" id="show_qr" @checked($sf('show_qr', false))><label class="form-check-label" for="show_qr">{{ __('Show QR') }}</label></div>
        <select name="qr_target" class="form-select form-select-sm mt-1">
          @foreach ($qrTargets as $q)<option value="{{ $q }}" @selected(old('qr_target', $template->qr_target ?? 'url') === $q)>{{ $q }}</option>@endforeach
        </select>
      </div>

      <div class="col-12 form-check ms-3 mt-2">
        <input type="hidden" name="is_default" value="0">
        <input type="checkbox" name="is_default" value="1" class="form-check-input" id="is_default" @checked($sf('is_default', false))>
        <label class="form-check-label" for="is_default">{{ __('Default template (used by batch printing when none is chosen)') }}</label>
      </div>
    </div>

    <div class="mt-4">
      <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> {{ __('Save') }}</button>
      <a href="{{ route('ahglabel.templates.index') }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
  </form>
</div>
@endsection
