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

      {{-- ===== Description area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="dates_of_existence" class="form-label">Dates of existence <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="dates_of_existence" id="dates_of_existence" class="form-control"
                     value="{{ old('dates_of_existence', $donor->dates_of_existence ?? '') }}">
              <div class="form-text text-muted small">Record the dates of existence of the entity being described.</div>
            </div>

            <div class="mb-3">
              <label for="history" class="form-label">History <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $donor->history ?? '') }}</textarea>
              <div class="form-text text-muted small">Record in narrative form or as a chronology the main life events, activities, achievements and/or roles of the entity being described.</div>
            </div>

            <div class="mb-3">
              <label for="places" class="form-label">Places <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="places" id="places" class="form-control" rows="4">{{ old('places', $donor->places ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the predominant places and/or jurisdictions where the entity was based, lived or resided.</div>
            </div>

            <div class="mb-3">
              <label for="legal_status" class="form-label">Legal status <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="legal_status" id="legal_status" class="form-control" rows="4">{{ old('legal_status', $donor->legal_status ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the legal status and where appropriate the type of corporate body.</div>
            </div>

            <div class="mb-3">
              <label for="functions" class="form-label">Functions, occupations and activities <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="functions" id="functions" class="form-control" rows="4">{{ old('functions', $donor->functions ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the functions, occupations and activities performed by the entity being described.</div>
            </div>

            <div class="mb-3">
              <label for="mandates" class="form-label">Mandates/sources of authority <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="mandates" id="mandates" class="form-control" rows="4">{{ old('mandates', $donor->mandates ?? '') }}</textarea>
              <div class="form-text text-muted small">Record any document, law, directive or charter which acts as a source of authority.</div>
            </div>

            <div class="mb-3">
              <label for="internal_structures" class="form-label">Internal structures/genealogy <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="internal_structures" id="internal_structures" class="form-control" rows="4">{{ old('internal_structures', $donor->internal_structures ?? '') }}</textarea>
              <div class="form-text text-muted small">Describe the internal structure of a corporate body or the genealogy of a family.</div>
            </div>

            <div class="mb-3">
              <label for="general_context" class="form-label">General context <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="general_context" id="general_context" class="form-control" rows="4">{{ old('general_context', $donor->general_context ?? '') }}</textarea>
              <div class="form-text text-muted small">Provide any significant information on the social, cultural, economic, political and/or historical context.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Control area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse" aria-expanded="false" aria-controls="control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse" aria-labelledby="control-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="institution_responsible_identifier" id="institution_responsible_identifier" class="form-control"
                     value="{{ old('institution_responsible_identifier', $donor->institution_responsible_identifier ?? '') }}">
              <div class="form-text text-muted small">Record the full authorized form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the authority record.</div>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules and/or conventions used <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="rules" id="rules" class="form-control" rows="4">{{ old('rules', $donor->rules ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the names and where useful the editions or publication dates of the conventions or rules applied.</div>
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="sources" id="sources" class="form-control" rows="4">{{ old('sources', $donor->sources ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the sources consulted in establishing the authority record.</div>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="revision_history" id="revision_history" class="form-control" rows="4">{{ old('revision_history', $donor->revision_history ?? '') }}</textarea>
              <div class="form-text text-muted small">Record the date the authority record was created and the dates of any revisions to the record.</div>
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
