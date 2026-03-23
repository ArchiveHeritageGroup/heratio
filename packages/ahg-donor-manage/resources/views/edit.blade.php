@extends('theme::layouts.1col')

@section('title', ($donor ? 'Edit' : 'Add new') . ' donor')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $donor ? 'Edit donor' : 'Add new donor' }}
    </h1>
    @if($donor)
      <span class="small" id="heading-label">{{ $donor->authorized_form_of_name ?: 'Untitled' }}</span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ $donor ? route('donor.update', $donor->slug) : route('donor.store') }}" id="editForm">
    @csrf

    <div class="accordion mb-3">
      {{-- ===== Identity area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse show" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name
                <span class="form-required" title="This is a mandatory field.">*</span>
                <span class="badge bg-danger ms-1">Required</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $donor->authorized_form_of_name ?? '') }}">
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Contact area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="contact-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact-collapse" aria-expanded="false" aria-controls="contact-collapse">
            Contact area
          </button>
        </h2>
        <div id="contact-collapse" class="accordion-collapse collapse" aria-labelledby="contact-heading">
          <div class="accordion-body">
            @include('ahg-actor-manage::partials._contact-area', ['contacts' => $contacts])
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($donor)
        <li><a href="{{ route('donor.show', $donor->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('donor.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>

@push('css')
<style>
.accordion-button {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button:not(.collapsed) {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
  box-shadow: none;
}
.accordion-button.collapsed {
  background-color: var(--ahg-primary) !important;
  color: var(--ahg-card-header-text, #fff) !important;
}
.accordion-button::after {
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'//%3e%3c/svg%3e");
}
.accordion-button:focus {
  box-shadow: 0 0 0 0.25rem var(--ahg-input-focus, rgba(0,88,55,0.25));
}
.form-required {
  color: var(--bs-danger, #dc3545);
  font-weight: bold;
}
</style>
@endpush
@endsection
