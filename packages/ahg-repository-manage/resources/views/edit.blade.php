@extends('theme::layouts.1col')

@section('title', ($repository ? 'Edit' : 'Add new') . ' archival institution - ISDIAH')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0" aria-describedby="heading-label">
      @if($repository)
        Edit archival institution - ISDIAH
      @else
        Add new archival institution - ISDIAH
      @endif
    </h1>
    @if($repository)
      <span class="small" id="heading-label">{{ $repository->authorized_form_of_name }}</span>
    @endif
  </div>

  @if($errors->any())
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $repository ? route('repository.update', $repository->slug) : route('repository.store') }}"
        id="editForm">
    @csrf

    <div class="accordion mb-3">

      {{-- ===== Identity area (ISDIAH 5.1) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="false" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="identifier" class="form-label">
                Identifier
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="identifier" id="identifier" class="form-control"
                     value="{{ old('identifier', $repository->identifier ?? '') }}">
              <div class="form-text text-muted small">"Record the numeric or alpha-numeric code identifying the institution in accordance with the relevant international and national standards." (ISDIAH 5.1.1)</div>
            </div>

            <div class="mb-3">
              <label for="authorized_form_of_name" class="form-label">
                Authorized form of name
                <span class="form-required" title="This is a mandatory element.">*</span> <span class="badge bg-danger ms-1">Required</span></label>
              <input type="text" name="authorized_form_of_name" id="authorized_form_of_name" class="form-control" required
                     value="{{ old('authorized_form_of_name', $repository->authorized_form_of_name ?? '') }}">
              <div class="form-text text-muted small">"Record the standardised form of name of the institution, adding appropriate qualifiers (for instance dates, place, etc.), if necessary. Specify separately in the Rules and/or conventions used element (5.6.3) which set of rules has been applied for this element." (ISDIAH 5.1.2)</div>
            </div>

            <div class="mb-3">
              <label for="parallel_name" class="form-label">Parallel form(s) of name <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="parallel_name" id="parallel_name" class="form-control"
                     value="{{ old('parallel_name', $parallelNames->first()->name ?? '') }}">
              <div class="form-text text-muted small">"Purpose: To indicate the various forms in which the authorised form of name of an institution occurs in other languages or script form(s). Rule: Record the parallel form(s) of name of the institution in accordance with any relevant national or international conventions or rules applied by the agency that created the description, including any necessary sub elements and/or qualifiers required by those conventions or rules. Specify in the Rules and/or conventions used element (5.6.3) which rules have been applied." (ISDIAH 5.1.3)</div>
            </div>

            <div class="mb-3">
              <label for="other_name" class="form-label">Other form(s) of name <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="other_name" id="other_name" class="form-control"
                     value="{{ old('other_name', $otherNames->first()->name ?? '') }}">
              <div class="form-text text-muted small">"Record any other name(s) by which the institution may be known. This could include other forms of the same name, acronyms, other institutional names, or changes of name over time, including, if possible, relevant dates." (ISDIAH 5.1.4)</div>
            </div>

            <div class="mb-3">
              <label for="repository_type" class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="repository_type" id="repository_type" class="form-control"
                     value="{{ old('repository_type', $repository->repository_type ?? '') }}" placeholder="Type to search repository types..." autocomplete="off">
              <div class="form-text text-muted small">Record the type of the institution. (ISDIAH 5.1.5) Select as many types as desired from the drop-down menu; these values are drawn from the Repository Types taxonomy.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Contact area (ISDIAH 5.2) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="contact-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contact-collapse" aria-expanded="false" aria-controls="contact-collapse">
            Contact area
          </button>
        </h2>
        <div id="contact-collapse" class="accordion-collapse collapse" aria-labelledby="contact-heading">
          <div class="accordion-body">

            <h3 class="fs-6 mb-2">Related contact information</h3>

            <div class="table-responsive">
              <table class="table table-bordered mb-0" id="contact-table">
                <thead>
                  <tr>
                    <th class="w-80">Contact person</th>
                    <th class="w-20">Primary</th>
                    <th><span class="visually-hidden">Actions</span></th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($contacts ?? collect() as $ci)
                    <tr>
                      <td>{{ $ci->contact_person ?? '' }}</td>
                      <td>{{ !empty($ci->primary_contact) ? 'Yes' : 'No' }}</td>
                      <td class="text-nowrap">
                        <button type="button" class="btn atom-btn-white me-1 edit-contact-row" data-bs-toggle="modal" data-bs-target="#contact-modal" data-index="{{ $loop->index }}">
                          <i class="fas fa-fw fa-pencil-alt" aria-hidden="true"></i>
                          <span class="visually-hidden">Edit row</span>
                        </button>
                        <button type="button" class="btn atom-btn-white delete-contact-row">
                          <i class="fas fa-fw fa-times" aria-hidden="true"></i>
                          <span class="visually-hidden">Delete row</span>
                        </button>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="3">
                      <button type="button" class="btn atom-btn-white" data-bs-toggle="modal" data-bs-target="#contact-modal">
                        <i class="fas fa-plus me-1" aria-hidden="true"></i>Add new
                      </button>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <!-- Contact Modal -->
            <div class="modal fade" id="contact-modal" data-bs-backdrop="static" tabindex="-1" aria-labelledby="related-contact-information-heading" aria-hidden="true">
              <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                  <div class="modal-header">
                    <h4 class="h5 modal-title" id="related-contact-information-heading">Related contact information</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal">
                      <span class="visually-hidden">Close</span>
                    </button>
                  </div>

                  <div class="modal-body pb-2">

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
                          <label class="form-label">Primary contact <span class="badge bg-secondary ms-1">Optional</span></label>
                          <select name="contact_primary" class="form-select">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label for="contact_person" class="form-label">Contact person <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_person" id="contact_person" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_telephone" class="form-label">Phone <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="tel" name="contact_telephone" id="contact_telephone" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_fax" class="form-label">Fax <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="tel" name="contact_fax" id="contact_fax" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_email" class="form-label">Email <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="email" name="contact_email" id="contact_email" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_website" class="form-label">URL <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="url" name="contact_website" id="contact_website" class="form-control" placeholder="https://">
                        </div>
                      </div>

                      <div class="tab-pane fade" id="pills-phys" role="tabpanel" aria-labelledby="pills-phys-tab">
                        <div class="mb-3">
                          <label for="contact_street_address" class="form-label">Street address <span class="badge bg-secondary ms-1">Optional</span></label>
                          <textarea name="contact_street_address" id="contact_street_address" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                          <label for="contact_region" class="form-label">Region/province <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_region" id="contact_region" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_country" class="form-label">Country <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_country" id="contact_country" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_postal_code" class="form-label">Postal code <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_postal_code" id="contact_postal_code" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_city" class="form-label">City <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_city" id="contact_city" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_latitude" class="form-label">Latitude <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_latitude" id="contact_latitude" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_longitude" class="form-label">Longitude <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_longitude" id="contact_longitude" class="form-control">
                        </div>
                      </div>

                      <div class="tab-pane fade" id="pills-other" role="tabpanel" aria-labelledby="pills-other-tab">
                        <div class="mb-3">
                          <label for="contact_type" class="form-label">Contact type <span class="badge bg-secondary ms-1">Optional</span></label>
                          <input type="text" name="contact_type" id="contact_type" class="form-control">
                        </div>
                        <div class="mb-3">
                          <label for="contact_note" class="form-label">Note <span class="badge bg-secondary ms-1">Optional</span></label>
                          <textarea name="contact_note" id="contact_note" class="form-control" rows="2"></textarea>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn atom-btn-outline-success modal-submit" data-bs-dismiss="modal">Submit</button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Description area (ISDIAH 5.3) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="history" class="form-label">History <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="history" id="history" class="form-control" rows="6">{{ old('history', $repository->history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record any relevant information about the history of the institution. This element may include information on dates of establishment, changes of names, changes of legislative mandates, or of any other sources of authority for the institution." (ISDIAH 5.3.1)</div>
            </div>

            <div class="mb-3">
              <label for="geocultural_context" class="form-label">Geographical and cultural context <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="geocultural_context" id="geocultural_context" class="form-control" rows="4">{{ old('geocultural_context', $repository->geocultural_context ?? '') }}</textarea>
              <div class="form-text text-muted small">"Identify the geographical area the institution belongs to. Record any other relevant information about the cultural context of the institution." (ISDIAH 5.3.2)</div>
            </div>

            <div class="mb-3">
              <label for="mandates" class="form-label">Mandates/Sources of authority <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="mandates" id="mandates" class="form-control" rows="4">{{ old('mandates', $repository->mandates ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record any document, law, directive or charter which acts as a source of authority for the powers, functions and responsibilities of the institution, together with information on the jurisdiction(s) and covering dates when the mandate(s) applied or were changed." (ISDIAH 5.3.3)</div>
            </div>

            <div class="mb-3">
              <label for="internal_structures" class="form-label">Administrative structure <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="internal_structures" id="internal_structures" class="form-control" rows="4">{{ old('internal_structures', $repository->internal_structures ?? '') }}</textarea>
              <div class="form-text text-muted small">"Describe, in narrative form or using organisational charts, the current administrative structure of the institution." (ISDIAH 5.3.4)</div>
            </div>

            <div class="mb-3">
              <label for="collecting_policies" class="form-label">Records management and collecting policies <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="collecting_policies" id="collecting_policies" class="form-control" rows="4">{{ old('collecting_policies', $repository->collecting_policies ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information about the records management and collecting policies of the institution. Define the scope and nature of material which the institution accessions. Indicate whether the repository seeks to acquire archival materials by transfer, gift, purchase and/or loan. If the policy includes active survey and/or rescue work, this might be spelt out." (ISDIAH 5.3.5)</div>
            </div>

            <div class="mb-3">
              <label for="buildings" class="form-label">Buildings <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="buildings" id="buildings" class="form-control" rows="4">{{ old('buildings', $repository->buildings ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information on the building(s) of the institution (general and architectural characteristics of the building, capacity of storage areas, etc). Where possible, provide information which can be used for generating statistics." (ISDIAH 5.3.6)</div>
            </div>

            <div class="mb-3">
              <label for="holdings" class="form-label">Archival and other holdings <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="holdings" id="holdings" class="form-control" rows="4">{{ old('holdings', $repository->holdings ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record a short description of the holdings of the institution, describing how and when they were formed. Provide information on volume of holdings, media formats, thematic coverage, etc." (ISDIAH 5.3.7)</div>
            </div>

            <div class="mb-3">
              <label for="finding_aids" class="form-label">Finding aids, guides and publications <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="finding_aids" id="finding_aids" class="form-control" rows="4">{{ old('finding_aids', $repository->finding_aids ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the title and other pertinent details of the published and/or unpublished finding aids and guides prepared by the institution and of any other relevant publications. Use ISO 690 Information and documentation – Bibliographic references and other national or international cataloguing rules." (ISDIAH 5.3.8)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Access area (ISDIAH 5.4) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            Access area
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="opening_times" class="form-label">Opening times <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="opening_times" id="opening_times" class="form-control" rows="4">{{ old('opening_times', $repository->opening_times ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the opening hours of the institution and annual, seasonal and public holidays, and any other planned closures. Record times associated with the availability and/or delivery of services (for example, exhibition spaces, reference services, etc.)." (ISDIAH 5.4.1)</div>
            </div>

            <div class="mb-3">
              <label for="access_conditions" class="form-label">Conditions and requirements <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="access_conditions" id="access_conditions" class="form-control" rows="4">{{ old('access_conditions', $repository->access_conditions ?? '') }}</textarea>
              <div class="form-text text-muted small">"Describe access policies, including any restrictions and/or regulations for the use of materials and facilities. Record information about registration, appointments, readers' tickets, letters of introduction, admission fees, etc. Where appropriate, make reference to the relevant legislation." (ISDIAH 5.4.2)</div>
            </div>

            <div class="mb-3">
              <label for="disabled_access" class="form-label">Accessibility <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="disabled_access" id="disabled_access" class="form-control" rows="4">{{ old('disabled_access', $repository->disabled_access ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information about travelling to the institution and details for users with disabilities, including building features, specialised equipment or tools, parking or lifts." (ISDIAH 5.4.3)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Services area (ISDIAH 5.5) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="services-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#services-collapse" aria-expanded="false" aria-controls="services-collapse">
            Services area
          </button>
        </h2>
        <div id="services-collapse" class="accordion-collapse collapse" aria-labelledby="services-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="research_services" class="form-label">Research services <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="research_services" id="research_services" class="form-control" rows="4">{{ old('research_services', $repository->research_services ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information about the onsite services provided by the institution such as languages spoken by staff, research and consultation rooms, enquiry services, internal libraries, map, microfiches, audio-visual, computer rooms, etc. Record as well any relevant information about research services, such as research undertaken by the institution, and the fee charge if applicable." (ISDIAH 5.5.1)</div>
            </div>

            <div class="mb-3">
              <label for="reproduction_services" class="form-label">Reproduction services <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="reproduction_services" id="reproduction_services" class="form-control" rows="4">{{ old('reproduction_services', $repository->reproduction_services ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information about reproduction services available to the public (microfilms, photocopies, photographs, digitised copies). Specify general conditions and restrictions to the services, including applicable fees and publication rules." (ISDIAH 5.5.2)</div>
            </div>

            <div class="mb-3">
              <label for="public_facilities" class="form-label">Public areas <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="public_facilities" id="public_facilities" class="form-control" rows="4">{{ old('public_facilities', $repository->public_facilities ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record information about spaces available for public use (permanent or temporary exhibitions, free or charged internet connection, cash machines, cafeterias, restaurants, shops, etc.)." (ISDIAH 5.5.3)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Control area (ISDIAH 5.6) ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="control-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#control-collapse" aria-expanded="false" aria-controls="control-collapse">
            Control area
          </button>
        </h2>
        <div id="control-collapse" class="accordion-collapse collapse" aria-labelledby="control-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="desc_identifier" class="form-label">Description identifier <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="desc_identifier" id="desc_identifier" class="form-control"
                     value="{{ old('desc_identifier', $repository->desc_identifier ?? '') }}">
              <div class="form-text text-muted small">"Record a unique description identifier in accordance with local and/or national conventions. If the description is to be used internationally, record the code of the country in which the description was created in accordance with the latest version of ISO 3166 - Codes for the representation of names of countries. Where the creator of the description is an international organisation, give the organisational identifier in place of the country code." (ISDIAH 5.6.1)</div>
            </div>

            <div class="mb-3">
              <label for="desc_institution_identifier" class="form-label">Institution identifier <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="desc_institution_identifier" id="desc_institution_identifier" class="form-control"
                     value="{{ old('desc_institution_identifier', $repository->desc_institution_identifier ?? '') }}">
              <div class="form-text text-muted small">"Record the full authorised form of name(s) of the agency(ies) responsible for creating, modifying or disseminating the description or, alternatively, record a code for the agency in accordance with the national or international agency code standard." (ISDIAH 5.6.2)</div>
            </div>

            <div class="mb-3">
              <label for="desc_rules" class="form-label">Rules and/or conventions used <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="desc_rules" id="desc_rules" class="form-control" rows="4">{{ old('desc_rules', $repository->desc_rules ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the names, and, where useful, the editions or publication dates of the conventions or rules applied. Specify, separately, which rules have been applied for creating the Authorised form(s) of name. Include reference to any system(s) of dating used to identify dates in this description (e.g. ISO 8601)." (ISDIAH 5.6.3)</div>
            </div>

            <div class="mb-3">
              <label for="desc_status_id" class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="desc_status_id" id="desc_status_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionStatuses'] as $status)
                  <option value="{{ $status->id }}" @selected(old('desc_status_id', $repository->desc_status_id ?? '') == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">The purpose of this field is "[t]o indicate the drafting status of the description so that users can understand the current status of the description." (ISDIAH 5.6.4). Select Final, Revised or Draft from the drop-down menu.</div>
            </div>

            <div class="mb-3">
              <label for="desc_detail_id" class="form-label">Level of detail <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="desc_detail_id" id="desc_detail_id" class="form-select">
                <option value="">-- Select --</option>
                @foreach($formChoices['descriptionDetails'] as $detail)
                  <option value="{{ $detail->id }}" @selected(old('desc_detail_id', $repository->desc_detail_id ?? '') == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
              <div class="form-text text-muted small">Select Full, Partial or Minimal from the drop-down menu. "In the absence of national guidelines or rules, minimal descriptions are those that consist only of the three essential elements of an ISDIAH compliant description (see 4.7), while full records are those that convey information for all relevant ISDIAH elements of description." (ISDIAH 5.6.5)</div>
            </div>

            <div class="mb-3">
              <label for="desc_revision_history" class="form-label">Dates of creation, revision and deletion <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="desc_revision_history" id="desc_revision_history" class="form-control" rows="4">{{ old('desc_revision_history', $repository->desc_revision_history ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the date the description was created and the dates of any revisions to the description." (ISDIAH 5.6.6)</div>
            </div>

            @if($repository && $repository->updated_at)
              <div class="mb-3">
                <h3 class="fs-6 mb-2">Last updated</h3>
                <span class="text-muted">{{ $repository->updated_at }}</span>
              </div>
            @endif

            <div class="mb-3">
              <label for="desc_language" class="form-label">Language(s) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="desc_language" name="desc_language"
                     value="{{ old('desc_language') }}" placeholder="e.g. English, French">
              <div class="form-text text-muted small">Select the language(s) of this record from the drop-down menu; enter the first few letters to narrow the choices. (ISDIAH 5.6.7)</div>
            </div>

            <div class="mb-3">
              <label for="desc_script" class="form-label">Script(s) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="desc_script" name="desc_script"
                     value="{{ old('desc_script') }}" placeholder="e.g. Latin, Cyrillic">
              <div class="form-text text-muted small">Select the script(s) of this record from the drop-down menu; enter the first few letters to narrow the choices. (ISDIAH 5.6.7)</div>
            </div>

            <div class="mb-3">
              <label for="desc_sources" class="form-label">Sources <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="desc_sources" id="desc_sources" class="form-control" rows="4">{{ old('desc_sources', $repository->desc_sources ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record the sources consulted in establishing the description of the institution." (ISDIAH 5.6.8)</div>
            </div>

            <div class="mb-3">
              <label for="maintenance_notes" class="form-label">Maintenance notes <span class="badge bg-warning ms-1">Recommended</span></label>
              <textarea name="maintenance_notes" id="maintenance_notes" class="form-control" rows="4">{{ old('maintenance_notes', $maintenanceNotes ?? '') }}</textarea>
              <div class="form-text text-muted small">"Record notes pertinent to the creation and maintenance of the description." (ISDIAH 5.6.9)</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Access points ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="points-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#points-collapse" aria-expanded="false" aria-controls="points-collapse">
            Access points
          </button>
        </h2>
        <div id="points-collapse" class="accordion-collapse collapse" aria-labelledby="points-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="thematic_area" class="form-label">Thematic area <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="thematic_area" name="thematic_area"
                     value="{{ old('thematic_area') }}" placeholder="Type to search thematic areas..." autocomplete="off">
              <div class="form-text text-muted small">Search for an existing term in the Thematic Areas taxonomy by typing the first few characters of the term name. This should be used to identify major collecting areas.</div>
            </div>

            <div class="mb-3">
              <label for="geographic_subregion" class="form-label">Geographic subregion <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" class="form-control" id="geographic_subregion" name="geographic_subregion"
                     value="{{ old('geographic_subregion') }}" placeholder="Type to search geographic subregions..." autocomplete="off">
              <div class="form-text text-muted small">Search for an existing term in the Geographic Subregion taxonomy by typing the first few characters of the term name.</div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Administration area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="admin-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false" aria-controls="admin-collapse">
            Administration area
          </button>
        </h2>
        <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="upload_limit" class="form-label">Upload limit (MB) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="number" name="upload_limit" id="upload_limit" class="form-control" min="0" step="1"
                     value="{{ old('upload_limit', $repository->upload_limit ?? '') }}" placeholder="0 = disabled">
              <div class="form-text text-muted small">Set the maximum upload size in megabytes for digital objects associated with this repository. Set to 0 or leave blank to use the global default.</div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <ul class="actions mb-3 nav gap-2">
      @if($repository)
        <li><a href="{{ route('repository.show', $repository->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      @else
        <li><a href="{{ route('repository.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
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
@endsection
