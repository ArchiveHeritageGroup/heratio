@extends('theme::layouts.1col')
@section('title', ($package ?? null) ? 'Edit OAIS Package' : 'Create OAIS Package')
@section('body-class', 'admin preservation')

@section('content')
<div class="row">
  <div class="col-md-3">@include('ahg-preservation::_menu')</div>
  <div class="col-md-9">
    <h1><i class="fas fa-{{ ($package ?? null) ? 'edit' : 'plus' }} me-2"></i>{{ ($package ?? null) ? 'Edit Package' : 'Create Package' }}</h1>

    @if($errors->any())
      <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <form method="post" action="{{ $formAction ?? '#' }}">
              @csrf
              @if(isset($package)) @method('PUT') @endif

              <div class="card mb-3">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">Package Details</div>
                <div class="card-body">
                  <div class="mb-3">
                    <label class="form-label">Package Name <span class="badge bg-danger ms-1">Required</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="{{ old('name', $package->name ?? '') }}"
                           placeholder="e.g., Annual Reports 2024 SIP">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Description <span class="badge bg-secondary ms-1">Optional</span></label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Brief description of package contents">{{ old('description', $package->description ?? '') }}</textarea>
                  </div>

                  @if(!($package ?? null))
                  <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Package Type <span class="badge bg-danger ms-1">Required</span></label>
                        <select name="package_type" class="form-select" required>
                            <option value="">Select type...</option>
                            <option value="SIP" {{ old('package_type') == 'SIP' ? 'selected' : '' }}>SIP - Submission Information Package</option>
                            <option value="AIP" {{ old('package_type') == 'AIP' ? 'selected' : '' }}>AIP - Archival Information Package</option>
                            <option value="DIP" {{ old('package_type') == 'DIP' ? 'selected' : '' }}>DIP - Dissemination Information Package</option>
                        </select>
                        <div class="form-text">SIP for ingest, AIP for storage, DIP for access</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Package Format <span class="badge bg-secondary ms-1">Optional</span></label>
                        <select name="package_format" class="form-select">
                            <option value="bagit" selected>BagIt (Recommended)</option>
                            <option value="zip">ZIP Archive</option>
                            <option value="tar">TAR Archive</option>
                        </select>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Checksum Algorithm <span class="badge bg-secondary ms-1">Optional</span></label>
                    <select name="manifest_algorithm" class="form-select">
                        <option value="sha256" selected>SHA-256 (Recommended)</option>
                        <option value="sha512">SHA-512</option>
                        <option value="sha1">SHA-1</option>
                        <option value="md5">MD5</option>
                    </select>
                  </div>
                  @else
                  <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Package Type <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" class="form-control" disabled value="{{ strtoupper($package->package_type) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status <span class="badge bg-secondary ms-1">Optional</span></label>
                        <input type="text" class="form-control" disabled value="{{ ucfirst($package->status) }}">
                    </div>
                  </div>
                  @endif

                  <hr>

                  <div class="mb-3">
                    <label class="form-label">Originator <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" name="originator" class="form-control"
                           value="{{ old('originator', $package->originator ?? '') }}"
                           placeholder="Organization creating this package">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Submission Agreement <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" name="submission_agreement" class="form-control"
                           value="{{ old('submission_agreement', $package->submission_agreement ?? '') }}"
                           placeholder="Reference to submission agreement">
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Retention Period <span class="badge bg-secondary ms-1">Optional</span></label>
                    <input type="text" name="retention_period" class="form-control"
                           value="{{ old('retention_period', $package->retention_period ?? '') }}"
                           placeholder="e.g., Permanent, 10 years, etc.">
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-between">
                <a href="{{ route('preservation.packages') }}" class="btn atom-btn-white">
                    <i class="fas fa-times me-1"></i>Cancel
                </a>
                <button type="submit" class="btn atom-btn-white">
                    <i class="fas fa-save me-1"></i>{{ ($package ?? null) ? 'Save Changes' : 'Create Package' }}
                </button>
              </div>
            </form>

            @if(($package ?? null) && $package->status === 'draft')
            {{-- Add Objects Section --}}
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
                    <span><i class="fas fa-file-import me-2"></i>Package Objects</span>
                    <span class="badge bg-light text-dark">{{ count($package->objects ?? []) }} objects</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Add Digital Object</label>
                        <div class="input-group">
                            <input type="number" id="objectIdInput" class="form-control" placeholder="Enter digital object ID">
                            <button type="button" class="btn atom-btn-white" onclick="addObject()">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                        <div class="form-text">Enter the ID of a digital object to add to this package</div>
                    </div>

                    @if(!empty($package->objects) && count($package->objects) > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Format</th>
                                    <th>Size</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($package->objects as $obj)
                                <tr id="obj-row-{{ $obj->digital_object_id ?? '' }}">
                                    <td>
                                        {{ $obj->file_name ?? '' }}
                                        <br><small class="text-muted">{{ $obj->information_object_title ?? $obj->digital_object_name ?? 'No title' }}</small>
                                    </td>
                                    <td>
                                        @if($obj->puid ?? null)
                                        <span class="badge bg-info">{{ $obj->puid }}</span>
                                        @endif
                                        <small class="text-muted d-block">{{ $obj->mime_type ?? 'Unknown' }}</small>
                                    </td>
                                    <td>{{ ($obj->file_size ?? null) ? number_format($obj->file_size / 1024, 1) . ' KB' : '-' }}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeObject({{ $obj->digital_object_id ?? 0 }})">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-inbox fa-2x d-block mb-2 opacity-25"></i>
                        No objects added yet
                    </div>
                    @endif
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            @if($package ?? null)
            {{-- Package Info --}}
            <div class="card mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-info-circle me-1"></i> Package Info
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">UUID</dt>
                        <dd class="col-sm-8"><code class="small">{{ $package->uuid }}</code></dd>
                        <dt class="col-sm-4">Format</dt>
                        <dd class="col-sm-8">{{ ucfirst($package->package_format ?? '') }}</dd>
                        <dt class="col-sm-4">Algorithm</dt>
                        <dd class="col-sm-8">{{ strtoupper($package->manifest_algorithm ?? '') }}</dd>
                        <dt class="col-sm-4">Objects</dt>
                        <dd class="col-sm-8">{{ number_format($package->object_count ?? 0) }}</dd>
                        <dt class="col-sm-4">Size</dt>
                        <dd class="col-sm-8">{{ ($package->total_size ?? null) ? number_format($package->total_size / 1048576, 2) . ' MB' : '-' }}</dd>
                        @if($package->package_checksum ?? null)
                        <dt class="col-sm-4">Checksum</dt>
                        <dd class="col-sm-8"><code class="small">{{ Str::limit($package->package_checksum, 16) }}...</code></dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Actions --}}
            <div class="card mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-bolt me-1"></i> Actions
                </div>
                <div class="card-body">
                    @if($package->status === 'draft' && ($package->object_count ?? 0) > 0)
                    <button type="button" class="btn atom-btn-outline-success w-100 mb-2" onclick="if(confirm('Build the package?')) alert('Build initiated.')">
                        <i class="fas fa-hammer me-1"></i>Build Package
                    </button>
                    @endif

                    @if(($package->status ?? '') === 'complete' || ($package->status ?? '') === 'built')
                    <button type="button" class="btn atom-btn-white w-100 mb-2" onclick="if(confirm('Validate package?')) alert('Validation initiated.')">
                        <i class="fas fa-check-circle me-1"></i>Validate Package
                    </button>
                    @endif

                    @if(in_array($package->status ?? '', ['complete', 'built', 'validated']))
                    <button type="button" class="btn atom-btn-white w-100 mb-2" onclick="alert('Export initiated.')">
                        <i class="fas fa-box-open me-1"></i>Export Package
                    </button>
                    @endif

                    @if($package->export_path ?? null)
                    <a href="#" class="btn atom-btn-white w-100 mb-2">
                        <i class="fas fa-download me-1"></i>Download Export
                    </a>
                    @endif

                    @if(strtolower($package->package_type ?? '') === 'sip' && in_array($package->status ?? '', ['validated', 'exported']))
                    <hr>
                    <button type="button" class="btn atom-btn-white w-100" onclick="alert('AIP conversion initiated.')">
                        <i class="fas fa-arrow-right me-1"></i>Convert to AIP
                    </button>
                    @endif

                    @if(strtolower($package->package_type ?? '') === 'aip' && in_array($package->status ?? '', ['validated', 'exported']))
                    <hr>
                    <button type="button" class="btn atom-btn-white w-100" onclick="alert('DIP creation initiated.')">
                        <i class="fas fa-arrow-right me-1"></i>Create DIP
                    </button>
                    @endif

                    @if(($package->status ?? '') === 'draft')
                    <hr>
                    <button type="button" class="btn btn-outline-danger w-100" onclick="if(confirm('Delete this package?')) alert('Delete initiated.')">
                        <i class="fas fa-trash me-1"></i>Delete Package
                    </button>
                    @endif
                </div>
            </div>

            {{-- Recent Events --}}
            @if(!empty($package->events) && count($package->events) > 0)
            <div class="card">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-history me-1"></i> Recent Events
                </div>
                <ul class="list-group list-group-flush">
                    @foreach(collect($package->events)->take(5) as $event)
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="badge bg-{{ ($event->event_outcome ?? '') === 'success' ? 'success' : (($event->event_outcome ?? '') === 'failure' ? 'danger' : 'secondary') }}">
                                {{ $event->event_type ?? '' }}
                            </span>
                            <small class="text-muted">{{ $event->event_datetime ?? '' }}</small>
                        </div>
                        @if($event->event_detail ?? null)
                        <small class="text-muted d-block mt-1">{{ Str::limit($event->event_detail, 50) }}</small>
                        @endif
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @else
            {{-- Help Card for New Package --}}
            <div class="card">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-question-circle me-1"></i> OAIS Package Types
                </div>
                <div class="card-body">
                    <h6 class="text-info"><i class="fas fa-arrow-circle-right me-1"></i>SIP - Submission</h6>
                    <p class="small text-muted mb-3">Package used to submit content to the archive. Contains the digital objects and metadata.</p>

                    <h6 class="text-success"><i class="fas fa-archive me-1"></i>AIP - Archival</h6>
                    <p class="small text-muted mb-3">Package stored in the archive for long-term preservation. Created from a validated SIP.</p>

                    <h6 class="text-warning"><i class="fas fa-share-square me-1"></i>DIP - Dissemination</h6>
                    <p class="small text-muted mb-0">Package created for user access. Derived from an AIP with access-optimized formats.</p>
                </div>
            </div>
            @endif
        </div>
    </div>
  </div>
</div>

@if($package ?? null)
<script>
const packageId = {{ $package->id }};

function addObject() {
    const objectId = document.getElementById('objectIdInput').value;
    if (!objectId) { alert('Please enter an object ID'); return; }
    alert('Add object #' + objectId + ' to package - API integration pending.');
}

function removeObject(objectId) {
    if (!confirm('Remove this object from the package?')) return;
    const row = document.getElementById('obj-row-' + objectId);
    if (row) row.remove();
}
</script>
@endif
@endsection
