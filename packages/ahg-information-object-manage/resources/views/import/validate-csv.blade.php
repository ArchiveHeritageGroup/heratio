@extends('theme::layouts.1col')

@section('title', $title)

@section('content')

  <h1>{{ $title }}</h1>

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('object.validateCsv.process') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="options-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#options-collapse" aria-expanded="true" aria-controls="options-collapse">
            CSV Validation options
          </button>
        </h2>
        <div id="options-collapse" class="accordion-collapse collapse show" aria-labelledby="options-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label" for="object-type-select">Type</label>
              <select class="form-select" name="objectType" id="object-type-select">
                <option value="informationObject" {{ (isset($objectType) && $objectType === 'informationObject') ? 'selected' : '' }}>Archival description</option>
                <option value="accession" {{ (isset($objectType) && $objectType === 'accession') ? 'selected' : '' }}>Accession</option>
                <option value="authorityRecord" {{ (isset($objectType) && $objectType === 'authorityRecord') ? 'selected' : '' }}>Authority record</option>
                <option value="authorityRecordRelationship" {{ (isset($objectType) && $objectType === 'authorityRecordRelationship') ? 'selected' : '' }}>Authority record relationship</option>
                <option value="event" {{ (isset($objectType) && $objectType === 'event') ? 'selected' : '' }}>Event</option>
                <option value="repository" {{ (isset($objectType) && $objectType === 'repository') ? 'selected' : '' }}>Repository</option>
              </select>
            </div>
          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="select-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#select-collapse" aria-expanded="true" aria-controls="select-collapse">
            Select file
          </button>
        </h2>
        <div id="select-collapse" class="accordion-collapse collapse show" aria-labelledby="select-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="file-input" class="form-label">Select a CSV file to validate</label>
              <input class="form-control" type="file" id="file-input" name="file" accept=".csv">
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="Validate">
    </section>

  </form>

  @if(isset($results) && $results !== null)
    <div class="card mt-4">
      <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">
          <i class="fas fa-clipboard-check me-2"></i>Validation Results
          <small class="text-muted ms-2">{{ $fileName ?? '' }}</small>
        </h5>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-auto">
            <span class="badge bg-success fs-6">{{ $validCount }} valid</span>
          </div>
          <div class="col-auto">
            <span class="badge bg-danger fs-6">{{ $invalidCount }} invalid</span>
          </div>
          <div class="col-auto">
            <span class="badge bg-warning text-dark fs-6">{{ $missingCount }} missing required</span>
          </div>
        </div>

        @if($missingCount === 0 && $invalidCount === 0)
          <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>All columns are valid and all required columns are present. This CSV is ready for import.
          </div>
        @elseif($missingCount > 0)
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>There are {{ $missingCount }} required column(s) missing. The import may fail or produce incomplete records.
          </div>
        @endif

        <div class="table-responsive">
          <table class="table table-bordered table-striped table-hover">
            <thead>
              <tr style="background:var(--ahg-primary);color:#fff">
                <th>Column name</th>
                <th style="width: 120px">Status</th>
                <th>Message</th>
              </tr>
            </thead>
            <tbody>
              @foreach($results as $result)
                <tr>
                  <td><code>{{ $result['column'] }}</code></td>
                  <td>
                    @if($result['status'] === 'valid')
                      <span class="badge bg-success">Valid</span>
                    @elseif($result['status'] === 'invalid')
                      <span class="badge bg-danger">Invalid</span>
                    @elseif($result['status'] === 'missing')
                      <span class="badge bg-warning text-dark">Missing</span>
                    @endif
                  </td>
                  <td>{{ $result['message'] }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

@endsection
