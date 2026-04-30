@extends('theme::layouts.2col')
@section('title', 'Digital object derivatives')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
  <h1>{{ __('Digital object derivatives') }}</h1>
@endsection

@section('content')
    <form method="post" action="{{ route('settings.digital-objects') }}">
      @csrf
      <div class="accordion mb-3">
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#derivatives-collapse">{{ __('Digital object derivatives settings') }}</button></h2>
          <div id="derivatives-collapse" class="accordion-collapse collapse show">
            <div class="accordion-body">
              <div class="mb-3">
                <label class="form-label">PDF page number for image derivative <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" name="settings[digital_object_derivatives_pdf_page_number]" class="form-control" value="{{ $settings['digital_object_derivatives_pdf_page_number'] ?? '1' }}" min="1">
                <small class="text-muted">{{ __('If the page number does not exist, the derivative will be generated from the previous closest one.') }}</small>
              </div>
              <div class="mb-3">
                <label class="form-label">Maximum length on longest edge (pixels) <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="number" name="settings[reference_image_maxwidth]" class="form-control" value="{{ $settings['reference_image_maxwidth'] ?? '480' }}" min="100">
                <small class="text-muted">{{ __('The maximum number of pixels on the longest edge for derived reference images.') }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
@endsection
