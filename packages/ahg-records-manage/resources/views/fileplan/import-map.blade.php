@extends('theme::layouts.1col')
@section('title', 'Import File Plan - Map Columns')
@section('body-class', 'admin records')

@section('title-block')
<h1>Import File Plan - Step 2: Map Columns</h1>
@endsection

@section('content')

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<form method="post" action="{{ route('records.fileplan.import.map') }}">
    @csrf
    <input type="hidden" name="file_path" value="{{ $filePath }}">
    <input type="hidden" name="department" value="{{ $department }}">
    <input type="hidden" name="agency_code" value="{{ $agencyCode }}">

    <div class="card mb-3">
        <div class="card-header">Column Mapping</div>
        <div class="card-body">
            <p class="text-muted">Map spreadsheet columns to file plan fields. Auto-detected mappings are highlighted.</p>

            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Target Field</th>
                            <th>Spreadsheet Column</th>
                            <th>Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $fields = [
                                'code' => ['label' => 'Code / Reference', 'required' => true],
                                'title' => ['label' => 'Title / Name', 'required' => true],
                                'description' => ['label' => 'Description', 'required' => false],
                                'retention_period' => ['label' => 'Retention Period', 'required' => false],
                                'disposal_action' => ['label' => 'Disposal Action', 'required' => false],
                            ];
                        @endphp
                        @foreach($fields as $fieldKey => $fieldInfo)
                        <tr class="{{ isset($detectedMapping[$fieldKey]) ? 'table-success' : '' }}">
                            <td>{{ $fieldInfo['label'] }}</td>
                            <td>
                                <select name="mapping[{{ $fieldKey }}]" class="form-select form-select-sm">
                                    <option value="">-- Skip --</option>
                                    @foreach($headers as $colLetter => $headerName)
                                        @if(!empty($headerName))
                                            <option value="{{ $colLetter }}" {{ (($detectedMapping[$fieldKey] ?? '') === $colLetter) ? 'selected' : '' }}>
                                                {{ $colLetter }}: {{ $headerName }}
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                @if($fieldInfo['required'])
                                    <span class="badge bg-danger">Required</span>
                                @else
                                    <span class="badge bg-secondary">Optional</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Sample Data (first 5 rows)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            @foreach($headers as $colLetter => $headerName)
                                @if(!empty($headerName))
                                    <th>{{ $colLetter }}: {{ $headerName }}</th>
                                @endif
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sampleRows as $row)
                        <tr>
                            @foreach($headers as $colLetter => $headerName)
                                @if(!empty($headerName))
                                    <td>{{ $row[$colLetter] ?? '' }}</td>
                                @endif
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between">
        <a href="{{ route('records.fileplan.import') }}" class="btn btn-secondary">Back</a>
        <button type="submit" class="btn btn-primary">Next: Preview</button>
    </div>
</form>
@endsection
