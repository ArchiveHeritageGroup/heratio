@extends('theme::layouts.1col')

@section('title', ($storage ? 'Edit' : 'Create') . ' physical storage')

@section('content')
  <div class="multiline-header d-flex flex-column mb-3">
    <h1 class="mb-0">{{ $storage ? 'Edit' : 'Create' }} physical storage</h1>
    @if($storage)
      <span class="small">{{ $storage->name }}</span>
    @endif
  </div>
  @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ $storage ? route('physicalobject.update', $storage->slug) : route('physicalobject.store') }}">
    @csrf

    <div class="row">
      <div class="col-md-8">

        {{-- Basic Information --}}
        <div class="card mb-4">
          <div class="card-header" style="background:var(--ahg-primary);color:#fff">
            <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Basic Information</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="name" class="form-label">Name <span class="badge bg-danger ms-1">Required</span></label>
                  <input type="text" name="name" id="name" class="form-control" required
                         value="{{ old('name', $storage->name ?? '') }}">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="type_id" class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="type_id" id="type_id" class="form-select">
                    <option value="">Select...</option>
                    @foreach($typeChoices as $tid => $tname)
                      <option value="{{ $tid }}" {{ old('type_id', $storage->type_id ?? '') == $tid ? 'selected' : '' }}>{{ $tname }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label for="location" class="form-label">Location (legacy) <span class="badge bg-secondary ms-1">Optional</span></label>
              <input type="text" name="location" id="location" class="form-control"
                     value="{{ old('location', $storage->location ?? '') }}"
                     placeholder="Use extended location fields below instead">
              <small class="text-muted">For backwards compatibility. Use the detailed fields below.</small>
            </div>
          </div>
        </div>

        {{-- Extended Location --}}
        <div class="card mb-4">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Location Details</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="building" class="form-label">Building <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="building" id="building" class="form-control"
                         value="{{ old('building', $extendedData['building'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="floor" class="form-label">Floor <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="floor" id="floor" class="form-control"
                         value="{{ old('floor', $extendedData['floor'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="room" class="form-label">Room <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="room" id="room" class="form-control"
                         value="{{ old('room', $extendedData['room'] ?? '') }}">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-3">
                <div class="mb-3">
                  <label for="aisle" class="form-label">Aisle <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="aisle" id="aisle" class="form-control"
                         value="{{ old('aisle', $extendedData['aisle'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-3">
                <div class="mb-3">
                  <label for="bay" class="form-label">Bay <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="bay" id="bay" class="form-control"
                         value="{{ old('bay', $extendedData['bay'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-3">
                <div class="mb-3">
                  <label for="rack" class="form-label">Rack <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="rack" id="rack" class="form-control"
                         value="{{ old('rack', $extendedData['rack'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-3">
                <div class="mb-3">
                  <label for="shelf" class="form-label">Shelf <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="shelf" id="shelf" class="form-control"
                         value="{{ old('shelf', $extendedData['shelf'] ?? '') }}">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="position" class="form-label">Position <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="position" id="position" class="form-control"
                         value="{{ old('position', $extendedData['position'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="barcode" class="form-label">Barcode <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="barcode" id="barcode" class="form-control"
                         value="{{ old('barcode', $extendedData['barcode'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="reference_code" class="form-label">Reference Code <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="text" name="reference_code" id="reference_code" class="form-control"
                         value="{{ old('reference_code', $extendedData['reference_code'] ?? '') }}">
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Dimensions --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-ruler-combined me-2"></i>Dimensions (cm)</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="width" class="form-label">Width <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.01" name="width" id="width" class="form-control"
                         value="{{ old('width', $extendedData['width'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="height" class="form-label">Height <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.01" name="height" id="height" class="form-control"
                         value="{{ old('height', $extendedData['height'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="depth" class="form-label">Depth <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.01" name="depth" id="depth" class="form-control"
                         value="{{ old('depth', $extendedData['depth'] ?? '') }}">
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Capacity Tracking --}}
        <div class="card mb-4">
          <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Capacity Tracking</h5>
          </div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="total_capacity" class="form-label">Total Capacity <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" name="total_capacity" id="total_capacity" class="form-control"
                         value="{{ old('total_capacity', $extendedData['total_capacity'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="used_capacity" class="form-label">Used Capacity <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" name="used_capacity" id="used_capacity" class="form-control"
                         value="{{ old('used_capacity', $extendedData['used_capacity'] ?? 0) }}">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label for="capacity_unit" class="form-label">Capacity Unit <span class="badge bg-secondary ms-1">Optional</span></label>
                  <select name="capacity_unit" id="capacity_unit" class="form-select">
                    <option value="">Select...</option>
                    <option value="boxes" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'boxes')>Boxes</option>
                    <option value="files" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'files')>Files</option>
                    <option value="folders" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'folders')>Folders</option>
                    <option value="items" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'items')>Items</option>
                    <option value="volumes" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'volumes')>Volumes</option>
                    <option value="metres" @selected(old('capacity_unit', $extendedData['capacity_unit'] ?? '') === 'metres')>Linear metres</option>
                  </select>
                </div>
              </div>
            </div>
            @if(!empty($extendedData['total_capacity']))
              @php
                $used = (int)($extendedData['used_capacity'] ?? 0);
                $total = (int)$extendedData['total_capacity'];
                $percent = $total > 0 ? round(($used / $total) * 100) : 0;
                $barClass = $percent >= 90 ? 'bg-danger' : ($percent >= 70 ? 'bg-warning' : 'bg-success');
              @endphp
              <div class="mb-3">
                <label class="form-label">Capacity Usage <span class="badge bg-secondary ms-1">Optional</span></label>
                <div class="progress" style="height:25px">
                  <div class="progress-bar {{ $barClass }}" role="progressbar" style="width:{{ $percent }}%">
                    {{ $used }} / {{ $total }} ({{ $percent }}%)
                  </div>
                </div>
              </div>
            @endif
            <hr>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="total_linear_metres" class="form-label">Total Linear Metres <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.01" name="total_linear_metres" id="total_linear_metres" class="form-control"
                         value="{{ old('total_linear_metres', $extendedData['total_linear_metres'] ?? '') }}">
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label for="used_linear_metres" class="form-label">Used Linear Metres <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.01" name="used_linear_metres" id="used_linear_metres" class="form-control"
                         value="{{ old('used_linear_metres', $extendedData['used_linear_metres'] ?? 0) }}">
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <div class="col-md-4">

        {{-- Status --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-toggle-on me-2"></i>Status</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="status" class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="status" id="status" class="form-select">
                <option value="active" @selected(old('status', $extendedData['status'] ?? 'active') === 'active')>Active</option>
                <option value="full" @selected(old('status', $extendedData['status'] ?? '') === 'full')>Full</option>
                <option value="maintenance" @selected(old('status', $extendedData['status'] ?? '') === 'maintenance')>Under Maintenance</option>
                <option value="decommissioned" @selected(old('status', $extendedData['status'] ?? '') === 'decommissioned')>Decommissioned</option>
              </select>
            </div>
          </div>
        </div>

        {{-- Environmental --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-thermometer-half me-2"></i>Environmental</h5>
          </div>
          <div class="card-body">
            <div class="mb-3 form-check">
              <input type="checkbox" name="climate_controlled" value="1" class="form-check-input" id="climate_controlled"
                     @checked(!empty(old('climate_controlled', $extendedData['climate_controlled'] ?? '')))>
              <label class="form-check-label" for="climate_controlled">Climate Controlled <span class="badge bg-secondary ms-1">Optional</span></label>
            </div>
            <div class="row">
              <div class="col-6">
                <div class="mb-3">
                  <label for="temperature_min" class="form-label">Temp Min (°C) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.1" name="temperature_min" id="temperature_min" class="form-control"
                         value="{{ old('temperature_min', $extendedData['temperature_min'] ?? '') }}">
                </div>
              </div>
              <div class="col-6">
                <div class="mb-3">
                  <label for="temperature_max" class="form-label">Temp Max (°C) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.1" name="temperature_max" id="temperature_max" class="form-control"
                         value="{{ old('temperature_max', $extendedData['temperature_max'] ?? '') }}">
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-6">
                <div class="mb-3">
                  <label for="humidity_min" class="form-label">Humidity Min (%) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.1" name="humidity_min" id="humidity_min" class="form-control"
                         value="{{ old('humidity_min', $extendedData['humidity_min'] ?? '') }}">
                </div>
              </div>
              <div class="col-6">
                <div class="mb-3">
                  <label for="humidity_max" class="form-label">Humidity Max (%) <span class="badge bg-secondary ms-1">Optional</span></label>
                  <input type="number" step="0.1" name="humidity_max" id="humidity_max" class="form-control"
                         value="{{ old('humidity_max', $extendedData['humidity_max'] ?? '') }}">
                </div>
              </div>
            </div>
          </div>
        </div>

        {{-- Security --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Security</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="security_level" class="form-label">Security Level <span class="badge bg-secondary ms-1">Optional</span></label>
              <select name="security_level" id="security_level" class="form-select">
                <option value="">Select...</option>
                <option value="public" @selected(old('security_level', $extendedData['security_level'] ?? '') === 'public')>Public</option>
                <option value="restricted" @selected(old('security_level', $extendedData['security_level'] ?? '') === 'restricted')>Restricted</option>
                <option value="confidential" @selected(old('security_level', $extendedData['security_level'] ?? '') === 'confidential')>Confidential</option>
                <option value="secure" @selected(old('security_level', $extendedData['security_level'] ?? '') === 'secure')>Secure</option>
                <option value="vault" @selected(old('security_level', $extendedData['security_level'] ?? '') === 'vault')>Vault</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="access_restrictions" class="form-label">Access Restrictions <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="access_restrictions" id="access_restrictions" class="form-control" rows="3">{{ old('access_restrictions', $extendedData['access_restrictions'] ?? '') }}</textarea>
            </div>
          </div>
        </div>

        {{-- Notes --}}
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes</h5>
          </div>
          <div class="card-body">
            <div class="mb-3">
              <label for="notes" class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label>
              <textarea name="notes" id="notes" class="form-control" rows="4">{{ old('notes', $extendedData['notes'] ?? '') }}</textarea>
            </div>
          </div>
        </div>

      </div>
    </div>

    <section class="actions mb-3">
      <ul class="actions mb-1 nav gap-2">
        @if($storage)
          <li><a href="{{ route('physicalobject.show', $storage->slug) }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Save"></li>
        @else
          <li><a href="{{ route('physicalobject.browse') }}" class="btn atom-btn-outline-light" role="button">Cancel</a></li>
          <li><input class="btn atom-btn-outline-success" type="submit" value="Create"></li>
        @endif
        <li><a href="{{ route('physicalobject.browse') }}" class="btn atom-btn-outline-light" role="button"><i class="fas fa-list me-1"></i>Browse physical objects</a></li>
      </ul>
    </section>
  </form>
@endsection
