@extends('theme::layouts.2col')

@section('title', 'Edit: ' . ($io->title ?? 'Untitled'))
@section('body-class', 'edit informationobject')

@section('sidebar')
  @if(isset($io->repository_id) && $io->repository_id)
    @php
      $repoLogo = \Illuminate\Support\Facades\DB::table('slug')
          ->where('object_id', $io->repository_id)
          ->value('slug');
    @endphp
    @if($repoLogo)
      <div class="repository-logo mb-3 mx-auto">
        <a class="text-decoration-none" href="{{ url('/repository/' . $repoLogo) }}">
          <img alt="Go to repository" class="img-fluid img-thumbnail border-4 shadow-sm bg-white"
               src="/uploads/r/{{ $repoLogo }}/conf/logo.png"
               onerror="this.parentElement.style.display='none'">
        </a>
      </div>
    @endif
  @endif
@endsection

@section('content')
  <h1>Item {{ $io->identifier ? $io->identifier . ' - ' : '' }}{{ $io->title ?? '[Untitled]' }}</h1>

  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
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

  <form method="POST" action="{{ route('informationobject.update', $io->slug) }}" id="editForm">
    @csrf
    @method('PUT')

    <div class="accordion mb-3">

      {{-- ===== Identity area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="identity-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#identity-collapse" aria-expanded="false" aria-controls="identity-collapse">
            Identity area
          </button>
        </h2>
        <div id="identity-collapse" class="accordion-collapse collapse" aria-labelledby="identity-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="identifier" class="form-label">Identifier</label>
              <input type="text" class="form-control" id="identifier" name="identifier"
                     value="{{ old('identifier', $io->identifier) }}">
            </div>

            <div class="mb-3">
              <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title"
                     value="{{ old('title', $io->title) }}" required>
              @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label for="level_of_description_id" class="form-label">Level of description</label>
              <select class="form-select" id="level_of_description_id" name="level_of_description_id">
                <option value="">-- Select --</option>
                @foreach($levels as $level)
                  <option value="{{ $level->id }}" @selected(old('level_of_description_id', $io->level_of_description_id) == $level->id)>
                    {{ $level->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="extent_and_medium" class="form-label">Extent and medium</label>
              <textarea class="form-control" id="extent_and_medium" name="extent_and_medium" rows="3">{{ old('extent_and_medium', $io->extent_and_medium) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Context area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="context-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#context-collapse" aria-expanded="false" aria-controls="context-collapse">
            Context area
          </button>
        </h2>
        <div id="context-collapse" class="accordion-collapse collapse" aria-labelledby="context-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="creators" class="form-label">Creator(s)</label>
              <input type="text" class="form-control" id="creators" name="creators" value="{{ old('creators', $io->creators ?? '') }}" placeholder="Type to search authority records...">
              <div class="form-text">Link to existing authority records as creators</div>
            </div>

            <div class="mb-3">
              <label for="repository_id" class="form-label">Repository</label>
              <select class="form-select" id="repository_id" name="repository_id">
                <option value="">-- Select --</option>
                @foreach($repositories as $repo)
                  <option value="{{ $repo->id }}" @selected(old('repository_id', $io->repository_id) == $repo->id)>
                    {{ $repo->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="archival_history" class="form-label">Archival history</label>
              <textarea class="form-control" id="archival_history" name="archival_history" rows="3">{{ old('archival_history', $io->archival_history) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="acquisition" class="form-label">Immediate source of acquisition or transfer</label>
              <textarea class="form-control" id="acquisition" name="acquisition" rows="3">{{ old('acquisition', $io->acquisition) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Content and structure area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="content-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#content-collapse" aria-expanded="false" aria-controls="content-collapse">
            Content and structure area
          </button>
        </h2>
        <div id="content-collapse" class="accordion-collapse collapse" aria-labelledby="content-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="scope_and_content" class="form-label">Scope and content</label>
              <textarea class="form-control" id="scope_and_content" name="scope_and_content" rows="4">{{ old('scope_and_content', $io->scope_and_content) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="appraisal" class="form-label">Appraisal, destruction and scheduling information</label>
              <textarea class="form-control" id="appraisal" name="appraisal" rows="3">{{ old('appraisal', $io->appraisal) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="accruals" class="form-label">Accruals</label>
              <textarea class="form-control" id="accruals" name="accruals" rows="3">{{ old('accruals', $io->accruals) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="arrangement" class="form-label">System of arrangement</label>
              <textarea class="form-control" id="arrangement" name="arrangement" rows="3">{{ old('arrangement', $io->arrangement) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Conditions of access and use area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="conditions-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#conditions-collapse" aria-expanded="false" aria-controls="conditions-collapse">
            Conditions of access and use area
          </button>
        </h2>
        <div id="conditions-collapse" class="accordion-collapse collapse" aria-labelledby="conditions-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="access_conditions" class="form-label">Conditions governing access</label>
              <textarea class="form-control" id="access_conditions" name="access_conditions" rows="3">{{ old('access_conditions', $io->access_conditions) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="reproduction_conditions" class="form-label">Conditions governing reproduction</label>
              <textarea class="form-control" id="reproduction_conditions" name="reproduction_conditions" rows="3">{{ old('reproduction_conditions', $io->reproduction_conditions) }}</textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Languages of the material</label>
              <input type="text" class="form-control" name="language_of_material" value="{{ old('language_of_material', $io->language_of_material ?? '') }}" placeholder="e.g. English, Afrikaans">
            </div>

            <div class="mb-3">
              <label class="form-label">Scripts of the material</label>
              <input type="text" class="form-control" name="script_of_material" value="{{ old('script_of_material', $io->script_of_material ?? '') }}" placeholder="e.g. Latin">
            </div>

            <div class="mb-3">
              <label for="language_notes" class="form-label">Language and script notes</label>
              <textarea class="form-control" id="language_notes" name="language_notes" rows="2">{{ old('language_notes', $io->language_notes ?? '') }}</textarea>
            </div>

            <div class="mb-3">
              <label for="physical_characteristics" class="form-label">Physical characteristics and technical requirements</label>
              <textarea class="form-control" id="physical_characteristics" name="physical_characteristics" rows="3">{{ old('physical_characteristics', $io->physical_characteristics) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="finding_aids" class="form-label">Finding aids</label>
              <textarea class="form-control" id="finding_aids" name="finding_aids" rows="3">{{ old('finding_aids', $io->finding_aids) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Allied materials area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="allied-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#allied-collapse" aria-expanded="false" aria-controls="allied-collapse">
            Allied materials area
          </button>
        </h2>
        <div id="allied-collapse" class="accordion-collapse collapse" aria-labelledby="allied-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="location_of_originals" class="form-label">Existence and location of originals</label>
              <textarea class="form-control" id="location_of_originals" name="location_of_originals" rows="3">{{ old('location_of_originals', $io->location_of_originals) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="location_of_copies" class="form-label">Existence and location of copies</label>
              <textarea class="form-control" id="location_of_copies" name="location_of_copies" rows="3">{{ old('location_of_copies', $io->location_of_copies) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="related_units_of_description" class="form-label">Related units of description</label>
              <textarea class="form-control" id="related_units_of_description" name="related_units_of_description" rows="3">{{ old('related_units_of_description', $io->related_units_of_description) }}</textarea>
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Notes area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="notes-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#notes-collapse" aria-expanded="false" aria-controls="notes-collapse">
            Notes area
          </button>
        </h2>
        <div id="notes-collapse" class="accordion-collapse collapse" aria-labelledby="notes-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <div class="table-responsive mb-2">
                <table class="table table-bordered mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>Type</th>
                      <th>Content</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      {{-- ===== Access points ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="access-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#access-collapse" aria-expanded="false" aria-controls="access-collapse">
            Access points
          </button>
        </h2>
        <div id="access-collapse" class="accordion-collapse collapse" aria-labelledby="access-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label class="form-label">Subject access points</label>
              <input type="text" class="form-control" name="subject_access_points" value="{{ old('subject_access_points', $io->subject_access_points ?? '') }}" placeholder="Type to search subjects...">
            </div>

            <div class="mb-3">
              <label class="form-label">Place access points</label>
              <input type="text" class="form-control" name="place_access_points" value="{{ old('place_access_points', $io->place_access_points ?? '') }}" placeholder="Type to search places...">
            </div>

            <div class="mb-3">
              <label class="form-label">Genre access points</label>
              <input type="text" class="form-control" name="genre_access_points" value="{{ old('genre_access_points', $io->genre_access_points ?? '') }}" placeholder="Type to search genres...">
            </div>

          </div>
        </div>
      </div>

      {{-- ===== Description control area ===== --}}
      <div class="accordion-item">
        <h2 class="accordion-header" id="description-heading">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#description-collapse" aria-expanded="false" aria-controls="description-collapse">
            Description control area
          </button>
        </h2>
        <div id="description-collapse" class="accordion-collapse collapse" aria-labelledby="description-heading">
          <div class="accordion-body">

            <div class="mb-3">
              <label for="description_identifier" class="form-label">Description identifier</label>
              <input type="text" class="form-control" id="description_identifier" name="description_identifier"
                     value="{{ old('description_identifier', $io->description_identifier) }}">
            </div>

            <div class="mb-3">
              <label for="institution_responsible_identifier" class="form-label">Institution identifier</label>
              <textarea class="form-control" id="institution_responsible_identifier" name="institution_responsible_identifier" rows="2">{{ old('institution_responsible_identifier', $io->institution_responsible_identifier) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="rules" class="form-label">Rules or conventions</label>
              <textarea class="form-control" id="rules" name="rules" rows="3">{{ old('rules', $io->rules) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="description_status_id" class="form-label">Status</label>
              <select class="form-select" id="description_status_id" name="description_status_id">
                <option value="">-- Select --</option>
                @foreach($descriptionStatuses as $status)
                  <option value="{{ $status->id }}" @selected(old('description_status_id', $io->description_status_id) == $status->id)>
                    {{ $status->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="description_detail_id" class="form-label">Level of detail</label>
              <select class="form-select" id="description_detail_id" name="description_detail_id">
                <option value="">-- Select --</option>
                @foreach($descriptionDetails as $detail)
                  <option value="{{ $detail->id }}" @selected(old('description_detail_id', $io->description_detail_id) == $detail->id)>
                    {{ $detail->name }}
                  </option>
                @endforeach
              </select>
            </div>

            <div class="mb-3">
              <label for="revision_history" class="form-label">Dates of creation, revision and deletion</label>
              <textarea class="form-control" id="revision_history" name="revision_history" rows="3">{{ old('revision_history', $io->revision_history) }}</textarea>
            </div>

            <div class="mb-3">
              <label class="form-label">Language(s)</label>
              <input type="text" class="form-control" name="language_of_description" value="{{ old('language_of_description', $io->language_of_description ?? '') }}" placeholder="e.g. English">
            </div>

            <div class="mb-3">
              <label class="form-label">Script(s)</label>
              <input type="text" class="form-control" name="script_of_description" value="{{ old('script_of_description', $io->script_of_description ?? '') }}" placeholder="e.g. Latin">
            </div>

            <div class="mb-3">
              <label for="sources" class="form-label">Sources</label>
              <textarea class="form-control" id="sources" name="sources" rows="3">{{ old('sources', $io->sources) }}</textarea>
            </div>

            <div class="mb-3">
              <label for="publication_status_id" class="form-label">Publication status</label>
              <select class="form-select" id="publication_status_id" name="publication_status_id">
                <option value="159" @selected(old('publication_status_id', $io->publication_status_id ?? '') == '159')>Draft</option>
                <option value="160" @selected(old('publication_status_id', $io->publication_status_id ?? '') == '160')>Published</option>
              </select>
            </div>

            <div class="mb-3">
              <label for="display_standard_id" class="form-label">Display standard</label>
              <select class="form-select" id="display_standard_id" name="display_standard_id">
                <option value="">-- Select --</option>
                @foreach($displayStandards as $std)
                  <option value="{{ $std->id }}" @selected(old('display_standard_id', $io->display_standard_id) == $std->id)>
                    {{ $std->name }}
                  </option>
                @endforeach
              </select>
            </div>

          </div>
        </div>
      </div>

    </div>

    {{-- ===== Security Classification ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="security-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#security-collapse" aria-expanded="false">
          Security Classification
        </button>
      </h2>
      <div id="security-collapse" class="accordion-collapse collapse" aria-labelledby="security-heading">
        <div class="accordion-body">
          <div class="mb-3">
            <label for="security_classification_id" class="form-label">Classification level</label>
            <select name="security_classification_id" id="security_classification_id" class="form-select">
              <option value="">-- None --</option>
              @foreach($formChoices['securityLevels'] ?? [] as $level)
                <option value="{{ $level->id }}" @selected(old('security_classification_id', $io->security_classification_id ?? '') == $level->id)>{{ $level->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label for="security_reason" class="form-label">Reason</label>
            <textarea name="security_reason" id="security_reason" class="form-control" rows="2">{{ old('security_reason', $io->security_reason ?? '') }}</textarea>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="security_review_date" class="form-label">Review date</label>
              <input type="date" name="security_review_date" id="security_review_date" class="form-control" value="{{ old('security_review_date', $io->security_review_date ?? '') }}">
            </div>
            <div class="col-md-6 mb-3">
              <label for="security_declassify_date" class="form-label">Declassify date</label>
              <input type="date" name="security_declassify_date" id="security_declassify_date" class="form-control" value="{{ old('security_declassify_date', $io->security_declassify_date ?? '') }}">
            </div>
          </div>
          <div class="mb-3">
            <label for="security_handling_instructions" class="form-label">Handling instructions</label>
            <textarea name="security_handling_instructions" id="security_handling_instructions" class="form-control" rows="2">{{ old('security_handling_instructions', $io->security_handling_instructions ?? '') }}</textarea>
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" name="security_inherit_to_children" id="security_inherit_to_children" value="1" {{ old('security_inherit_to_children', $io->security_inherit_to_children ?? '') ? 'checked' : '' }}>
            <label class="form-check-label" for="security_inherit_to_children">Apply classification to children</label>
          </div>
        </div>
      </div>
    </div>

    {{-- ===== Watermark Settings ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="watermark-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#watermark-collapse" aria-expanded="false">
          Watermark Settings
        </button>
      </h2>
      <div id="watermark-collapse" class="accordion-collapse collapse" aria-labelledby="watermark-heading">
        <div class="accordion-body">
          <p class="text-muted">Watermark settings are managed via the digital object interface.</p>
        </div>
      </div>
    </div>

    {{-- ===== Administration area ===== --}}
    <div class="accordion-item">
      <h2 class="accordion-header" id="admin-heading">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#admin-collapse" aria-expanded="false">
          Administration area
        </button>
      </h2>
      <div id="admin-collapse" class="accordion-collapse collapse" aria-labelledby="admin-heading">
        <div class="accordion-body">
          <div class="mb-3">
            <label for="publication_status_id" class="form-label">Publication status</label>
            <select name="publication_status_id" id="publication_status_id" class="form-select">
              <option value="159" @selected(old('publication_status_id', $io->publication_status_id ?? '') == 159)>Draft</option>
              <option value="160" @selected(old('publication_status_id', $io->publication_status_id ?? '') == 160)>Published</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="display_standard_id" class="form-label">Display standard</label>
            <select name="display_standard_id" id="display_standard_id" class="form-select">
              @foreach($formChoices['displayStandards'] ?? [] as $dsId => $dsName)
                <option value="{{ $dsId }}" @selected(old('display_standard_id', $io->display_standard_id ?? '') == $dsId)>{{ $dsName }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>
    </div>

    </div>{{-- end main accordion --}}

    {{-- ===== Form actions ===== --}}
    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        <li><a href="{{ route('informationobject.show', $io->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
        <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
      </ul>
    </section>
  </form>

  {{-- ===== Digital object upload/manage ===== --}}
  <div class="accordion mb-4" id="digitalObjectAccordion">
    @include('ahg-io-manage::partials._upload-form')
  </div>
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
