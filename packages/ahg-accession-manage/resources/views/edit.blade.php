@extends('theme::layouts.1col')

@section('title')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">
      @if($accession)
        Edit accession record
      @else
        Create accession record
      @endif
    </h1>
    @if($accession)
      <span class="small">{{ $accession->title ?: $accession->identifier }}</span>
    @endif
  </div>
@endsection

@section('content')

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $accession ? route('accession.update', $accession->slug) : route('accession.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">
      {{-- Basic info --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="basic-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="true" aria-controls="basic-collapse">
            Basic info
          </button>
        </h2>
        <div id="basic-collapse" class="accordion-collapse collapse show" aria-labelledby="basic-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="identifier" class="form-label">
                Accession number <span class="form-required text-danger" title="This is a mandatory element.">*</span>
              </label>
              <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror" required
                     value="{{ old('identifier', $accession->identifier ?? '') }}">
              @error('identifier')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

          <div class="mb-3">
            <label for="alternative_identifiers" class="form-label">Alternative identifier(s)</label>
            <textarea class="form-control" id="alternative_identifiers" name="alternative_identifiers" rows="2" placeholder="One per line: Label | Identifier">{{ old('alternative_identifiers', $accession->alternative_identifiers ?? '') }}</textarea>
            <div class="form-text">Enter one per line in format: Label | Identifier</div>
          </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title</label>
              <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                     value="{{ old('title', $accession->title ?? '') }}">
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="date" class="form-label">Acquisition date</label>
              <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror"
                     value="{{ old('date', $accession->date ?? '') }}">
              @error('date')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="acquisition_type_id" class="form-label">Acquisition type</label>
              <select name="acquisition_type_id" id="acquisition_type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['acquisitionTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('acquisition_type_id', $accession->acquisition_type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="resource_type_id" class="form-label">Resource type</label>
              <select name="resource_type_id" id="resource_type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['resourceTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('resource_type_id', $accession->resource_type_id ?? '') == $type->id)>
                    {{ $type->name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>
        </div>
      </div>

      {{-- Donor area (display only) --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="donor-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#donor-collapse" aria-expanded="false" aria-controls="donor-collapse">
            Donor/Transferring body area
          </button>
        </h2>
        <div id="donor-collapse" class="accordion-collapse collapse" aria-labelledby="donor-heading">
          <div class="accordion-body">
            @if($donor)
              <div class="mb-3">
                <label class="form-label">Related donor</label>
                <div>
                  <a href="{{ route('actor.show', $donor->slug) }}">{{ $donor->name }}</a>
                </div>
              </div>
            @else
              <p class="text-muted">No donor linked to this accession.</p>
            @endif
          </div>
        </div>
      </div>

      {{-- Area 1: Content and structure --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="area1-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#area1-collapse" aria-expanded="false" aria-controls="area1-collapse">
            Content and structure area
          </button>
        </h2>
        <div id="area1-collapse" class="accordion-collapse collapse" aria-labelledby="area1-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="6">{{ old('scope_and_content', $accession->scope_and_content ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival history</label>
              <textarea name="archival_history" id="archival_history" class="form-control" rows="4">{{ old('archival_history', $accession->archival_history ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="source_of_acquisition" class="form-label">Source of acquisition</label>
              <textarea name="source_of_acquisition" id="source_of_acquisition" class="form-control" rows="4">{{ old('source_of_acquisition', $accession->source_of_acquisition ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Area 2: Location and extent --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="area2-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#area2-collapse" aria-expanded="false" aria-controls="area2-collapse">
            Location and extent area
          </button>
        </h2>
        <div id="area2-collapse" class="accordion-collapse collapse" aria-labelledby="area2-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="location_information" class="form-label">Location information</label>
              <textarea name="location_information" id="location_information" class="form-control" rows="4">{{ old('location_information', $accession->location_information ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="received_extent_units" class="form-label">Received extent units</label>
              <textarea name="received_extent_units" id="received_extent_units" class="form-control" rows="4">{{ old('received_extent_units', $accession->received_extent_units ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical characteristics</label>
              <textarea name="physical_characteristics" id="physical_characteristics" class="form-control" rows="4">{{ old('physical_characteristics', $accession->physical_characteristics ?? '') }}</textarea>
            </div>
          </div>
        </div>
      </div>

      {{-- Area 3: Administration --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="area3-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#area3-collapse" aria-expanded="false" aria-controls="area3-collapse">
            Administration area
          </button>
        </h2>
        <div id="area3-collapse" class="accordion-collapse collapse" aria-labelledby="area3-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling</label>
              <textarea name="appraisal" id="appraisal" class="form-control" rows="4">{{ old('appraisal', $accession->appraisal ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="processing_notes" class="form-label">Processing notes</label>
              <textarea name="processing_notes" id="processing_notes" class="form-control" rows="4">{{ old('processing_notes', $accession->processing_notes ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="processing_status_id" class="form-label">Processing status</label>
              <select name="processing_status_id" id="processing_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['processingStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('processing_status_id', $accession->processing_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="processing_priority_id" class="form-label">Processing priority</label>
              <select name="processing_priority_id" id="processing_priority_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['processingPriorities'] as $priority)
                  <option value="{{ $priority->id }}" @selected(old('processing_priority_id', $accession->processing_priority_id ?? '') == $priority->id)>
                    {{ $priority->name }}
                  </option>
                @endforeach
              </select>
            </div>

          <div class="mb-3">
            <label for="creators" class="form-label">Creator(s)</label>
            <input type="text" class="form-control" id="creators" name="creators" value="{{ old('creators', $accession->creators ?? '') }}" placeholder="Type to search authority records...">
            <div class="form-text">Link creators (authority records) to this accession</div>
          </div>
          </div>
        </div>
      </div>

      {{-- Linked information objects --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="heading-io-links"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-io-links">Information objects</button></h2>
        <div id="collapse-io-links" class="accordion-collapse collapse" aria-labelledby="heading-io-links">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="information_objects" class="form-label">Linked archival descriptions</label>
              <input type="text" class="form-control" id="information_objects" name="information_objects" value="{{ old('information_objects') }}" placeholder="Type to search archival descriptions...">
              <div class="form-text">Link this accession to archival descriptions</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($accession)
          <li><a href="{{ route('accession.show', $accession->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
          <li><a href="{{ route('accession.confirmDelete', $accession->slug) }}" class="btn atom-btn-outline-danger">Delete</a></li>
        @else
          <li><a href="{{ route('accession.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
      </ul>
    </section>
  </form>

@endsection
