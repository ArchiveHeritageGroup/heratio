@extends('theme::layouts.1col')

@section('title')
  @if(isset($resource))
    <div class="multiline-header d-flex flex-column mb-3">
      <h1 class="mb-0" aria-describedby="heading-label">
        {{ $title }}
      </h1>
      <span class="small" id="heading-label">
        {{ $resource->authorized_form_of_name ?? $resource->title ?? '' }}
      </span>
    </div>
  @else
    <h1>{{ $title }}</h1>
  @endif
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

  @if(isset($resource))
    <form method="POST" action="{{ route('informationobject.import.process') }}" enctype="multipart/form-data">
  @else
    <form method="POST" action="{{ route('informationobject.import.process') }}" enctype="multipart/form-data">
  @endif

    @csrf

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="import-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#import-collapse" aria-expanded="true" aria-controls="import-collapse">
            {{ __('Import options') }}
          </button>
        </h2>
        <div id="import-collapse" class="accordion-collapse collapse show" aria-labelledby="import-heading">
          <div class="accordion-body">
            <input type="hidden" name="importType" value="{{ e($type) }}"/>

            @if('csv' == $type)
              <div class="mb-3">
                <label class="form-label" for="object-type-select">{{ __('Type') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="informationObject">{{ config('app.ui_label_informationobject', __('Archival description')) }}</option>
                  <option value="accession">{{ config('app.ui_label_accession', __('Accession')) }}</option>
                  <option value="authorityRecord">{{ config('app.ui_label_actor', __('Authority record')) }}</option>
                  <option value="authorityRecordRelationship">{{ config('app.ui_label_authority_record_relationships', __('Authority record relationships')) }}</option>
                  <option value="event">{{ config('app.ui_label_event', __('Event')) }}</option>
                  <option value="repository">{{ config('app.ui_label_repository', __('Repository')) }}</option>
                </select>
              </div>
            @endif

            @if('csv' != $type)
              <p class="alert alert-info text-center">{{ __('If you are importing a SKOS file to a taxonomy other than subjects, please go to the %1%', ['%1%' => '<a class="alert-link" href="/sfSkosPlugin/import">' . __('SKOS import page') . '</a>']) }}</p>
              <div class="mb-3">
                <label for="object-type-select" class="form-label">{{ __('Type') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="ead">{{ __('EAD 2002') }}</option>
                  <option value="eac-cpf">{{ __('EAC CPF') }}</option>
                  <option value="mods">{{ __('MODS') }}</option>
                  <option value="dc">{{ __('DC') }}</option>
                </select>
              </div>
            @endif

            <div id="updateBlock">

              @if('csv' == $type)
                <div class="mb-3">
                  <label class="form-label" for="update-type-select">{{ __('Update behaviours') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="updateType" id="update-type-select">
                    <option value="import-as-new">{{ __('Ignore matches and create new records on import') }}</option>
                    <option value="match-and-update">{{ __('Update matches ignoring blank fields in CSV') }}</option>
                    <option value="delete-and-replace">{{ __('Delete matches and replace with imported records') }}</option>
                  </select>
                </div>
              @endif

              @if('csv' != $type)
                <div class="mb-3">
                  <label class="form-label" for="update-type-select">{{ __('Update behaviours') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="updateType" id="update-type-select">
                    <option value="import-as-new">{{ __('Ignore matches and import as new') }}</option>
                    <option value="delete-and-replace">{{ __('Delete matches and replace with imports') }}</option>
                  </select>
                </div>
              @endif

	      <div class="form-item">
                <div class="panel panel-default" id="matchingOptions" style="display:none;">
                  <div class="panel-body">
                    <div class="mb-3 form-check">
                      <input class="form-check-input" name="skipUnmatched" id="skip-unmatched-input" type="checkbox"/>
                      <label class="form-check-label" for="skip-unmatched-input">{{ __('Skip unmatched records') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>

                    <div class="criteria">
                      <div class="repos-limit">
                        <div class="mb-3">
                          <label class="form-label" for="repos">{{ __('Limit matches to:') }}</label>
                          <select class="form-select" name="repos" id="repos">
                            @foreach($repositories ?? [] as $repoId => $repoName)
                              <option value="{{ $repoId }}">{{ $repoName }}</option>
                            @endforeach
                          </select>
                        </div>
                      </div>

                      <div class="collection-limit">
                        <div class="mb-3">
                          <label class="form-label" for="collection">{{ __('Top-level description') }}</label>
                          <input class="form-control form-autocomplete" type="text" name="collection" id="collection" value="">
                          <input class="list" type="hidden" value="{{ route('informationobject.autocomplete') }}?parent=1&filterDrafts=true">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="panel panel-default" id="importAsNewOptions">
                  <div class="panel-body">
                    <div class="mb-3 form-check">
                      <input class="form-check-input" name="skipMatched" id="skip-matched-input" type="checkbox"/>
                      <label class="form-check-label" for="skip-matched-input">{{ __('Skip matched records') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-3 form-check" id="noIndex">
              <input class="form-check-input" name="noIndex" id="no-index-input" type="checkbox"/>
              <label class="form-check-label" for="no-index-input">{{ __('Do not index imported items') }} <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>

            @if('csv' == $type && config('app.csv_transform_script_name'))
              <div class="mb-3 form-check">
                <input class="form-check-input" name="doCsvTransform" id="do-csv-transform-input" type="checkbox"/>
                <label class="form-check-label" for="do-csv-transform-input" aria-described-by="do-csv-transform-help">{{ __('Include transformation script') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="form-text" id="do-csv-transform-help">{{ __(config('app.csv_transform_script_name')) }}</div>
              </div>
            @endif

          </div>
        </div>
      </div>
      <div class="accordion-item">
        <h2 class="accordion-header" id="file-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#file-collapse" aria-expanded="true" aria-controls="file-collapse">
            {{ __('Select file') }}
          </button>
        </h2>
        <div id="file-collapse" class="accordion-collapse collapse show" aria-labelledby="file-heading">
          <div class="accordion-body">
            <div class="mb-3">
              <label for="import-file" class="form-label">{{ __('Select a file to import') }} <span class="badge bg-secondary ms-1">Optional</span></label>
              <input class="form-control" type="file" id="import-file" name="file">
            </div>
          </div>
        </div>
      </div>
    </div>

    <section class="actions mb-3">
      <input class="btn atom-btn-outline-success" type="submit" value="{{ __('Import') }}">
    </section>

  </form>

@endsection
