@extends('theme::layouts.1col')

@section('title', $title)

@section('content')

  @if(isset($resource))
    <div class="multiline-header d-flex flex-column mb-3">
      <h1 class="mb-0" aria-describedby="heading-label">{{ $title }}</h1>
      <span class="small" id="heading-label">{{ $resource->title ?? '' }}</span>
    </div>
  @else
    <h1>{{ $title }}</h1>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('informationobject.import.process') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="importType" value="{{ $type }}">
    @if(isset($resource))
      <input type="hidden" name="slug" value="{{ $resource->slug }}">
    @endif

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="import-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#import-collapse" aria-expanded="true">
            Import options
          </button>
        </h2>
        <div id="import-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">

            @if($type === 'csv')
              <div class="mb-3">
                <label class="form-label" for="object-type-select">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="informationObject">Archival description</option>
                  <option value="accession">Accession</option>
                  <option value="authorityRecord">Authority record</option>
                  <option value="authorityRecordRelationship">Authority record relationship</option>
                  <option value="event">Event</option>
                  <option value="repository">Repository</option>
                </select>
              </div>
            @endif

            @if($type === 'xml')
              <div class="mb-3">
                <label for="object-type-select" class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="ead">EAD 2002</option>
                  <option value="eac-cpf">EAC CPF</option>
                  <option value="mods">MODS</option>
                  <option value="dc">DC</option>
                </select>
              </div>
            @endif

            @if($type === 'csv')
              <div class="mb-3">
                <label class="form-label" for="update-type-select">Update behaviours <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="updateType" id="update-type-select">
                  <option value="import-as-new">Ignore matches and create new records on import</option>
                  <option value="match-and-update">Update matches ignoring blank fields in CSV</option>
                  <option value="delete-and-replace">Delete matches and replace with imported records</option>
                </select>
              </div>
            @else
              <div class="mb-3">
                <label class="form-label" for="update-type-select">Update behaviours <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="updateType" id="update-type-select">
                  <option value="import-as-new">Ignore matches and import as new</option>
                  <option value="delete-and-replace">Delete matches and replace with imports</option>
                </select>
              </div>
            @endif

            <div class="mb-3 form-check">
              <input class="form-check-input" name="skipUnmatched" id="skip-unmatched-input" type="checkbox">
              <label class="form-check-label" for="skip-unmatched-input">Skip unmatched records <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>

            <div class="mb-3">
              <label class="form-label" for="collection-select">Limit matches to: <span class="badge bg-secondary ms-1">Optional</span></label>
              <select class="form-select" name="collection" id="collection-select">
                <option value="">Top-level description</option>
                @foreach($collections ?? [] as $coll)
                  <option value="{{ $coll->slug }}">{{ $coll->title }}</option>
                @endforeach
              </select>
            </div>

            <div class="mb-3 form-check">
              <input class="form-check-input" name="skipMatched" id="skip-matched-input" type="checkbox">
              <label class="form-check-label" for="skip-matched-input">Skip matched records <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>

            <div class="mb-3 form-check">
              <input class="form-check-input" name="noIndex" id="no-index-input" type="checkbox">
              <label class="form-check-label" for="no-index-input">Do not index imported items <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>

          </div>
        </div>
      </div>

      <div class="accordion-item">
        <h2 class="accordion-header" id="file-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#file-collapse" aria-expanded="true">
            Select file
          </button>
        </h2>
        <div id="file-collapse" class="accordion-collapse collapse show">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="import-file" class="form-label">Select a file to import <span class="badge bg-secondary ms-1">Optional</span></label>
              <input class="form-control" type="file" id="import-file" name="file"
                     accept="{{ $type === 'csv' ? '.csv' : '.xml' }}">
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="Import">
    </section>

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
</style>
@endpush
@endsection
