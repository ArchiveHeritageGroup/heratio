{{--
  Metadata Extraction — auto-extraction and field mapping settings
  Cloned from AtoM ahgSettingsPlugin section.blade.php @case('metadata')

  @copyright  Johan Pieterse / Plain Sailing
  @license    AGPL-3.0-or-later
--}}
@extends('theme::layouts.2col')
@section('title', 'Metadata Extraction')
@section('body-class', 'admin settings')

@section('sidebar')
  @include('ahg-settings::_menu', ['menu' => $menu ?? []])
@endsection

@section('title-block')
<h1><i class="fas fa-tags me-2"></i>Metadata Extraction</h1>
<p class="text-muted">Automatic metadata extraction from uploaded files</p>
@endsection

@section('content')
  @if(session('notice'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('notice') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  <form method="POST" action="{{ route('settings.ahg.metadata') }}">
    @csrf

    {{-- Metadata Extraction --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Metadata Extraction</h5>
      </div>
      <div class="card-body">
        <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i> Configure automatic metadata extraction.</div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Extract on Upload') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="meta_extract_on_upload"
                     name="settings[meta_extract_on_upload]" value="true"
                     {{ ($settings['meta_extract_on_upload'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_extract_on_upload">{{ __('Auto-extract metadata') }}</label>
            </div>
          </div>
        </div>
        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">{{ __('Auto-Populate') }}</label>
          <div class="col-sm-9">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" id="meta_auto_populate"
                     name="settings[meta_auto_populate]" value="true"
                     {{ ($settings['meta_auto_populate'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_auto_populate">{{ __('Populate description fields') }}</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- File Types --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-file me-2"></i>File Types</h5>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="meta_images" name="settings[meta_images]" value="true"
                     {{ ($settings['meta_images'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_images"><i class="fas fa-image text-success me-1"></i> Images</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="meta_pdf" name="settings[meta_pdf]" value="true"
                     {{ ($settings['meta_pdf'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_pdf"><i class="fas fa-file-pdf text-danger me-1"></i> PDF</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="meta_office" name="settings[meta_office]" value="true"
                     {{ ($settings['meta_office'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_office"><i class="fas fa-file-word text-primary me-1"></i> Office</label>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="meta_video" name="settings[meta_video]" value="true"
                     {{ ($settings['meta_video'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_video"><i class="fas fa-video text-info me-1"></i> Video</label>
            </div>
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" id="meta_audio" name="settings[meta_audio]" value="true"
                     {{ ($settings['meta_audio'] ?? 'true') === 'true' ? 'checked' : '' }}>
              <label class="form-check-label" for="meta_audio"><i class="fas fa-music text-warning me-1"></i> Audio</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- Field Mapping --}}
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-random me-2"></i>Field Mapping</h5>
      </div>
      <div class="card-body">
        <p class="text-muted">Configure where extracted metadata is saved:</p>
        <div class="table-responsive">
          <table class="table table-sm table-bordered">
            <thead class="table-dark">
              <tr>
                <th style="width:20%">{{ __('Metadata Source') }}</th>
                <th style="width:26%">{{ __('Archives (ISAD)') }}</th>
                <th style="width:27%">{{ __('Museum (Spectrum)') }}</th>
                <th style="width:27%">{{ __('DAM') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><i class="fas fa-heading text-muted me-1"></i> Title</td>
                <td><select class="form-select form-select-sm" name="settings[map_title_isad]">
                  <option value="title" {{ ($settings['map_title_isad'] ?? 'title') === 'title' ? 'selected' : '' }}>{{ __('Title') }}</option>
                  <option value="alternateTitle" {{ ($settings['map_title_isad'] ?? '') === 'alternateTitle' ? 'selected' : '' }}>{{ __('Alternate Title') }}</option>
                  <option value="none" {{ ($settings['map_title_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_title_museum]">
                  <option value="objectName" {{ ($settings['map_title_museum'] ?? 'objectName') === 'objectName' ? 'selected' : '' }}>{{ __('Object Name') }}</option>
                  <option value="title" {{ ($settings['map_title_museum'] ?? '') === 'title' ? 'selected' : '' }}>{{ __('Title') }}</option>
                  <option value="none" {{ ($settings['map_title_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_title_dam]">
                  <option value="title" {{ ($settings['map_title_dam'] ?? 'title') === 'title' ? 'selected' : '' }}>{{ __('Title / Filename') }}</option>
                  <option value="caption" {{ ($settings['map_title_dam'] ?? '') === 'caption' ? 'selected' : '' }}>{{ __('Caption') }}</option>
                  <option value="none" {{ ($settings['map_title_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-user text-muted me-1"></i> Creator/Author</td>
                <td><select class="form-select form-select-sm" name="settings[map_creator_isad]">
                  <option value="nameAccessPoints" {{ ($settings['map_creator_isad'] ?? 'nameAccessPoints') === 'nameAccessPoints' ? 'selected' : '' }}>{{ __('Name Access Points') }}</option>
                  <option value="creators" {{ ($settings['map_creator_isad'] ?? '') === 'creators' ? 'selected' : '' }}>{{ __('Creators (Event)') }}</option>
                  <option value="none" {{ ($settings['map_creator_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_creator_museum]">
                  <option value="productionPerson" {{ ($settings['map_creator_museum'] ?? 'productionPerson') === 'productionPerson' ? 'selected' : '' }}>{{ __('Production Person') }}</option>
                  <option value="nameAccessPoints" {{ ($settings['map_creator_museum'] ?? '') === 'nameAccessPoints' ? 'selected' : '' }}>{{ __('Name Access Points') }}</option>
                  <option value="none" {{ ($settings['map_creator_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_creator_dam]">
                  <option value="creator" {{ ($settings['map_creator_dam'] ?? 'creator') === 'creator' ? 'selected' : '' }}>{{ __('Creator / Photographer') }}</option>
                  <option value="creditLine" {{ ($settings['map_creator_dam'] ?? '') === 'creditLine' ? 'selected' : '' }}>{{ __('Credit Line') }}</option>
                  <option value="none" {{ ($settings['map_creator_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-tags text-muted me-1"></i> Keywords</td>
                <td><select class="form-select form-select-sm" name="settings[map_keywords_isad]">
                  <option value="subjectAccessPoints" {{ ($settings['map_keywords_isad'] ?? 'subjectAccessPoints') === 'subjectAccessPoints' ? 'selected' : '' }}>{{ __('Subject Access Points') }}</option>
                  <option value="genreAccessPoints" {{ ($settings['map_keywords_isad'] ?? '') === 'genreAccessPoints' ? 'selected' : '' }}>{{ __('Genre Access Points') }}</option>
                  <option value="none" {{ ($settings['map_keywords_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_keywords_museum]">
                  <option value="objectCategory" {{ ($settings['map_keywords_museum'] ?? 'objectCategory') === 'objectCategory' ? 'selected' : '' }}>{{ __('Object Category') }}</option>
                  <option value="subjectAccessPoints" {{ ($settings['map_keywords_museum'] ?? '') === 'subjectAccessPoints' ? 'selected' : '' }}>{{ __('Subject Access Points') }}</option>
                  <option value="none" {{ ($settings['map_keywords_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_keywords_dam]">
                  <option value="keywords" {{ ($settings['map_keywords_dam'] ?? 'keywords') === 'keywords' ? 'selected' : '' }}>{{ __('Keywords / Tags') }}</option>
                  <option value="category" {{ ($settings['map_keywords_dam'] ?? '') === 'category' ? 'selected' : '' }}>{{ __('Category') }}</option>
                  <option value="none" {{ ($settings['map_keywords_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-align-left text-muted me-1"></i> Description</td>
                <td><select class="form-select form-select-sm" name="settings[map_description_isad]">
                  <option value="scopeAndContent" {{ ($settings['map_description_isad'] ?? 'scopeAndContent') === 'scopeAndContent' ? 'selected' : '' }}>{{ __('Scope and Content') }}</option>
                  <option value="archivalHistory" {{ ($settings['map_description_isad'] ?? '') === 'archivalHistory' ? 'selected' : '' }}>{{ __('Archival History') }}</option>
                  <option value="none" {{ ($settings['map_description_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_description_museum]">
                  <option value="briefDescription" {{ ($settings['map_description_museum'] ?? 'briefDescription') === 'briefDescription' ? 'selected' : '' }}>{{ __('Brief Description') }}</option>
                  <option value="physicalDescription" {{ ($settings['map_description_museum'] ?? '') === 'physicalDescription' ? 'selected' : '' }}>{{ __('Physical Description') }}</option>
                  <option value="none" {{ ($settings['map_description_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_description_dam]">
                  <option value="caption" {{ ($settings['map_description_dam'] ?? 'caption') === 'caption' ? 'selected' : '' }}>{{ __('Caption / Description') }}</option>
                  <option value="instructions" {{ ($settings['map_description_dam'] ?? '') === 'instructions' ? 'selected' : '' }}>{{ __('Special Instructions') }}</option>
                  <option value="none" {{ ($settings['map_description_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-calendar text-muted me-1"></i> Date Created</td>
                <td><select class="form-select form-select-sm" name="settings[map_date_isad]">
                  <option value="creationEvent" {{ ($settings['map_date_isad'] ?? 'creationEvent') === 'creationEvent' ? 'selected' : '' }}>{{ __('Creation Event Date') }}</option>
                  <option value="none" {{ ($settings['map_date_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_date_museum]">
                  <option value="productionDate" {{ ($settings['map_date_museum'] ?? 'productionDate') === 'productionDate' ? 'selected' : '' }}>{{ __('Production Date') }}</option>
                  <option value="none" {{ ($settings['map_date_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_date_dam]">
                  <option value="dateCreated" {{ ($settings['map_date_dam'] ?? 'dateCreated') === 'dateCreated' ? 'selected' : '' }}>{{ __('Date Created / Taken') }}</option>
                  <option value="dateModified" {{ ($settings['map_date_dam'] ?? '') === 'dateModified' ? 'selected' : '' }}>{{ __('Date Modified') }}</option>
                  <option value="none" {{ ($settings['map_date_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-copyright text-muted me-1"></i> Copyright</td>
                <td><select class="form-select form-select-sm" name="settings[map_copyright_isad]">
                  <option value="accessConditions" {{ ($settings['map_copyright_isad'] ?? 'accessConditions') === 'accessConditions' ? 'selected' : '' }}>{{ __('Access Conditions') }}</option>
                  <option value="reproductionConditions" {{ ($settings['map_copyright_isad'] ?? '') === 'reproductionConditions' ? 'selected' : '' }}>{{ __('Reproduction Conditions') }}</option>
                  <option value="none" {{ ($settings['map_copyright_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_copyright_museum]">
                  <option value="rightsNotes" {{ ($settings['map_copyright_museum'] ?? 'rightsNotes') === 'rightsNotes' ? 'selected' : '' }}>{{ __('Rights Notes') }}</option>
                  <option value="none" {{ ($settings['map_copyright_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_copyright_dam]">
                  <option value="copyrightNotice" {{ ($settings['map_copyright_dam'] ?? 'copyrightNotice') === 'copyrightNotice' ? 'selected' : '' }}>{{ __('Copyright Notice') }}</option>
                  <option value="usageRights" {{ ($settings['map_copyright_dam'] ?? '') === 'usageRights' ? 'selected' : '' }}>{{ __('Usage Rights') }}</option>
                  <option value="none" {{ ($settings['map_copyright_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-camera text-muted me-1"></i> Technical Data</td>
                <td><select class="form-select form-select-sm" name="settings[map_technical_isad]">
                  <option value="physicalCharacteristics" {{ ($settings['map_technical_isad'] ?? 'physicalCharacteristics') === 'physicalCharacteristics' ? 'selected' : '' }}>{{ __('Physical Characteristics') }}</option>
                  <option value="extentAndMedium" {{ ($settings['map_technical_isad'] ?? '') === 'extentAndMedium' ? 'selected' : '' }}>{{ __('Extent and Medium') }}</option>
                  <option value="none" {{ ($settings['map_technical_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_technical_museum]">
                  <option value="technicalDescription" {{ ($settings['map_technical_museum'] ?? 'technicalDescription') === 'technicalDescription' ? 'selected' : '' }}>{{ __('Technical Description') }}</option>
                  <option value="none" {{ ($settings['map_technical_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_technical_dam]">
                  <option value="technicalInfo" {{ ($settings['map_technical_dam'] ?? 'technicalInfo') === 'technicalInfo' ? 'selected' : '' }}>{{ __('Technical Info (EXIF)') }}</option>
                  <option value="cameraInfo" {{ ($settings['map_technical_dam'] ?? '') === 'cameraInfo' ? 'selected' : '' }}>{{ __('Camera / Equipment') }}</option>
                  <option value="none" {{ ($settings['map_technical_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
              <tr>
                <td><i class="fas fa-map-marker-alt text-muted me-1"></i> GPS Location</td>
                <td><select class="form-select form-select-sm" name="settings[map_gps_isad]">
                  <option value="placeAccessPoints" {{ ($settings['map_gps_isad'] ?? 'placeAccessPoints') === 'placeAccessPoints' ? 'selected' : '' }}>{{ __('Place Access Points') }}</option>
                  <option value="physicalCharacteristics" {{ ($settings['map_gps_isad'] ?? '') === 'physicalCharacteristics' ? 'selected' : '' }}>{{ __('Physical Characteristics') }}</option>
                  <option value="none" {{ ($settings['map_gps_isad'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_gps_museum]">
                  <option value="fieldCollectionPlace" {{ ($settings['map_gps_museum'] ?? 'fieldCollectionPlace') === 'fieldCollectionPlace' ? 'selected' : '' }}>{{ __('Field Collection Place') }}</option>
                  <option value="placeAccessPoints" {{ ($settings['map_gps_museum'] ?? '') === 'placeAccessPoints' ? 'selected' : '' }}>{{ __('Place Access Points') }}</option>
                  <option value="none" {{ ($settings['map_gps_museum'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
                <td><select class="form-select form-select-sm" name="settings[map_gps_dam]">
                  <option value="gpsLocation" {{ ($settings['map_gps_dam'] ?? 'gpsLocation') === 'gpsLocation' ? 'selected' : '' }}>{{ __('GPS Coordinates') }}</option>
                  <option value="location" {{ ($settings['map_gps_dam'] ?? '') === 'location' ? 'selected' : '' }}>{{ __('Location Name') }}</option>
                  <option value="none" {{ ($settings['map_gps_dam'] ?? '') === 'none' ? 'selected' : '' }}>{{ __('Do not map') }}</option>
                </select></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- Save --}}
    <div class="d-flex justify-content-between align-items-center">
      <a href="{{ route('settings.index') }}" class="btn btn-link text-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Settings
      </a>
      <button type="submit" class="btn btn-primary">
        <i class="fas fa-save me-1"></i>Save
      </button>
    </div>
  </form>
@endsection
