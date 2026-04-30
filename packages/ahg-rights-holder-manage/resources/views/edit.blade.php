@extends('theme::layouts.1col')

@section('title', ($rightsHolder ? 'Edit rights holder' : 'Add new rights holder'))

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $rightsHolder ? 'Edit rights holder' : 'Add new rights holder' }}
    </h1>
    @if($rightsHolder)
      <span class="small" id="heading-label">{{ $rightsHolder->authorized_form_of_name }}</span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" id="editForm"
        action="{{ $rightsHolder ? route('rightsholder.update', $rightsHolder->slug) : route('rightsholder.store') }}">
    @csrf

    <div class="accordion mb-3">
      {{-- ── Identity area ── --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#identity-collapse"
                  aria-expanded="false" aria-controls="identity-collapse">
            {{ __('Identity area') }}
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name
                <span class="form-required text-danger" title="{{ __('This is a mandatory element.') }}">*</span>
                <span class="badge bg-danger ms-1">Required</span>
              </label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name"
                     class="form-control" required
                     value="{{ old('authorized_form_of_name', $rightsHolder->authorized_form_of_name ?? '') }}">
            </div>
          </div>
        </div>
      </div>

      {{-- ── Contact area ── --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="contact-heading">
          <button class="accordion-button collapsed" type="button"
                  data-bs-toggle="collapse" data-bs-target="#contact-collapse"
                  aria-expanded="false" aria-controls="contact-collapse">
            {{ __('Contact area') }}
          </button>
        </h2>
        <div id="contact-collapse" class="accordion-collapse collapse" aria-labelledby="contact-heading">
          <div class="accordion-body">
            @include('ahg-rights-holder-manage::_contact-edit', ['contacts' => $contacts])
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($rightsHolder)
        <li><a href="{{ route('rightsholder.show', $rightsHolder->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('rightsholder.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>
@endsection
