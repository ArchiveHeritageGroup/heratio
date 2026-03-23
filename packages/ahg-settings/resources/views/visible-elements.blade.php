@extends('theme::layouts.1col')
@section('title', 'Visible elements')
@section('body-class', 'admin settings')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-settings::_menu')</div>
  <div class="col-md-9">
    <h1>Visible elements</h1>

    <form method="post" action="{{ route('settings.visible-elements') }}">
      @csrf

      <div class="accordion mb-3" id="visElementsAccordion">
        @php
          // AtoM exact accordion group definitions
          $atomGroups = [
            'global' => [
              'label' => 'Global',
              'fields' => [
                'global_login_button' => 'Login button',
              ],
            ],
            'isad_headings' => [
              'label' => 'ISAD template - area headings',
              'fields' => [
                'isad_identity_area' => 'Identity area',
                'isad_context_area' => 'Context area',
                'isad_content_and_structure_area' => 'Content and structure area',
                'isad_conditions_of_access_use_area' => 'Conditions of access and use area',
                'isad_allied_materials_area' => 'Allied materials area',
                'isad_notes_area' => 'Notes area',
                'isad_access_points_area' => 'Access points',
                'isad_description_control_area' => 'Description control area',
              ],
            ],
            'isad_elements' => [
              'label' => 'ISAD template - elements',
              'fields' => [
                'isad_archival_history' => 'Archival history',
                'isad_immediate_source' => 'Immediate source of acquisition or transfer',
                'isad_appraisal_destruction' => 'Appraisal, destruction and scheduling information',
                'isad_notes' => 'Notes',
                'isad_physical_condition' => 'Physical characteristics and technical requirements',
                'isad_control_description_identifier' => 'Description identifier',
                'isad_control_institution_identifier' => 'Institution identifier',
                'isad_control_rules_conventions' => 'Rules or conventions',
                'isad_control_status' => 'Status',
                'isad_control_level_of_detail' => 'Level of detail',
                'isad_control_dates' => 'Dates of creation, revision and deletion',
                'isad_control_languages' => 'Language(s)',
                'isad_control_scripts' => 'Script(s)',
                'isad_control_sources' => 'Sources',
                'isad_control_archivists_notes' => "Archivist's notes",
              ],
            ],
            'rad_headings' => [
              'label' => 'RAD template - area headings',
              'fields' => [
                'rad_title_responsibility_area' => 'Title and statement of responsibility area',
                'rad_edition_area' => 'Edition area',
                'rad_material_specific_details_area' => 'Class of material specific details area',
                'rad_dates_of_creation_area' => 'Dates of creation area',
                'rad_physical_description_area' => 'Physical description area',
                'rad_publishers_series_area' => "Publisher's series area",
                'rad_archival_description_area' => 'Archival description area',
                'rad_notes_area' => 'Notes area',
                'rad_standard_number_area' => 'Standard number area',
                'rad_access_points_area' => 'Access points',
                'rad_description_control_area' => 'Control area',
              ],
            ],
            'rad_elements' => [
              'label' => 'RAD template - elements',
              'fields' => [
                'rad_archival_history' => 'Custodial history',
                'rad_physical_condition' => 'Physical condition',
                'rad_immediate_source' => 'Immediate source of acquisition',
                'rad_general_notes' => 'General note(s)',
                'rad_conservation_notes' => 'Conservation note(s)',
                'rad_rights_notes' => 'Rights note(s)',
                'rad_control_description_identifier' => 'Description identifier',
                'rad_control_institution_identifier' => 'Institution identifier',
                'rad_control_rules_conventions' => 'Rules or conventions',
                'rad_control_status' => 'Status',
                'rad_control_level_of_detail' => 'Level of detail',
                'rad_control_dates' => 'Dates of creation, revision and deletion',
                'rad_control_language' => 'Language',
                'rad_control_script' => 'Script',
                'rad_control_sources' => 'Sources',
              ],
            ],
            'dacs_headings' => [
              'label' => 'DACS template - area headings',
              'fields' => [
                'dacs_identity_area' => 'Identity area',
                'dacs_content_area' => 'Content and structure area',
                'dacs_conditions_of_access_area' => 'Conditions of access and use area',
                'dacs_acquisition_area' => 'Acquisition and appraisal area',
                'dacs_materials_area' => 'Related materials area',
                'dacs_notes_area' => 'Notes area',
                'dacs_control_area' => 'Description control area',
                'dacs_access_points_area' => 'Access points',
              ],
            ],
            'dacs_elements' => [
              'label' => 'DACS template - elements',
              'fields' => [
                'dacs_physical_access' => 'Physical access',
              ],
            ],
            'do_original' => [
              'label' => 'Digital object metadata - original file',
              'fields' => [
                'digital_object_preservation_system_original_file_name' => 'File name',
                'digital_object_preservation_system_original_format_name' => 'Format name',
                'digital_object_preservation_system_original_format_version' => 'Format version',
                'digital_object_preservation_system_original_format_registry_key' => 'Format registry key',
                'digital_object_preservation_system_original_format_registry_name' => 'Format registry name',
                'digital_object_preservation_system_original_file_size' => 'File size',
                'digital_object_preservation_system_original_ingested' => 'Ingested',
                'digital_object_preservation_system_original_permissions' => 'Permissions',
              ],
            ],
            'do_preservation' => [
              'label' => 'Digital object metadata - preservation copy',
              'fields' => [
                'digital_object_preservation_system_preservation_file_name' => 'File name',
                'digital_object_preservation_system_preservation_file_size' => 'File size',
                'digital_object_preservation_system_preservation_normalized' => 'Normalized',
                'digital_object_preservation_system_preservation_permissions' => 'Permissions',
              ],
            ],
            'do_master' => [
              'label' => 'Digital object metadata - master file',
              'fields' => [
                'digital_object_url' => 'URL',
                'digital_object_file_name' => 'File name',
                'digital_object_geolocation' => 'Latitude and longitude',
                'digital_object_media_type' => 'Media type',
                'digital_object_mime_type' => 'MIME type',
                'digital_object_file_size' => 'File size',
                'digital_object_uploaded' => 'Uploaded',
                'digital_object_permissions' => 'Permissions',
              ],
            ],
            'do_reference' => [
              'label' => 'Digital object metadata - reference copy',
              'fields' => [
                'digital_object_reference_file_name' => 'File name',
                'digital_object_reference_media_type' => 'Media type',
                'digital_object_reference_mime_type' => 'MIME type',
                'digital_object_reference_file_size' => 'File size',
                'digital_object_reference_uploaded' => 'Uploaded',
                'digital_object_reference_permissions' => 'Permissions',
              ],
            ],
            'do_thumbnail' => [
              'label' => 'Digital object metadata - thumbnail copy',
              'fields' => [
                'digital_object_thumbnail_file_name' => 'File name',
                'digital_object_thumbnail_media_type' => 'Media type',
                'digital_object_thumbnail_mime_type' => 'MIME type',
                'digital_object_thumbnail_file_size' => 'File size',
                'digital_object_thumbnail_uploaded' => 'Uploaded',
                'digital_object_thumbnail_permissions' => 'Permissions',
              ],
            ],
            'physical_storage' => [
              'label' => 'Physical storage',
              'fields' => [
                'physical_storage' => 'Physical storage',
              ],
            ],
          ];

          // Build a lookup from setting name -> setting object
          $settingsByName = collect();
          foreach($groups as $prefix => $groupSettings) {
            foreach($groupSettings as $setting) {
              $settingsByName[$setting->name] = $setting;
            }
          }
          $idx = 0;
        @endphp

        @foreach($atomGroups as $groupKey => $group)
          @php $idx++; @endphp
          <div class="accordion-item">
            <h2 class="accordion-header" id="heading-{{ $groupKey }}">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-{{ $groupKey }}" aria-expanded="false" aria-controls="collapse-{{ $groupKey }}">
                {{ $group['label'] }}
              </button>
            </h2>
            <div id="collapse-{{ $groupKey }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $groupKey }}">
              <div class="accordion-body">
                @foreach($group['fields'] as $fieldName => $fieldLabel)
                  @php $setting = $settingsByName[$fieldName] ?? null; @endphp
                  @if($setting)
                    <div class="form-check mb-2">
                      <input class="form-check-input" type="checkbox" name="settings[{{ $setting->id }}]" value="1" id="ve_{{ $setting->id }}" {{ ($setting->value ?? '0') == '1' ? 'checked' : '' }}>
                      <label class="form-check-label" for="ve_{{ $setting->id }}">{{ $fieldLabel }}</label>
                    </div>
                  @endif
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
      </div>

      <section class="actions mb-3">
        <input class="btn atom-btn-outline-success" type="submit" value="Save">
      </section>
    </form>
  </div>
</div>
@endsection
