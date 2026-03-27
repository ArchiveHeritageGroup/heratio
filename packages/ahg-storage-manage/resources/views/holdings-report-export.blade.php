@extends('theme::layouts.1col')
@section('title', 'Export storage report')
@section('body-class', 'export physicalobject')
@section('content')

  <h1>Export storage report</h1>

  <form method="POST" action="{{ url('/physicalobject/holdingsReportExport') }}">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="export-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#export-collapse" aria-expanded="true" aria-controls="export-collapse">
            Export options
          </button>
        </h2>
        <div id="export-collapse" class="accordion-collapse collapse show" aria-labelledby="export-heading">
          <div class="accordion-body">
            <div class="form-check mb-3">
              <input type="checkbox" checked name="includeEmpty" class="form-check-input" id="includeEmpty">
              <label class="form-check-label" for="includeEmpty">
                Include unlinked containers
              </label>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" checked name="includeAccessions" class="form-check-input" id="includeAccessions">
              <label class="form-check-label" for="includeAccessions">
                Include containers linked to accessions
              </label>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" checked name="includeDescriptions" class="form-check-input" id="includeDescriptions">
              <label class="form-check-label" for="includeDescriptions">
                Include containers linked to descriptions
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <ul class="actions mb-3 nav gap-2">
      <li><a href="{{ route('physicalobject.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
      <li><input class="btn atom-btn-outline-success" type="submit" id="exportSubmit" value="Export"></li>
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
</style>
@endpush
@endsection
