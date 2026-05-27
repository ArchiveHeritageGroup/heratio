@extends('theme::layouts.1col')
@section('title', 'Import MARC Records')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="mb-0">Import MARC Records</h2>
                <span class="badge bg-info text-dark mt-1">MARCXML Batch Import</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- File upload form --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <i class="fas fa-upload me-2"></i>Upload MARC File
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('library.marc-import-preview') }}" enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="marc_file" class="form-label">Select MARCXML file</label>
                    <input type="file" name="marc_file" id="marc_file" class="form-control"
                           accept=".xml,.marcxml,text/xml,application/xml" required>
                    <div class="form-text">
                        Accepts .xml or .marcxml files up to 20 MB.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label d-block">Format</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="format" id="fmt_xml" value="marcxml" checked>
                        <label class="form-check-label" for="fmt_xml">MARCXML</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="format" id="fmt_marc" value="marc">
                        <label class="form-check-label" for="fmt_marc">MARC Binary (.mrc)</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Preview Import
                </button>
            </form>

            <hr>

            <p class="mb-1 small fw-semibold text-muted">What happens next:</p>
            <ol class="small text-muted mb-0">
                <li>The first record from the file is parsed and displayed for review.</li>
                <li>Field sections (leader, control fields, title, author, publication, etc.) are shown.</li>
                <li>Click <strong>Commit Import</strong> to create library items for all valid records.</li>
            </ol>
        </div>
    </div>

    {{-- Preview results (shown after formImportPreview posts back) --}}
    @if(isset($preview_data) && !empty($preview_data))
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list-check me-2"></i>Preview: First Record</span>
                <span class="badge bg-secondary">{{ count($preview_data) }} section(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="accordion" id="previewAccordion">

                    @php $sectionIdx = 0; @endphp
                    @foreach($preview_data as $sectionKey => $section)
                        @php $sectionId = 'section_' . $sectionIdx; @endphp
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading_{{ $sectionId }}">
                                <button class="accordion-button {{ $sectionIdx > 0 ? 'collapsed' : '' }}" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#{{ $sectionId }}"
                                        aria-expanded="{{ $sectionIdx === 0 ? 'true' : 'false' }}"
                                        aria-controls="{{ $sectionId }}">
                                    <span class="badge bg-info text-dark me-2">{{ $section['label'] }}</span>
                                    <span class="text-muted small">{{ count($section['fields']) }} field(s)</span>
                                </button>
                            </h2>
                            <div id="{{ $sectionId }}" class="accordion-collapse collapse {{ $sectionIdx === 0 ? 'show' : '' }}"
                                 aria-labelledby="heading_{{ $sectionId }}" data-bs-parent="#previewAccordion">
                                <div class="accordion-body p-0">
                                    @if(empty($section['fields']))
                                        <p class="text-muted small p-3 mb-0">No data in this section.</p>
                                    @else
                                        <table class="table table-striped table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:60px">Tag</th>
                                                    <th style="width:60px">Ind1</th>
                                                    <th style="width:60px">Ind2</th>
                                                    <th>Subfield Code</th>
                                                    <th>Content</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($section['fields'] as $field)
                                                    @if(isset($field['value']))
                                                        {{-- Plain text (controlfield / leader) --}}
                                                        <tr>
                                                            <td><code>{{ $field['tag'] }}</code></td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td class="text-break">{{ $field['value'] }}</td>
                                                        </tr>
                                                    @else
                                                        {{-- Datafield with subfields --}}
                                                        @php
                                                            $tag = $field['tag'] ?? '';
                                                            $ind1 = $field['ind1'] ?? ' ';
                                                            $ind2 = $field['ind2'] ?? ' ';
                                                        @endphp
                                                        @if(!empty($field['subfields']))
                                                            @foreach($field['subfields'] as $code => $value)
                                                                <tr>
                                                                    <td><code>{{ $tag }}</code></td>
                                                                    <td><code>{{ $ind1 }}</code></td>
                                                                    <td><code>{{ $ind2 }}</code></td>
                                                                    <td><code>${{ $code }}</code></td>
                                                                    <td class="text-break">{{ $value }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td><code>{{ $tag }}</code></td>
                                                                <td><code>{{ $ind1 }}</code></td>
                                                                <td><code>{{ $ind2 }}</code></td>
                                                                <td>-</td>
                                                                <td class="text-muted small">No subfields</td>
                                                            </tr>
                                                        @endif
                                                    @endif
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @php $sectionIdx++; @endphp
                    @endforeach

                </div>{{-- end accordion --}}
            </div>

            <div class="card-footer">
                <form method="POST" action="{{ route('library.marc-import-commit') }}" enctype="multipart/form-data" class="d-inline">
                    @csrf
                    <input type="hidden" name="marc_file" value="@if(isset($raw_marcxml)){{ base64_encode($raw_marcxml) }}@endif">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Commit Import
                    </button>
                </form>
                <a href="{{ route('library.marc-import') }}" class="btn btn-outline-secondary ms-2">
                    Upload Different File
                </a>
            </div>
        </div>
    @endif

</div>
@endsection