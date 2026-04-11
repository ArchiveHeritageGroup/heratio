@extends('theme::layouts.1col')
@section('title', 'Spatial Analysis Export')
@section('body-class', 'admin reports')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-reports::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-map-marker-alt me-2"></i>Spatial Analysis Export</h1>
    <p class="text-muted">Export archival descriptions with geographic coordinate data for GIS analysis.</p>

    <div class="row">
      {{-- Form (left) --}}
      <div class="col-md-8">
        <form method="POST" action="{{ route('reports.spatial') }}">
          @csrf

          {{-- Coordinate Source --}}
          <div class="card mb-3">
            <div class="card-header" ><i class="fas fa-crosshairs me-2"></i>Coordinate Source</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Source <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="coordinateSource" class="form-select">
                  <option value="property" selected>Property table (latitude/longitude)</option>
                </select>
                <div class="form-text">Coordinates are stored in the property table associated with each information object.</div>
              </div>
            </div>
          </div>

          {{-- Record Filters --}}
          <div class="card mb-3">
            <div class="card-header" ><i class="fas fa-filter me-2"></i>Record Filters</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Place (Taxonomy 42) <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="place[]" class="form-select" multiple size="5">
                  @foreach($placeTerms as $term)
                    <option value="{{ $term->id }}" {{ in_array($term->id, (array) ($params['place'] ?? [])) ? 'selected' : '' }}>{{ $term->name }}</option>
                  @endforeach
                </select>
                <div class="form-text">Hold Ctrl/Cmd to select multiple places. Leave empty for all.</div>
              </div>

              <div class="mb-3">
                <label class="form-label">Level of Description <span class="badge bg-secondary ms-1">Optional</span></label>
                <select name="level" class="form-select">
                  <option value="">-- All levels --</option>
                  @foreach($levels as $lev)
                    <option value="{{ $lev->id }}" {{ ($params['level'] ?? '') == $lev->id ? 'selected' : '' }}>{{ $lev->name }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Subject filter <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="subjects" class="form-control" rows="3" placeholder="Enter subject terms, comma-separated">{{ $params['subjects'] ?? '' }}</textarea>
                <div class="form-text">Comma-separated list of subject terms to filter by.</div>
              </div>

              <div class="form-check mb-2">
                <input type="checkbox" name="topLevelOnly" value="1" class="form-check-input" id="topLevelOnly" {{ ($params['topLevelOnly'] ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="topLevelOnly">Top-level records only <span class="badge bg-secondary ms-1">Optional</span></label>
              </div>

              <div class="form-check mb-2">
                <input type="checkbox" name="requireCoordinates" value="1" class="form-check-input" id="requireCoordinates" {{ ($params['requireCoordinates'] ?? true) ? 'checked' : '' }}>
                <label class="form-check-label" for="requireCoordinates">Require coordinates (exclude records without lat/lng) <span class="badge bg-secondary ms-1">Optional</span></label>
              </div>
            </div>
          </div>

          {{-- Tradition Classification --}}
          <div class="card mb-3">
            <div class="card-header" ><i class="fas fa-layer-group me-2"></i>Tradition Classification</div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label">Include traditions <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="includeTraditions" class="form-control" rows="2" placeholder="Comma-separated tradition names to include"></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">Exclude traditions <span class="badge bg-secondary ms-1">Optional</span></label>
                <textarea name="excludeTraditions" class="form-control" rows="2" placeholder="Comma-separated tradition names to exclude"></textarea>
              </div>
            </div>
          </div>

          {{-- Hidden export field (set by JS) --}}
          <input type="hidden" name="export" id="exportField" value="">

          <div class="d-flex gap-2 mb-4">
            <button type="submit" class="btn atom-btn-outline-success" onclick="document.getElementById('exportField').value=''"><i class="fas fa-eye me-1"></i>Preview</button>
            <button type="submit" class="btn atom-btn-outline-success" onclick="document.getElementById('exportField').value='csv'"><i class="fas fa-file-csv me-1"></i>Export CSV</button>
            <button type="submit" class="btn atom-btn-white text-white" onclick="document.getElementById('exportField').value='json'"><i class="fas fa-file-code me-1"></i>Export JSON</button>
          </div>
        </form>

        {{-- Preview Results --}}
        @if(isset($preview) && $preview->count() > 0)
        <div class="card mb-3">
          <div class="card-header" ><i class="fas fa-table me-2"></i>Preview ({{ $preview->count() }} of {{ number_format($totalCount) }} records)</div>
          <div class="table-responsive">
            <table class="table table-bordered table-sm table-striped mb-0">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Identifier</th>
                  <th>Title</th>
                  <th>Latitude</th>
                  <th>Longitude</th>
                  <th>Level</th>
                  <th>Repository</th>
                </tr>
              </thead>
              <tbody>
                @foreach($preview as $row)
                <tr>
                  <td>{{ $row->id }}</td>
                  <td>{{ $row->identifier }}</td>
                  <td>{{ \Illuminate\Support\Str::limit($row->title, 40) }}</td>
                  <td>{{ $row->latitude }}</td>
                  <td>{{ $row->longitude }}</td>
                  <td>{{ $row->level_of_description }}</td>
                  <td>{{ $row->repository }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
        @elseif(request()->isMethod('post'))
        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>No records found matching the selected filters.</div>
        @endif
      </div>

      {{-- Sidebar (right) --}}
      <div class="col-md-4">
        {{-- Export Buttons --}}
        <div class="card mb-3">
          <div class="card-header" ><i class="fas fa-download me-2"></i>Export</div>
          <div class="card-body">
            <p class="small text-muted">Use the form on the left to filter records, then choose an export format.</p>
            <ul class="list-unstyled small">
              <li><i class="fas fa-eye me-2 text-primary"></i><strong>Preview</strong> — Show first 10 results</li>
              <li><i class="fas fa-file-csv me-2 text-success"></i><strong>CSV</strong> — Spreadsheet-compatible format</li>
              <li><i class="fas fa-file-code me-2 text-info"></i><strong>JSON</strong> — GeoJSON FeatureCollection</li>
            </ul>
          </div>
        </div>

        {{-- Output Columns --}}
        <div class="card mb-3">
          <div class="card-header" ><i class="fas fa-columns me-2"></i>Output Columns</div>
          <ul class="list-group list-group-flush small">
            <li class="list-group-item py-1">ID</li>
            <li class="list-group-item py-1">Identifier</li>
            <li class="list-group-item py-1">Title</li>
            <li class="list-group-item py-1">Latitude</li>
            <li class="list-group-item py-1">Longitude</li>
            <li class="list-group-item py-1">Level of Description</li>
            <li class="list-group-item py-1">Repository</li>
            <li class="list-group-item py-1">Place</li>
          </ul>
        </div>

        {{-- Help --}}
        <div class="card mb-3">
          <div class="card-header" ><i class="fas fa-question-circle me-2"></i>Help</div>
          <div class="card-body small">
            <p>The Spatial Analysis Export tool allows you to extract geographic data from archival descriptions for use in GIS applications.</p>
            <p><strong>Coordinates</strong> are stored in the <code>property</code> table associated with each information object record.</p>
            <p><strong>CSV export</strong> can be imported directly into QGIS, ArcGIS, or Google Earth.</p>
            <p><strong>JSON export</strong> produces GeoJSON FeatureCollection format, suitable for web mapping libraries like Leaflet or OpenLayers.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
