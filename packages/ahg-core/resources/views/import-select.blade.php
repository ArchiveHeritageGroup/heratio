@php decorate_with('layout_1col.php'); @endphp

@php slot('title'); @endphp
  @if(isset($resource))
    <div class="multiline-header d-flex flex-column mb-3">
      <h1 class="mb-0" aria-describedby="heading-label">
        @php echo $title; @endphp
      </h1>
      <span class="small" id="heading-label">
        @php echo render_title($resource); @endphp
      </span>
    </div>
  @php } else { @endphp
    <h1>@php echo $title; @endphp</h1>
  @endforeach
@php end_slot(); @endphp

@php slot('content'); @endphp

  @php echo $form->renderGlobalErrors(); @endphp

  @if(isset($resource))
    @php echo $form->renderFormTag(url_for([$resource, 'module' => 'object', 'action' => 'importSelect']), ['enctype' => 'multipart/form-data']); @endphp
  @php } else { @endphp
    @php echo $form->renderFormTag(route('object.importSelect'), ['enctype' => 'multipart/form-data']); @endphp
  @endforeach

    @php echo $form->renderHiddenFields(); @endphp

    <div class="accordion mb-3">
      <div class="accordion-item">
        <h2 class="accordion-header" id="import-heading">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#import-collapse" aria-expanded="true" aria-controls="import-collapse">
            {{ __('Import options') }}
          </button>
        </h2>
        <div id="import-collapse" class="accordion-collapse collapse show" aria-labelledby="import-heading">
          <div class="accordion-body">
            <input type="hidden" name="importType" value="@php echo esc_entities($type); @endphp"/>

            @if('csv' == $type)
              <div class="mb-3">
                <label class="form-label" for="object-type-select">{{ __('Type') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="informationObject">@php echo sfConfig::get('app_ui_label_informationobject'); @endphp</option>
                  <option value="accession">@php echo sfConfig::get('app_ui_label_accession', __('Accession')); @endphp</option>
                  <option value="authorityRecord">@php echo sfConfig::get('app_ui_label_actor'); @endphp</option>
                  <option value="authorityRecordRelationship">@php echo sfConfig::get('app_ui_label_authority_record_relationships'); @endphp</option>
                  <option value="event">@php echo sfConfig::get('app_ui_label_event', __('Event')); @endphp</option>
                  <option value="repository">@php echo sfConfig::get('app_ui_label_repository', __('Repository')); @endphp</option>
                </select>
              </div>
            @endforeach

            @if('csv' != $type)
              <p class="alert alert-info text-center">{{ __('If you are importing a SKOS file to a taxonomy other than subjects, please go to the %1%', ['%1%' => link_to(__('SKOS import page'), ['module' => 'sfSkosPlugin', 'action' => 'import'], ['class' => 'alert-link'])]) }}</p>
              <div class="mb-3">
                <label for="object-type-select" class="form-label">{{ __('Type') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                <select class="form-select" name="objectType" id="object-type-select">
                  <option value="ead">{{ __('EAD 2002') }}</option>
                  <option value="eac-cpf">{{ __('EAC CPF') }}</option>
                  <option value="mods">{{ __('MODS') }}</option>
                  <option value="dc">{{ __('DC') }}</option>
                </select>
              </div>
            @endforeach

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
              @endforeach

              @if('csv' != $type)
                <div class="mb-3">
                  <label class="form-label" for="update-type-select">{{ __('Update behaviours') }} <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select class="form-select" name="updateType" id="update-type-select">
                    <option value="import-as-new">{{ __('Ignore matches and import as new') }}</option>
                    <option value="delete-and-replace">{{ __('Delete matches and replace with imports') }}</option>
                  </select>
                </div>
              @endforeach

	      <div class="form-item">
                <div class="panel panel-default" id="matchingOptions" style="display:none;">
                  <div class="panel-body">
                    <div class="mb-3 form-check">
                      <input class="form-check-input" name="skipUnmatched" id="skip-unmatched-input" type="checkbox"/>
                      <label class="form-check-label" for="skip-unmatched-input">{{ __('Skip unmatched records') }}</label>
                    </div>

                    <div class="criteria">
                      <div class="repos-limit">
                        @php echo render_field($form->repos->label(__('Limit matches to:'))); @endphp
                      </div>

                      <div class="collection-limit">
                        @php echo render_field(
                          $form->collection->label(__('Top-level description')),
                          null,
                          [
                              'class' => 'form-autocomplete',
                              'extraInputs' => '<input class="list" type="hidden" value="'
                                  .url_for([
                                      'module' => 'informationobject',
                                      'action' => 'autocomplete',
                                      'parent' => QubitInformationObject::ROOT_ID,
                                      'filterDrafts' => true,
                                  ])
                                  .'">',
                          ]
                        ); @endphp
                      </div>
                    </div>
                  </div>
                </div>

                <div class="panel panel-default" id="importAsNewOptions">
                  <div class="panel-body">
                    <div class="mb-3 form-check">
                      <input class="form-check-input" name="skipMatched" id="skip-matched-input" type="checkbox"/>
                      <label class="form-check-label" for="skip-matched-input">{{ __('Skip matched records') }}</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mb-3 form-check" id="noIndex">
              <input class="form-check-input" name="noIndex" id="no-index-input" type="checkbox"/>
              <label class="form-check-label" for="no-index-input">{{ __('Do not index imported items') }}</label>
            </div>

            @if('csv' == $type && sfConfig::get('app_csv_transform_script_name'))
              <div class="mb-3 form-check">
                <input class="form-check-input" name="doCsvTransform" id="do-csv-transform-input" type="checkbox"/>
                <label class="form-check-label" for="do-csv-transform-input" aria-described-by="do-csv-transform-help">{{ __('Include transformation script') }}</label>
                <div class="form-text" id="do-csv-transform-help">{{ __(sfConfig::get('app_csv_transform_script_name')) }}</div>
              </div>
            @endforeach

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

@php end_slot(); @endphp
