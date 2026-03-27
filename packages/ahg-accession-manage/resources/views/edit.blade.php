@extends('theme::layouts.1col')

@section('title', ($accession ? 'Edit' : 'Add new') . ' accession record')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      {{ $accession ? 'Edit accession record' : 'Add new accession record' }}
    </h1>
    @if($accession)
      <span class="small" id="heading-label">{{ $accession->title ?: $accession->identifier }}</span>
    @endif
  </div>

  @if(request('accession'))
    <div class="alert alert-info" role="alert">
      You are creating an accrual to accession {{ request('accession') }}
    </div>
  @endif

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
              <label for="identifier" class="form-label">Accession number <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="identifier" id="identifier" class="form-control @error('identifier') is-invalid @enderror"
                     value="{{ old('identifier', $accession->identifier ?? '') }}">
              @error('identifier') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Accession number should be a combination of values recorded in the field and should be a unique accession number for the repository</div>
            </div>

            <!-- Alternative identifier(s) -->
            <div class="text-end mb-3">
              <button class="btn atom-btn-white text-wrap collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#alternative-identifiers-table" aria-expanded="false" aria-controls="alternative-identifiers-table">
                <i class="fas fa-plus me-1" aria-hidden="true"></i>
                Add alternative identifier(s)
              </button>
            </div>

            <div id="alternative-identifiers-table" class="collapse">
              <h3 class="fs-6 mb-2">Alternative identifier(s)</h3>
              <div class="table-responsive mb-2">
                <table class="table table-bordered mb-0" id="altids-table">
                  <thead>
                    <tr>
                      <th id="alt-identifiers-type-head" class="w-30">Type</th>
                      <th id="alt-identifiers-identifier-head" class="w-35">Identifier</th>
                      <th id="alt-identifiers-note-head" class="w-35">Notes</th>
                      <th><span class="visually-hidden">Delete</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>
                        <select name="alternativeIdentifiers[0][identifierType]" class="form-select form-select-sm" aria-labelledby="alt-identifiers-type-head" aria-describedby="alt-identifiers-table-help">
                          <option value=""></option>
                          @foreach($formChoices['altIdentifierTypes'] ?? [] as $ait)
                            <option value="{{ $ait->id }}">{{ $ait->name }}</option>
                          @endforeach
                        </select>
                      </td>
                      <td>
                        <input type="text" name="alternativeIdentifiers[0][identifier]" class="form-control form-control-sm" aria-labelledby="alt-identifiers-identifier-head" aria-describedby="alt-identifiers-table-help">
                      </td>
                      <td>
                        <textarea name="alternativeIdentifiers[0][note]" class="form-control form-control-sm" rows="1" aria-labelledby="alt-identifiers-note-head" aria-describedby="alt-identifiers-table-help"></textarea>
                      </td>
                      <td>
                        <button type="button" class="btn atom-btn-white remove-altid-row">
                          <i class="fas fa-times" aria-hidden="true"></i>
                          <span class="visually-hidden">Delete row</span>
                        </button>
                      </td>
                    </tr>
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="4">
                        <button type="button" class="btn atom-btn-white" id="add-altid-row">
                          <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                        </button>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>
              <div class="form-text mb-3" id="alt-identifiers-table-help">
                <strong>Type:</strong> Enter a name for the alternative identifier field that indicates its purpose and usage.<br><strong>Identifier:</strong> Enter a legacy reference code, alternative identifier, or any other alpha-numeric string associated with the record.
              </div>
            </div>

            <div class="mb-3">
              <label for="date" class="form-label">
                Acquisition date
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="date" name="date" id="date" class="form-control @error('date') is-invalid @enderror"
                     value="{{ old('date', $accession->date ?? '') }}">
              @error('date') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-muted small">Accession date represents the date of receipt of the materials and is added during the donation process.</div>
            </div>

            <div class="mb-3">
              <label for="source_of_acquisition" class="form-label">
                Immediate source of acquisition
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <textarea name="source_of_acquisition" id="source_of_acquisition" class="form-control" rows="3">{{ old('source_of_acquisition', $accession->source_of_acquisition ?? '') }}</textarea>
              <div class="form-text text-muted small">Identify immediate source of acquisition or transfer, and date and method of acquisition IF the information is NOT confidential.</div>
            </div>

            <div class="mb-3">
              <label for="location_information" class="form-label">
                Location information
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
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

            <h3 class="fs-6 mb-2">Related donors</h3>

            <div class="atom-table-modal">
              <div class="table-responsive">
                <table class="table table-bordered mb-0" id="donor-table">
                  <thead>
                    <tr>
                      <th class="w-100">Name</th>
                      <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                  </thead>
                  <tbody>
                    @if($donor ?? null)
                      <tr>
                        <td>{{ $donor->name }}</td>
                        <td class="text-nowrap">
                          <button type="button" class="btn atom-btn-white me-1 edit-donor-row" data-bs-toggle="modal" data-bs-target="#donor-modal">
                            <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                            <span class="visually-hidden">Edit row</span>
                          </button>
                          <button type="button" class="btn atom-btn-white delete-donor-row">
                            <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                            <span class="visually-hidden">Delete row</span>
                          </button>
                        </td>
                      </tr>
                    @endif
                  </tbody>
                  <tfoot>
                    <tr>
                      <td colspan="2">
                        <button type="button" class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#donor-modal">
                          <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                        </button>
                      </td>
                    </tr>
                  </tfoot>
                </table>
              </div>

              <!-- Donor Modal -->
              <div class="modal fade" id="donor-modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="related-donor-heading" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h4 class="h5 modal-title" id="related-donor-heading">Related donor record</h4>
                      <button type="button" class="btn-close" data-bs-dismiss="modal">
                        <span class="visually-hidden">Close</span>
                      </button>
                    </div>

                    <div class="modal-body pb-2">

                      <div class="mb-3">
                        <label for="donor_name" class="form-label">Name <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" name="donor_name" id="donor_name" class="form-control" value="{{ old('donor_name', $donor->name ?? '') }}" placeholder="Type to search donors..." autocomplete="off">
                        <div class="form-text text-muted small">This is the legal entity field and provides the contact information for the person(s) or the institution that donated or transferred the materials. It has the option of multiple instances and provides the option of creating more than one contact record using the same form.</div>
                      </div>

                      <h5>Primary contact information</h5>

                      <ul class="nav nav-pills mb-3 d-flex gap-2" role="tablist">
                        <li class="nav-item" role="presentation">
                          <button class="btn atom-btn-white active-primary text-wrap active" id="pills-main-tab" data-bs-toggle="pill" data-bs-target="#pills-main" type="button" role="tab" aria-controls="pills-main" aria-selected="true">Main</button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="btn atom-btn-white active-primary text-wrap" id="pills-phys-tab" data-bs-toggle="pill" data-bs-target="#pills-phys" type="button" role="tab" aria-controls="pills-phys" aria-selected="false">Physical location</button>
                        </li>
                        <li class="nav-item" role="presentation">
                          <button class="btn atom-btn-white active-primary text-wrap" id="pills-other-tab" data-bs-toggle="pill" data-bs-target="#pills-other" type="button" role="tab" aria-controls="pills-other" aria-selected="false">Other details</button>
                        </li>
                      </ul>

                      <div class="tab-content">
                        <div class="tab-pane fade show active" id="pills-main" role="tabpanel" aria-labelledby="pills-main-tab">
                          <div class="mb-3">
                            <label for="donor_contact_person" class="form-label">Contact person <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_contact_person" id="donor_contact_person" class="form-control" value="{{ old('donor_contact_person', $donorContact->contact_person ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_telephone" class="form-label">Telephone <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_telephone" id="donor_telephone" class="form-control" value="{{ old('donor_telephone', $donorContact->telephone ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_fax" class="form-label">Fax <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_fax" id="donor_fax" class="form-control" value="{{ old('donor_fax', $donorContact->fax ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_email" class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="email" name="donor_email" id="donor_email" class="form-control" value="{{ old('donor_email', $donorContact->email ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_url" class="form-label">URL <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="url" name="donor_url" id="donor_url" class="form-control" value="{{ old('donor_url', $donorContact->website ?? '') }}">
                          </div>
                        </div>

                        <div class="tab-pane fade" id="pills-phys" role="tabpanel" aria-labelledby="pills-phys-tab">
                          <div class="mb-3">
                            <label for="donor_street_address" class="form-label">Street address <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_street_address" id="donor_street_address" class="form-control" value="{{ old('donor_street_address', $donorContact->street_address ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_region" class="form-label">Region/province <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_region" id="donor_region" class="form-control" value="{{ old('donor_region', $donorContact->region ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_country" class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_country" id="donor_country" class="form-control" value="{{ old('donor_country', $donorContact->country_code ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_postal_code" class="form-label">Postal code <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_postal_code" id="donor_postal_code" class="form-control" value="{{ old('donor_postal_code', $donorContact->postal_code ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_city" class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_city" id="donor_city" class="form-control" value="{{ old('donor_city', $donorContact->city ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_latitude" class="form-label">Latitude <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_latitude" id="donor_latitude" class="form-control" value="{{ old('donor_latitude', $donorContact->latitude ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_longitude" class="form-label">Longitude <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_longitude" id="donor_longitude" class="form-control" value="{{ old('donor_longitude', $donorContact->longitude ?? '') }}">
                          </div>
                        </div>

                        <div class="tab-pane fade" id="pills-other" role="tabpanel" aria-labelledby="pills-other-tab">
                          <div class="mb-3">
                            <label for="donor_contact_type" class="form-label">Contact type <span class="badge bg-secondary ms-1">Optional</span></label>
                            <input type="text" name="donor_contact_type" id="donor_contact_type" class="form-control" value="{{ old('donor_contact_type', $donorContact->contact_type ?? '') }}">
                          </div>
                          <div class="mb-3">
                            <label for="donor_note" class="form-label">Note <span class="badge bg-secondary ms-1">Optional</span></label>
                            <textarea name="donor_note" id="donor_note" class="form-control" rows="2">{{ old('donor_note', $donorContact->note ?? '') }}</textarea>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer">
                      <button type="button" class="btn atom-btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                      <button type="button" class="btn atom-btn-outline-success modal-submit">Submit</button>
                    </div>
                  </div>
                </div>
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
              <label for="acquisition_type_id" class="form-label">Acquisition type <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="acquisition_type_id" id="acquisition_type_id" class="form-select">
                <option value=""></option>
                @foreach($formChoices['acquisitionTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('acquisition_type_id', $accession->acquisition_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Term describing the type of accession transaction and referring to the way in which the accession was acquired.</div>
            </div>

            <div class="mb-3">
              <label for="resource_type_id" class="form-label">Resource type <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="resource_type_id" id="resource_type_id" class="form-select">
                <option value=""></option>
                @foreach($formChoices['resourceTypes'] as $type)
                  <option value="{{ $type->id }}" @selected(old('resource_type_id', $accession->resource_type_id ?? '') == $type->id)>{{ $type->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select the type of resource represented in the accession, either public or private.</div>
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="title" id="title" class="form-control" value="{{ old('title', $accession->title ?? '') }}">
              <div class="form-text text-muted small">The title of the accession, usually the creator name and term describing the format of the accession materials.</div>
            </div>

            <div class="mb-3">
              <label for="creators" class="form-label">Creators <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="creators" id="creators" class="form-control" value="{{ old('creators', $accession->creators ?? '') }}" placeholder="Type to search authority records..." autocomplete="off">
              <div class="form-text text-muted small">The name of the creator of the accession or the name of the department that created the accession.</div>
            </div>

            <!-- ISAD Date(s) multi-row table -->
            <h3 class="fs-6 mb-2">
              Date(s)
              <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span>
            </h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0" id="isad-dates-table">
                <thead>
                  <tr>
                    <th id="isad-events-type-head" class="w-25">Type</th>
                    <th id="isad-events-date-head" class="w-30">Date</th>
                    <th id="isad-events-start-head">Start</th>
                    <th id="isad-events-end-head">End</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <select name="editEvents[0][type]" class="form-select form-select-sm" aria-labelledby="isad-events-type-head" aria-describedby="isad-events-table-help">
                        <option value=""></option>
                        @foreach($formChoices['eventTypes'] ?? [] as $et)
                          <option value="{{ $et->id }}">{{ $et->name }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <input type="text" name="editEvents[0][date]" class="form-control form-control-sm" aria-labelledby="isad-events-date-head" aria-describedby="isad-events-table-help">
                    </td>
                    <td>
                      <input type="text" name="editEvents[0][startDate]" class="form-control form-control-sm" placeholder="YYYY-MM-DD" aria-labelledby="isad-events-start-head" aria-describedby="isad-events-table-help">
                    </td>
                    <td>
                      <input type="text" name="editEvents[0][endDate]" class="form-control form-control-sm" placeholder="YYYY-MM-DD" aria-labelledby="isad-events-end-head" aria-describedby="isad-events-table-help">
                    </td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-isaddate-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="5">
                      <button type="button" class="btn atom-btn-white" id="add-isaddate-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="form-text mb-3" id="isad-events-table-help">
              "Identify and record the date(s) of the unit of description. Identify the type of date given. Record as a single date or a range of dates as appropriate." (ISAD 3.1.3). The Date display field can be used to enter free-text date information, including typographical marks to express approximation, uncertainty, or qualification. Use the start and end fields to make the dates searchable. Do not use any qualifiers or typographical symbols to express uncertainty. Acceptable date formats: YYYYMMDD, YYYY-MM-DD, YYYY-MM, YYYY.
            </div>

            <!-- Event(s) multi-row table -->
            <h3 class="fs-6 mb-2">Event(s)</h3>
            <div class="table-responsive mb-2">
              <table class="table table-bordered mb-0" id="events-table">
                <thead>
                  <tr>
                    <th id="accession-events-type-head" class="w-20">Type</th>
                    <th id="accession-events-date-head" class="w-25">Date</th>
                    <th id="accession-events-agent-head">Agent</th>
                    <th id="accession-events-notes-head">Notes</th>
                    <th><span class="visually-hidden">Delete</span></th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>
                      <select name="events[0][eventType]" class="form-select form-select-sm" aria-labelledby="accession-events-type-head" aria-describedby="accession-events-help">
                        <option value=""></option>
                        @foreach($formChoices['eventTypes'] ?? [] as $et)
                          <option value="{{ $et->id }}">{{ $et->name }}</option>
                        @endforeach
                      </select>
                    </td>
                    <td>
                      <input type="text" name="events[0][date]" class="form-control form-control-sm" aria-labelledby="accession-events-date-head" aria-describedby="accession-events-help">
                    </td>
                    <td>
                      <input type="text" name="events[0][agent]" class="form-control form-control-sm" aria-labelledby="accession-events-agent-head" aria-describedby="accession-events-help">
                    </td>
                    <td>
                      <textarea name="events[0][note]" class="form-control form-control-sm" rows="1" aria-labelledby="accession-events-notes-head" aria-describedby="accession-events-help"></textarea>
                    </td>
                    <td>
                      <button type="button" class="btn atom-btn-white remove-event-row">
                        <i class="fas fa-times" aria-hidden="true"></i>
                        <span class="visually-hidden">Delete row</span>
                      </button>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="5">
                      <button type="button" class="btn atom-btn-white" id="add-event-row">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <div class="form-text mb-3" id="accession-events-help">
              <strong>Type:</strong> Select the type of the event.
              <strong>Date:</strong> Enter the date of the event.
              <strong>Agent:</strong> Enter the agent associated with the event.
              <strong>Note:</strong> Enter notes associated with the event.
            </div>

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival/Custodial history <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="archival_history" id="archival_history" class="form-control" rows="3">{{ old('archival_history', $accession->archival_history ?? '') }}</textarea>
              <div class="form-text text-muted small">Information on the history of the accession. When the accession is acquired directly from the creator, do not record an archival history but record the information as the Immediate Source of Acquisition.</div>
            </div>

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="scope_and_content" id="scope_and_content" class="form-control" rows="4">{{ old('scope_and_content', $accession->scope_and_content ?? '') }}</textarea>
              <div class="form-text text-muted small">A description of the intellectual content and document types represented in the accession.</div>
            </div>

            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="appraisal" id="appraisal" class="form-control" rows="3">{{ old('appraisal', $accession->appraisal ?? '') }}</textarea>
              <div class="form-text text-muted small">Record appraisal, destruction and scheduling actions taken on or planned for the unit of description, especially if they may affect the interpretation of the material.</div>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical condition <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="physical_characteristics" id="physical_characteristics" class="form-control" rows="3">{{ old('physical_characteristics', $accession->physical_characteristics ?? '') }}</textarea>
              <div class="form-text text-muted small">A description of the physical condition of the accession and if any preservation or special handling is required.</div>
            </div>

            <div class="mb-3">
              <label for="received_extent_units" class="form-label">Received extent units <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="received_extent_units" id="received_extent_units" class="form-control" rows="2">{{ old('received_extent_units', $accession->received_extent_units ?? '') }}</textarea>
              <div class="form-text text-muted small">The number of units as a whole number and the measurement of the received volume of records in the accession.</div>
            </div>

            <div class="mb-3">
              <label for="processing_status_id" class="form-label">Processing status <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="processing_status_id" id="processing_status_id" class="form-select">
                <option value=""></option>
                @foreach($formChoices['processingStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('processing_status_id', $accession->processing_status_id ?? '') == $status->id)>{{ $status->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">An indicator of the accessioning process.</div>
            </div>

            <div class="mb-3">
              <label for="processing_priority_id" class="form-label">Processing priority <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="processing_priority_id" id="processing_priority_id" class="form-select">
                <option value=""></option>
                @foreach($formChoices['processingPriorities'] as $priority)
                  <option value="{{ $priority->id }}" @selected(old('processing_priority_id', $accession->processing_priority_id ?? '') == $priority->id)>{{ $priority->name }}</option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Indicates the priority the repository assigns to completing the processing of the accession.</div>
            </div>

            <div class="mb-3">
              <label for="processing_notes" class="form-label">Processing notes <span class="badge bg-secondary ms-1">Optional</span></label>
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
            {{ config('app.ui_label_informationobject', 'Archival description') }} area
          </button>
        </h2>
        <div id="io-collapse" class="accordion-collapse collapse" aria-labelledby="io-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="information_objects" class="form-label">{{ config('app.ui_label_informationobject', 'Archival description') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="information_objects" id="information_objects" class="form-control" value="{{ old('information_objects') }}" placeholder="Type to search archival descriptions..." autocomplete="off">
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
@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Alternative identifiers multi-row
  var altIdx = 1;
  var altTypeOptions = document.querySelector('#altids-table select')?.innerHTML || '';
  document.getElementById('add-altid-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="alternativeIdentifiers[' + altIdx + '][identifierType]" class="form-select form-select-sm">' + altTypeOptions + '</select></td>' +
      '<td><input type="text" name="alternativeIdentifiers[' + altIdx + '][identifier]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="alternativeIdentifiers[' + altIdx + '][note]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-altid-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#altids-table tbody').appendChild(tr);
    altIdx++;
  });

  // Events multi-row
  var eventIdx = 1;
  var eventTypeOptions = document.querySelector('#events-table select')?.innerHTML || '';
  document.getElementById('add-event-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="events[' + eventIdx + '][eventType]" class="form-select form-select-sm">' + eventTypeOptions + '</select></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][date]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][agent]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="events[' + eventIdx + '][note]" class="form-control form-control-sm"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-event-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#events-table tbody').appendChild(tr);
    eventIdx++;
  });

  // ISAD Date(s) multi-row
  var isadIdx = 1;
  var isadTypeOptions = document.querySelector('#isad-dates-table select')?.innerHTML || '';
  document.getElementById('add-isaddate-row')?.addEventListener('click', function() {
    var tr = document.createElement('tr');
    tr.innerHTML = '<td><select name="editEvents[' + isadIdx + '][type]" class="form-select form-select-sm">' + isadTypeOptions + '</select></td>' +
      '<td><input type="text" name="editEvents[' + isadIdx + '][date]" class="form-control form-control-sm"></td>' +
      '<td><input type="text" name="editEvents[' + isadIdx + '][startDate]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>' +
      '<td><input type="text" name="editEvents[' + isadIdx + '][endDate]" class="form-control form-control-sm" placeholder="YYYY-MM-DD"></td>' +
      '<td><button type="button" class="btn atom-btn-white remove-isaddate-row"><i class="fas fa-times" aria-hidden="true"></i><span class="visually-hidden">Delete row</span></button></td>';
    document.querySelector('#isad-dates-table tbody').appendChild(tr);
    isadIdx++;
  });

  // Remove row handler for all multi-row tables
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-altid-row, .remove-event-row, .remove-isaddate-row');
    if (btn) {
      var table = btn.closest('table');
      if (table.querySelectorAll('tbody tr').length > 1) {
        btn.closest('tr').remove();
      }
    }
  });

  // Delete donor row
  document.addEventListener('click', function(e) {
    var btn = e.target.closest('.delete-donor-row');
    if (btn) {
      btn.closest('tr').remove();
      // Clear donor fields
      ['donor_name','donor_contact_person','donor_telephone','donor_fax','donor_email','donor_url',
       'donor_street_address','donor_region','donor_country','donor_postal_code','donor_city',
       'donor_latitude','donor_longitude','donor_contact_type','donor_note'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) el.value = '';
      });
    }
  });
});
</script>
@endpush
@endsection
