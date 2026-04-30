{{--
/**
 * Spatial Analysis Export — clone of PSIS reportSpatialAnalysisSuccess template.
 *
 * @author    Johan Pieterse <johan@plainsailing.co.za>
 * @copyright (c) Plain Sailing (Pty) Ltd
 * @license   AGPL-3.0-or-later
 */
--}}
@extends('theme::layouts.1col')
@section('title', 'Spatial Analysis Export')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">
    @include('ahg-reports::_menu')
  </div>
  <div class="col-md-9">
    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('reports.index') }}">Reports</a></li>
        <li class="breadcrumb-item active">Spatial Analysis Export</li>
      </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 mb-1"><i class="bi bi-geo-alt me-2"></i>{{ __('Spatial Analysis Export') }}</h1>
        <p class="text-muted mb-0">Export site records with GPS coordinates for GIS/spatial analysis</p>
      </div>
    </div>

    <form method="post" action="{{ route('reports.spatial') }}">
      @csrf
      <div class="row">
        {{-- Main Configuration --}}
        <div class="col-lg-8">
          {{-- Coordinate Source --}}
          <div class="card mb-4">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-pin-map me-2"></i>{{ __('Coordinate Source') }}</h6>
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label for="coordinate_source" class="form-label">{{ __('Where are coordinates stored?') }}</label>
                <select class="form-select" id="coordinate_source" name="coordinate_source" onchange="togglePropertyFields()">
                  @foreach ($coordinateSources as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                  @endforeach
                </select>
                <div class="form-text">Select the database location where GPS coordinates are stored for your site records.</div>
              </div>

              <div id="propertyFields" class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="latitude_property" class="form-label">{{ __('Latitude Property Name') }}</label>
                    <input type="text" class="form-control" id="latitude_property" name="latitude_property" value="latitude">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="longitude_property" class="form-label">{{ __('Longitude Property Name') }}</label>
                    <input type="text" class="form-control" id="longitude_property" name="longitude_property" value="longitude">
                  </div>
                </div>
              </div>
            </div>
          </div>

          {{-- Record Filters --}}
          <div class="card mb-4">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-funnel me-2"></i>{{ __('Record Filters') }}</h6>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="places" class="form-label">{{ __('Place Access Points (Countries)') }}</label>
                    <select class="form-select" id="places" name="places[]" multiple size="6">
                      @foreach ($availablePlaces as $id => $name)
                        <option value="{{ $name }}"
                          {{ in_array($name, ['South Africa', 'Lesotho', 'Eswatini', 'Swaziland']) ? 'selected' : '' }}>
                          {{ $name }}
                        </option>
                      @endforeach
                    </select>
                    <div class="form-text">Hold Ctrl/Cmd to select multiple. Leave empty for all places.</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="level_of_description" class="form-label">{{ __('Level of Description') }}</label>
                    <select class="form-select" id="level_of_description" name="level_of_description">
                      <option value="">-- All Levels --</option>
                      @foreach ($availableLevels as $id => $name)
                        <option value="{{ $name }}">{{ $name }}</option>
                      @endforeach
                    </select>
                    <div class="form-text">Filter by level (e.g., Site, Collection, Fonds)</div>
                  </div>
                </div>
              </div>

              <div class="mb-3">
                <label for="subject_filter_terms" class="form-label">{{ __('Subject Access Point Filter') }}</label>
                <textarea class="form-control" id="subject_filter_terms" name="subject_filter_terms" rows="4"
                  placeholder="{{ __('Enter subject terms to filter by (one per line)...') }}">brush painted
finger painted
engraving
pecking
incising
San
Khoekhoen
Khoi</textarea>
                <div class="form-text">Records must have at least one of these subject terms. Leave empty for all subjects.</div>
              </div>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="top_level_only" name="top_level_only" value="1" checked>
                    <label class="form-check-label" for="top_level_only">
                      Top-level records only (exclude child records like panels/images)
                    </label>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="require_coordinates" name="require_coordinates" value="1" checked>
                    <label class="form-check-label" for="require_coordinates">
                      Require coordinates (exclude records without lat/long)
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          {{-- Tradition Classification --}}
          <div class="card mb-4">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-tags me-2"></i>{{ __('Tradition Classification') }}</h6>
            </div>
            <div class="card-body">
              <p class="text-muted small">Configure which subject terms indicate painted vs engraved traditions. The export will include <code>is_painted</code> and <code>is_engraved</code> boolean columns based on these terms.</p>

              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="painted_terms" class="form-label">{{ __('Painted Tradition Terms') }}</label>
                    <textarea class="form-control" id="painted_terms" name="painted_terms" rows="6">{{ $defaultPaintedTerms }}</textarea>
                    <div class="form-text">One term per line. Records with these subjects = is_painted: TRUE</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="engraved_terms" class="form-label">{{ __('Engraved Tradition Terms') }}</label>
                    <textarea class="form-control" id="engraved_terms" name="engraved_terms" rows="6">{{ $defaultEngravedTerms }}</textarea>
                    <div class="form-text">One term per line. Records with these subjects = is_engraved: TRUE</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
          {{-- Export Actions --}}
          <div class="card mb-4">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-download me-2"></i>{{ __('Export') }}</h6>
            </div>
            <div class="card-body">
              <div class="d-grid gap-2">
                <button type="submit" name="preview" value="1" class="btn btn-outline-primary">
                  <i class="bi bi-eye me-1"></i> {{ __('Preview (10 records)') }}
                </button>
                <button type="submit" name="export" value="1" class="btn btn-primary">
                  <i class="bi bi-file-earmark-spreadsheet me-1"></i> {{ __('Export CSV') }}
                </button>
                <button type="submit" name="export" value="1" formaction="{{ route('reports.spatial') }}?format=json" class="btn btn-outline-secondary">
                  <i class="bi bi-filetype-json me-1"></i> {{ __('Export JSON') }}
                </button>
              </div>
            </div>
          </div>

          {{-- Output Fields --}}
          <div class="card mb-4">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-list-columns me-2"></i>{{ __('Output Columns') }}</h6>
            </div>
            <div class="card-body p-0">
              <ul class="list-group list-group-flush">
                <li class="list-group-item py-2"><code>reference_code</code> - Site identifier</li>
                <li class="list-group-item py-2"><code>site_name</code> - Site title</li>
                <li class="list-group-item py-2"><code>latitude</code> - GPS latitude</li>
                <li class="list-group-item py-2"><code>longitude</code> - GPS longitude</li>
                <li class="list-group-item py-2"><code>place_country</code> - Country from place terms</li>
                <li class="list-group-item py-2"><code>is_painted</code> - TRUE/FALSE</li>
                <li class="list-group-item py-2"><code>is_engraved</code> - TRUE/FALSE</li>
                <li class="list-group-item py-2"><code>subjects</code> - All subject tags</li>
              </ul>
            </div>
          </div>

          {{-- Help --}}
          <div class="card">
            <div class="card-header">
              <h6 class="mb-0"><i class="bi bi-question-circle me-2"></i>{{ __('Help') }}</h6>
            </div>
            <div class="card-body small">
              <p><strong>{{ __('Use Case:') }}</strong> Overlay site locations onto geological maps to investigate relationships between surface geology and rock art traditions.</p>
              <p><strong>{{ __('Coordinate Sources:') }}</strong></p>
              <ul class="mb-2">
                <li><strong>{{ __('Property Table:') }}</strong> Custom fields stored in the property table</li>
                <li><strong>{{ __('NMMZ Site:') }}</strong> Archaeological site records with GPS</li>
                <li><strong>{{ __('DAM Metadata:') }}</strong> GPS extracted from image EXIF</li>
                <li><strong>{{ __('Contact Info:') }}</strong> Repository location coordinates</li>
              </ul>
              <p class="mb-0"><strong>{{ __('Note:') }}</strong> Records can be both painted AND engraved if they have subjects matching both term lists.</p>
            </div>
          </div>
        </div>
      </div>
    </form>

    @if (isset($previewData) && $previewData)
    {{-- Preview Results --}}
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-table me-2"></i>{{ __('Preview Results') }}</h6>
        <span class="badge bg-primary">{{ $previewData['count'] }} records (limited to 10)</span>
      </div>
      <div class="card-body p-0">
        @if (!empty($previewData['rows']))
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                @foreach ($previewData['headers'] as $key => $label)
                  <th>{{ $label }}</th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              @foreach ($previewData['rows'] as $row)
                <tr>
                  @foreach (array_keys($previewData['headers']) as $key)
                    <td>
                      @php
                      $value = $row[$key] ?? '';
                      if ($key === 'subjects_concatenated' && strlen($value) > 50) {
                          echo '<span title="{{ __("' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '") }}">' . htmlspecialchars(substr($value, 0, 50), ENT_QUOTES, 'UTF-8') . '...</span>';
                      } elseif ($key === 'is_painted' || $key === 'is_engraved') {
                          $badgeClass = $value === 'TRUE' ? 'bg-success' : 'bg-secondary';
                          echo '<span class="badge ' . $badgeClass . '">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
                      } else {
                          echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                      }
                      @endphp
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        @else
        <div class="p-4 text-center text-muted">
          <i class="bi bi-inbox fs-1 d-block mb-2"></i>
          <p class="mb-0">No records match the current filter criteria.</p>
        </div>
        @endif
      </div>
    </div>
    @endif
  </div>
</div>

<script>
function togglePropertyFields() {
  const source = document.getElementById('coordinate_source').value;
  const propertyFields = document.getElementById('propertyFields');
  propertyFields.style.display = source === 'property' ? 'flex' : 'none';
}
document.addEventListener('DOMContentLoaded', togglePropertyFields);
</script>
@endsection
