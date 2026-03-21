@extends('theme::layouts.1col')

@section('title', $title)

@section('content')

  <h1>{{ $title }}</h1>

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="alert alert-info mb-3">
    <i class="fas fa-info-circle me-2"></i>
    SKOS (Simple Knowledge Organization System) is a W3C standard for representing controlled vocabularies, taxonomies, thesauri, and classification schemes in RDF/XML format. You can import a SKOS file to populate a taxonomy with terms.
  </div>

  <form action="{{ route('sfSkosPlugin.import.process') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="import-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#import-collapse" aria-expanded="true" aria-controls="import-collapse">
            Import options
          </button>
        </h2>
        <div id="import-collapse" class="accordion-collapse collapse show" aria-labelledby="import-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="taxonomy-select">Taxonomy</label>
              <select class="form-select" name="taxonomy" id="taxonomy-select" required>
                <option value="">-- Select a taxonomy --</option>
                @foreach($taxonomies as $taxonomy)
                  <option value="{{ $taxonomy->id }}" {{ old('taxonomy') == $taxonomy->id ? 'selected' : '' }}>{{ $taxonomy->name }}</option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="select-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#select-collapse" aria-expanded="true" aria-controls="select-collapse">
            Select source
          </button>
        </h2>
        <div id="select-collapse" class="accordion-collapse collapse show" aria-labelledby="select-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="file-input" class="form-label">Select a file to import</label>
              <input class="form-control" type="file" id="file-input" name="file" accept=".xml,.rdf,.skos">
            </div>

            <div class="mb-3">
              <label for="url-input" class="form-label">Or a remote resource</label>
              <input class="form-control" type="text" id="url-input" name="url" placeholder="https://" value="{{ old('url') }}">
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
