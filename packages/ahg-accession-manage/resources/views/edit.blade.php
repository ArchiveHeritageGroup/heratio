@extends('theme::layouts.1col')

@section('title', ($accession ? 'Edit' : 'Create') . ' accession record')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $accession ? 'Edit accession record' : 'Create accession record' }}
    </h1>
    @if($accession)
      <span class="small" id="heading-label">{{ $accession->title ?: $accession->identifier }}</span>
    @endif
  </div>

  <form method="POST"
        action="{{ $accession ? route('accession.update', $accession->slug) : route('accession.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">

      {{-- ===== Basic info ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="basic-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#basic-collapse" aria-expanded="false" aria-controls="basic-collapse">
            Basic info
          </button>
        </h2>
        <div id="basic-collapse" class="accordion-collapse collapse" aria-labelledby="basic-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="identifier" class="form-label">Accession number</label>
              <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                     value="{{ old('identifier', $accession->identifier ?? '') }}">
              @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Accession number should be a combination of values recorded in the field and should be a unique accession number for the repository</div>
            </div>

            <div class="mb-3">
              <label for="date" class="form-label">
                Acquisition date
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror"
                     value="{{ old('date', $accession->date ?? '') }}">
              @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Accession date represents the date of receipt of the materials and is added during the donation process.</div>
            </div>

            <div class="mb-3">
              <label for="source_of_acquisition" class="form-label">
                Immediate source of acquisition
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <textarea name="source_of_acquisition" id="source_of_acquisition" class="form-control" rows="3">{{ old('source_of_acquisition', $accession->source_of_acquisition ?? '') }}</textarea>
              <div class="form-text text-muted small">Identify immediate source of acquisition or transfer, and date and method of acquisition IF the information is NOT confidential.</div>
            </div>

            <div class="mb-3">
              <label for="location_information" class="form-label">
                Location information
                <span class="form-required" title="This is a mandatory element.">*</span>
              </label>
              <textarea name="location_information" id="location_information" class="form-control" rows="3">{{ old('location_information', $accession->location_information ?? '') }}</textarea>
              <div class="form-text text-muted small">A description of the physical location in the repository where the accession can be found.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Donor/Transferring body area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="donor-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#donor-collapse" aria-expanded="false" aria-controls="donor-collapse">
            Donor/Transferring body area
          </button>
        </h2>
        <div id="donor-collapse" class="accordion-collapse collapse" aria-labelledby="donor-heading">
          <div class="accordion-body">
            @if($donor ?? null)
              <div class="mb-3">
                <label class="form-label">Related donor</label>
                <div><a href="{{ route('donor.show', $donor->slug) }}">{{ $donor->name }}</a></div>
              </div>
            @endif

            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="donor_name" class="form-label">Name</label>
                <input type="text" name="donor_name" id="donor_name" class="form-control" value="{{ old('donor_name', $donor->name ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label for="donor_contact_person" class="form-label">Contact person</label>
                <input type="text" name="donor_contact_person" id="donor_contact_person" class="form-control" value="{{ old('donor_contact_person', $donorContact->contact_person ?? '') }}">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="donor_telephone" class="form-label">Telephone</label>
                <input type="text" name="donor_telephone" id="donor_telephone" class="form-control" value="{{ old('donor_telephone', $donorContact->telephone ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_fax" class="form-label">Fax</label>
                <input type="text" name="donor_fax" id="donor_fax" class="form-control" value="{{ old('donor_fax', $donorContact->fax ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_email" class="form-label">Email</label>
                <input type="email" name="donor_email" id="donor_email" class="form-control" value="{{ old('donor_email', $donorContact->email ?? '') }}">
              </div>
            </div>
            <div class="mb-3">
              <label for="donor_url" class="form-label">URL</label>
              <input type="url" name="donor_url" id="donor_url" class="form-control" value="{{ old('donor_url', $donorContact->website ?? '') }}">
            </div>
            <div class="mb-3">
              <label for="donor_street_address" class="form-label">Street address</label>
              <input type="text" name="donor_street_address" id="donor_street_address" class="form-control" value="{{ old('donor_street_address', $donorContact->street_address ?? '') }}">
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="donor_region" class="form-label">Region/province</label>
                <input type="text" name="donor_region" id="donor_region" class="form-control" value="{{ old('donor_region', $donorContact->region ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_country" class="form-label">Country</label>
                <input type="text" name="donor_country" id="donor_country" class="form-control" value="{{ old('donor_country', $donorContact->country_code ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_postal_code" class="form-label">Postal code</label>
                <input type="text" name="donor_postal_code" id="donor_postal_code" class="form-control" value="{{ old('donor_postal_code', $donorContact->postal_code ?? '') }}">
              </div>
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label for="donor_city" class="form-label">City</label>
                <input type="text" name="donor_city" id="donor_city" class="form-control" value="{{ old('donor_city', $donorContact->city ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_latitude" class="form-label">Latitude</label>
                <input type="text" name="donor_latitude" id="donor_latitude" class="form-control" value="{{ old('donor_latitude', $donorContact->latitude ?? '') }}">
              </div>
              <div class="col-md-4 mb-3">
                <label for="donor_longitude" class="form-label">Longitude</label>
                <input type="text" name="donor_longitude" id="donor_longitude" class="form-control" value="{{ old('donor_longitude', $donorContact->longitude ?? '') }}">
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label for="donor_contact_type" class="form-label">Contact type</label>
                <input type="text" name="donor_contact_type" id="donor_contact_type" class="form-control" value="{{ old('donor_contact_type', $donorContact->contact_type ?? '') }}">
              </div>
              <div class="col-md-6 mb-3">
                <label for="donor_note" class="form-label">Note</label>
                <textarea name="donor_note" id="donor_note" class="form-control" rows="2">{{ old('donor_note', $donorContact->note ?? '') }}</textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Administrative area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="admin-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false" aria-controls="admin-collapse">
            Administrative area
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="acquisition_type_id" class="form-label">Acquisition type</label>
              <select name="acquisition_type_id" id="acquisition_type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['acquisitionTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('acquisition_type_id', $accession->acquisition_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Term describing the type of accession transaction and referring to the way in which the accession was acquired.</div>
            </div>

            <div class="mb-3">
              <label for="resource_type_id" class="form-label">Resource type</label>
              <select name="resource_type_id" id="resource_type_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['resourceTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('resource_type_id', $accession->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select the type of resource represented in the accession, either public or private.</div>
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title</label>
              <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $accession->title ?? '') }}">
              <div class="form-text text-muted small">The title of the accession, usually the creator name and term describing the format of the accession materials.</div>
            </div>

            <div class="mb-3">
              <label for="creators" class="form-label">Creators</label>
              <input type="text" name="creators" id="creators" class="form-control" value="{{ old('creators', $accession->creators ?? '') }}" placeholder="Type to search authority records...">
              <div class="form-text text-muted small">The name of the creator of the accession or the name of the department that created the accession.</div>
            </div>

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival/Custodial history</label>
              <textarea name="archival_history" id="archival_history" class="form-control" rows="3">{{ old('archival_history', $accession->archival_history ?? '') }}</textarea>
              <div class="form-text text-muted small">Information on the history of the accession. When the accession is acquired directly from the creator, do not record an archival history but record the information as the Immediate Source of Acquisition.</div>
            </div>

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $accession->scope_and_content ?? '') }}</textarea>
              <div class="form-text text-muted small">A description of the intellectual content and document types represented in the accession.</div>
            </div>

            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling</label>
              <textarea name="appraisal" id="appraisal" class="form-control" rows="3">{{ old('appraisal', $accession->appraisal ?? '') }}</textarea>
              <div class="form-text text-muted small">Record appraisal, destruction and scheduling actions taken on or planned for the unit of description, especially if they may affect the interpretation of the material.</div>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical condition</label>
              <textarea name="physical_characteristics" id="physical_characteristics" class="form-control" rows="3">{{ old('physical_characteristics', $accession->physical_characteristics ?? '') }}</textarea>
              <div class="form-text text-muted small">A description of the physical condition of the accession and if any preservation or special handling is required.</div>
            </div>

            <div class="mb-3">
              <label for="received_extent_units" class="form-label">Received extent units</label>
              <textarea name="received_extent_units" id="received_extent_units" class="form-control" rows="2">{{ old('received_extent_units', $accession->received_extent_units ?? '') }}</textarea>
              <div class="form-text text-muted small">The number of units as a whole number and the measurement of the received volume of records in the accession.</div>
            </div>

            <div class="mb-3">
              <label for="processing_status_id" class="form-label">Processing status</label>
              <select name="processing_status_id" id="processing_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['processingStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('processing_status_id', $accession->processing_status_id ?? '') == $status->id)>{{ $status->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">An indicator of the accessioning process.</div>
            </div>

            <div class="mb-3">
              <label for="processing_priority_id" class="form-label">Processing priority</label>
              <select name="processing_priority_id" id="processing_priority_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['processingPriorities'] as $priority)
                  <option value="{{ $priority->id }}" @selected(old('processing_priority_id', $accession->processing_priority_id ?? '') == $priority->id)>{{ $priority->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Indicates the priority the repository assigns to completing the processing of the accession.</div>
            </div>

            <div class="mb-3">
              <label for="processing_notes" class="form-label">Processing notes</label>
              <textarea name="processing_notes" id="processing_notes" class="form-control" rows="3">{{ old('processing_notes', $accession->processing_notes ?? '') }}</textarea>
              <div class="form-text text-muted small">Notes about the processing plan, describing what needs to be done for the accession to be processed completely.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Archival description area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="io-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#io-collapse" aria-expanded="false" aria-controls="io-collapse">
            Archival description area
          </button>
        </h2>
        <div id="io-collapse" class="accordion-collapse collapse" aria-labelledby="io-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="information_objects" class="form-label">Archival description</label>
              <input type="text" name="information_objects" id="information_objects" class="form-control" value="{{ old('information_objects') }}" placeholder="Type to search archival descriptions...">
              <div class="form-text text-muted small">Link this accession to existing archival descriptions.</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($accession)
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('accession.show', $accession->slug) }}">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a class="btn atom-btn-outline-light" role="button" href="{{ route('accession.browse') }}">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
      @endif
    </ul>
  </form>
@endsection
