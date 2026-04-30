@extends('theme::layouts.1col')
@section('title', 'Import File Plan')
@section('body-class', 'admin records')

@section('title-block')
<h1>{{ __('Import File Plan') }}</h1>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
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

<div class="card mb-3">
    <div class="card-header">Upload File Plan</div>
    <div class="card-body">
        <form method="post" action="{{ route('records.fileplan.import.upload') }}" enctype="multipart/form-data" id="importForm">
            @csrf

            <div class="mb-3">
                <label class="form-label fw-bold">Source Type <span class="text-danger">*</span></label>
                <div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="source_type" id="type_spreadsheet" value="spreadsheet" {{ old('source_type', 'spreadsheet') === 'spreadsheet' ? 'checked' : '' }}>
                        <label class="form-check-label" for="type_spreadsheet">{{ __('Excel / CSV') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="source_type" id="type_directory" value="directory" {{ old('source_type') === 'directory' ? 'checked' : '' }}>
                        <label class="form-check-label" for="type_directory">{{ __('Directory Path') }}</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="source_type" id="type_xml" value="xml" {{ old('source_type') === 'xml' ? 'checked' : '' }}>
                        <label class="form-check-label" for="type_xml">XML</label>
                    </div>
                </div>
            </div>

            <div id="file-input-section">
                <div class="mb-3">
                    <label for="import_file" class="form-label">{{ __('File') }}</label>
                    <input type="file" name="import_file" id="import_file" class="form-control" accept=".xlsx,.xls,.csv,.ods,.xml">
                    <div class="form-text">Supported formats: .xlsx, .xls, .csv, .ods, .xml (max 50MB)</div>
                </div>
            </div>

            <div id="directory-input-section" class="d-none">
                <div class="mb-3">
                    <label for="directory_path" class="form-label">{{ __('Directory Path') }}</label>
                    <input type="text" name="directory_path" id="directory_path" class="form-control" value="{{ old('directory_path') }}" placeholder="{{ __('/path/to/fileplan/directory') }}">
                    <div class="form-text">Full server path to the directory containing the file plan structure.</div>
                </div>
            </div>

            <div id="xml-format-section" class="d-none">
                <div class="mb-3">
                    <label for="xml_format" class="form-label">{{ __('XML Format') }}</label>
                    <select name="xml_format" id="xml_format" class="form-select">
                        <option value="generic" {{ old('xml_format', 'generic') === 'generic' ? 'selected' : '' }}>{{ __('Generic XML') }}</option>
                        <option value="ead" {{ old('xml_format') === 'ead' ? 'selected' : '' }}>{{ __('EAD (Encoded Archival Description)') }}</option>
                    </select>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="department" class="form-label">{{ __('Department Name') }}</label>
                    <input type="text" name="department" id="department" class="form-control" value="{{ old('department') }}" placeholder="{{ __('e.g. Department of Home Affairs') }}">
                </div>

                <div class="col-md-6 mb-3">
                    <label for="agency_code" class="form-label">{{ __('Agency Code') }}</label>
                    <input type="text" name="agency_code" id="agency_code" class="form-control" value="{{ old('agency_code') }}" maxlength="50" placeholder="{{ __('e.g. DHA') }}">
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <a href="{{ route('records.fileplan.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">{{ __('Next: Map Columns') }}</button>
            </div>
        </form>
    </div>
</div>

@if(!empty($sessions['data']))
<div class="card">
    <div class="card-header">Recent Import Sessions</div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('ID') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('File') }}</th>
                    <th>{{ __('Department') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Nodes') }}</th>
                    <th>{{ __('Date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($sessions['data'] as $sess)
                <tr>
                    <td>{{ $sess->id }}</td>
                    <td>{{ ucfirst($sess->source_type) }}</td>
                    <td>{{ $sess->source_filename ?: '-' }}</td>
                    <td>{{ $sess->department ?: '-' }}</td>
                    <td>
                        <span class="badge {{ $sess->status === 'completed' ? 'bg-success' : ($sess->status === 'failed' ? 'bg-danger' : 'bg-warning text-dark') }}">
                            {{ ucfirst($sess->status) }}
                        </span>
                    </td>
                    <td>{{ $sess->imported_nodes }}/{{ $sess->total_nodes }}</td>
                    <td>{{ $sess->created_at }}</td>
                    <td><a href="{{ route('records.fileplan.import.status', $sess->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var radios = document.querySelectorAll('input[name="source_type"]');
    var fileSection = document.getElementById('file-input-section');
    var dirSection = document.getElementById('directory-input-section');
    var xmlSection = document.getElementById('xml-format-section');

    function toggleSections() {
        var selected = document.querySelector('input[name="source_type"]:checked').value;
        fileSection.classList.toggle('d-none', selected === 'directory');
        dirSection.classList.toggle('d-none', selected !== 'directory');
        xmlSection.classList.toggle('d-none', selected !== 'xml');
    }

    radios.forEach(function(radio) {
        radio.addEventListener('change', toggleSections);
    });

    toggleSections();
});
</script>
@endpush
@endsection
