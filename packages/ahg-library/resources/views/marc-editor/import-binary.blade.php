@extends('theme::layouts.1col')
@section('title', 'Import MARC Binary')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="mb-0">{{ __('Import MARC Binary') }}</h2>
                <span class="badge bg-warning text-dark mt-1">MARC21 Binary (ISO 2709)</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    {{-- File upload --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <i class="fas fa-file-import me-2"></i>Upload MARC Binary (.mrc) File
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('library.marc-binary-preview') }}"
                  enctype="multipart/form-data">
                @csrf
                <div class="mb-3">
                    <label for="marc_file" class="form-label">{{ __('Select MARC binary file') }}</label>
                    <input type="file" name="marc_file" id="marc_file" class="form-control"
                           accept=".mrc,.mrk,.bib,.dat,application/octet-stream" required>
                    <div class="form-text">MARC21 binary / ISO 2709 files. Max 20 MB.</div>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Preview Import
                </button>
            </form>

            <hr>
            <p class="mb-1 small fw-semibold text-muted">What happens next:</p>
            <ol class="small text-muted mb-0">
                <li>The file is parsed as ISO 2709 binary MARC21.</li>
                <li>A preview table shows extracted fields grouped by MARC section.</li>
                <li>Click <strong>Commit Import</strong> to create a library item.</li>
            </ol>
        </div>
    </div>

    {{-- Preview --}}
    @if(isset($preview_data) && !empty($preview_data))
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list-check me-2"></i>Preview: Decoded Record</span>
                <span class="badge bg-secondary">{{ count($preview_data) }} section(s)</span>
            </div>
            <div class="card-body p-0">
                <div class="accordion" id="previewAccordion">
                    @php $sectionIdx = 0; @endphp
                    @foreach($preview_data as $sectionKey => $section)
                        @php $sectionId = 'section_' . $sectionIdx; @endphp
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading_{{ $sectionId }}">
                                <button class="accordion-button {{ $sectionIdx > 0 ? 'collapsed' : '' }}"
                                        type="button" data-bs-toggle="collapse"
                                        data-bs-target="#{{ $sectionId }}"
                                        aria-expanded="{{ $sectionIdx === 0 ? 'true' : 'false' }}"
                                        aria-controls="{{ $sectionId }}">
                                    <span class="badge bg-info text-dark me-2">{{ $section['label'] }}</span>
                                    <span class="text-muted small">{{ count($section['fields']) }} field(s)</span>
                                </button>
                            </h2>
                            <div id="{{ $sectionId }}"
                                 class="accordion-collapse collapse {{ $sectionIdx === 0 ? 'show' : '' }}"
                                 aria-labelledby="heading_{{ $sectionId }}"
                                 data-bs-parent="#previewAccordion">
                                <div class="accordion-body p-0">
                                    @if(empty($section['fields']))
                                        <p class="text-muted small p-3 mb-0">No data in this section.</p>
                                    @else
                                        <table class="table table-striped table-sm mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:60px">{{ __('Tag') }}</th>
                                                    <th style="width:60px">{{ __('Ind1') }}</th>
                                                    <th style="width:60px">{{ __('Ind2') }}</th>
                                                    <th>{{ __('Subfield Code') }}</th>
                                                    <th>{{ __('Content') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($section['fields'] as $field)
                                                    @if(isset($field['value']))
                                                        <tr>
                                                            <td><code>{{ $field['tag'] }}</code></td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td>-</td>
                                                            <td class="text-break">{{ $field['value'] }}</td>
                                                        </tr>
                                                    @else
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
                                                                    <td><code>\${{ $code }}</code></td>
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
                </div>
            </div>
            <div class="card-footer">
                <form method="POST" action="{{ route('library.marc-binary-commit') }}"
                      enctype="multipart/form-data" class="d-inline">
                    @csrf
                    <input type="hidden" name="marc_file"
                           value="@if(isset($raw_marc)){{ base64_encode($raw_marc) }}@endif">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Commit Import
                    </button>
                </form>
                <a href="{{ route('library.marc-binary') }}" class="btn btn-outline-secondary ms-2">
                    Upload Different File
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
