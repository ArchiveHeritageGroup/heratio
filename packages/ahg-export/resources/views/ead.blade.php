@extends('theme::layouts.1col')

@section('title', 'EAD 2002 Export')

@section('content')
<div class="container-fluid py-4">
    <h1>EAD 2002 Export</h1>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Export to Encoded Archival Description (EAD)</h5>
        </div>
        <div class="card-body">

            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                Export archival descriptions in EAD 2002 XML format. Select a top-level record (fonds/collection) to export with its hierarchy.
            </div>

            <form action="{{ route('export.ead') }}" method="post">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Select Record to Export <span class="badge bg-danger ms-1">Required</span></label>
                    <select name="object_id" class="form-select" required>
                        <option value="">-- Select a fonds or collection --</option>
                    </select>
                    <small class="text-muted">Showing top-level archival descriptions.</small>
                </div>

                <div class="mb-3 form-check">
                    <input type="checkbox" name="include_descendants" value="1" class="form-check-input" id="includeDescendants" checked>
                    <label class="form-check-label" for="includeDescendants">
                        Include all descendants (series, files, items)
                    </label>
                </div>

                <hr>

                <h6>EAD Export includes:</h6>
                <ul class="small text-muted">
                    <li>Descriptive identification (unitid, unittitle, unitdate)</li>
                    <li>Scope and content</li>
                    <li>Arrangement</li>
                    <li>Access and use restrictions</li>
                    <li>Custodial history</li>
                    <li>Subject access points</li>
                    <li>Hierarchical component structure (dsc/c)</li>
                </ul>

                <hr>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('export.index') }}" class="atom-btn-white">
                        <i class="bi bi-arrow-left me-1"></i>Back
                    </a>
                    <button type="submit" class="atom-btn-white">
                        <i class="bi bi-download me-1"></i>Export EAD XML
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
@endsection
