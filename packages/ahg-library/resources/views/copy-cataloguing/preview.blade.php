@extends('theme::layouts.1col')
@section('title', 'Preview & Import — Copy Cataloguing')
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.marc-copy-cataloguing.search') }}"
           class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="mb-0">Preview & Import</h2>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Parsed MARC fields summary --}}
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i>Parsed MARC Fields
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:60px">Tag</th>
                                <th style="width:60px">I1</th>
                                <th style="width:60px">I2</th>
                                <th>Code</th>
                                <th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if(!empty($parsed['data']))
                                @foreach($parsed['data'] as $field)
                                    @php $tag = $field['tag']; $i1 = $field['ind1']; $i2 = $field['ind2']; @endphp
                                    @foreach($field['subfields'] as $code => $value)
                                        <tr>
                                            <td><code>{{ $tag }}</code></td>
                                            <td><code>{{ $i1 }}</code></td>
                                            <td><code>{{ $i2 }}</code></td>
                                            <td><code>${{ $code }}</code></td>
                                            <td class="text-break">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            @elseif(!empty($parsed['leader']))
                                <tr>
                                    <td><code>LEADER</code></td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td>-</td>
                                    <td class="text-break font-monospace small">{{ $parsed['leader'] }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Import form with overrides --}}
            <div class="card shadow-sm">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <i class="fas fa-download me-2"></i>Commit Import
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('library.marc-copy-cataloguing.import') }}">
                        @csrf
                        <input type="hidden" name="marc_content" value="{{ $marcContent }}">
                        <input type="hidden" name="library_item_id" value="{{ $libraryItemId ?? null }}">

                        <p class="text-muted small mb-3">
                            Optionally override fields before importing into the catalogue.
                        </p>

                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="title" class="form-label small fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="title" class="form-control"
                                       value="@if(!empty($parsed['data'])){{ $parsed['data'][0]['subfields']['a'] ?? '' }}@endif"
                                       maxlength="500" required>
                            </div>
                            <div class="col-md-4">
                                <label for="isbn" class="form-label small fw-semibold">ISBN</label>
                                <input type="text" name="isbn" id="isbn" class="form-control" value="">
                            </div>
                            <div class="col-md-6">
                                <label for="publisher" class="form-label small fw-semibold">Publisher</label>
                                <input type="text" name="publisher" class="form-control" value="">
                            </div>
                            <div class="col-md-6">
                                <label for="publication_date" class="form-label small fw-semibold">Publication Date</label>
                                <input type="text" name="publication_date" class="form-control" value="" placeholder="YYYY">
                            </div>
                            <div class="col-md-6">
                                <label for="call_number" class="form-label small fw-semibold">Call Number</label>
                                <input type="text" name="call_number" id="call_number" class="form-control" value="">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-download me-2"></i>Create Library Item
                            </button>
                            <a href="{{ route('library.marc-copy-cataloguing.search') }}"
                               class="btn btn-outline-secondary ms-2">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
