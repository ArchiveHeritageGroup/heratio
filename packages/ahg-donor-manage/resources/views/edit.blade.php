@extends('theme::layouts.1col')

@section('title', ($donor ? 'Edit' : 'Create') . ' donor')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $donor ? 'Edit' : 'Create' }} donor</h1>
    @if($donor)
      <span class="small">{{ $donor->authorized_form_of_name }}</span>
    @endif
  </div>
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ $donor ? route('donor.update', $donor->slug) : route('donor.store') }}">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="true">Identity area</button></h2>
        <div id="identity-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">Authorized form of name <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $donor->authorized_form_of_name ?? '') }}">
            </div>
          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h2 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact-collapse">Contact area</button></h2>
        <div id="contact-collapse" class="accordion-collapse collapse">
          <div class="accordion-body">
            @include('ahg-actor-manage::partials._contact-area', ['contacts' => $contacts])
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($donor)
          <li><a href="{{ route('donor.show', $donor->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
        @else
          <li><a href="{{ route('donor.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>
  </form>
@endsection
