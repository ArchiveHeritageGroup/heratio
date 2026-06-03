@extends('theme::layouts.1col')
@section('title', 'MARC Editor: ' . ($formData['library_item_id'] ?? ''))
@section('content')
<div class="container py-4">
    {{-- Header --}}
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">{{ __('MARC Editor') }}</h2>
            <span class="badge bg-secondary mt-1">Library Item #{{ $formData['library_item_id'] ?? '' }}</span>
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

    <form method="POST" action="{{ route('library.marc-update', $formData['library_item_id'] ?? 0) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="info_object_id" value="{{ $formData['info_object_id'] ?? 0 }}">

        <div class="row g-4">
            {{-- LEFT: field sections --}}
            <div class="col-lg-8">

                {{-- Leader + Control Fields --}}
                @if(!empty($formData['leader']) || !empty($formData['control_fields']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Leader & Control Fields</h5>
                        </div>
                        <div class="card-body">
                            @if(!empty($formData['leader']))
                                <div class="mb-3">
                                    <label class="form-label small fw-semibold">{{ __('Leader') }}</label>
                                    <input type="text" class="form-control font-monospace" style="font-size:0.75rem"
                                           value="{{ $formData['leader'] }}" readonly>
                                </div>
                            @endif
                            @if(!empty($formData['control_fields']))
                                <div class="row g-2">
                                    @foreach($formData['control_fields'] as $tag => $val)
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label small">{{ $tag }}</label>
                                            <input type="text" class="form-control font-monospace" style="font-size:0.75rem"
                                                   name="control_fields[{{ $tag }}]"
                                                   value="{{ $val }}" readonly>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Title Statement --}}
                @if(!empty($formData['title_statement']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-heading me-2"></i>Title Statement (245)</h5>
                        </div>
                        <div class="card-body">
                            @if(isset($formData['title_statement']['245']))
                                @php $f245 = $formData['title_statement']['245']; @endphp
                                <div class="row g-2 mb-2">
                                    <div class="col-md-2">
                                        <label class="form-label small">{{ __('Ind 1') }}</label>
                                        <input type="text" class="form-control font-monospace" style="font-size:0.8rem"
                                               name="title_statement[245][_ind1]"
                                               value="{{ $f245['_ind1'] ?? ' ' }}">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">{{ __('Ind 2') }}</label>
                                        <input type="text" class="form-control font-monospace" style="font-size:0.8rem"
                                               name="title_statement[245][_ind2]"
                                               value="{{ $f245['_ind2'] ?? ' ' }}">
                                    </div>
                                </div>
                                @foreach(['a','b','c','n','p'] as $code)
                                    @if(isset($f245[$code]))
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-2">
                                                <span class="badge bg-light text-dark border">\${{ $code }}</span>
                                            </div>
                                            <div class="col-md-10">
                                                <input type="text" class="form-control" style="font-size:0.9rem"
                                                       name="title_statement[245][{{ $code }}]"
                                                       value="{{ $f245[$code] }}">
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Author Entry --}}
                @if(!empty($formData['author_entry']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Author Entry (1XX/7XX)</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Tag') }}</th><th>{{ __('Ind1') }}</th><th>{{ __('Ind2') }}</th>
                                        <th>{{ __('\\$a (name)') }}</th><th>{{ __('\\$t (title)') }}</th><th>{{ __('\\$e (role)') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($formData['author_entry'] as $idx => $ae)
                                        <tr>
                                            <td><code>{{ $ae['tag'] ?? '' }}</code></td>
                                            <td><input type="text" class="form-control form-control-sm font-monospace"
                                                       name="author_entry[{{ $idx }}][_ind1]"
                                                       value="{{ $ae['_ind1'] ?? ' ' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm font-monospace"
                                                       name="author_entry[{{ $idx }}][_ind2]"
                                                       value="{{ $ae['_ind2'] ?? ' ' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="author_entry[{{ $idx }}][a]"
                                                       value="{{ $ae['a'] ?? '' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="author_entry[{{ $idx }}][t]"
                                                       value="{{ $ae['t'] ?? '' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="author_entry[{{ $idx }}][e]"
                                                       value="{{ $ae['e'] ?? '' }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- Standard Identifiers (020/022/024/028) --}}
                <section class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Standard Identifiers (020/022/024/028)</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $idf = $formData['identifier_fields'] ?? [];
                            $f020 = $idf['020'] ?? [];
                            $f022 = $idf['022'] ?? [];
                            $f024 = $idf['024'] ?? [];
                            $f028 = $idf['028'] ?? [];
                        @endphp
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">\$020\$a ISBN</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[020][a]"
                                       value="{{ $f020['a'] ?? '' }}"
                                       placeholder="978-0-123-45678-9">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$020\$c Price</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[020][c]"
                                       value="{{ $f020['c'] ?? '' }}"
                                       placeholder="{{ __('R299.00') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$022\$a ISSN</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[022][a]"
                                       value="{{ $f022['a'] ?? '' }}"
                                       placeholder="1234-5678">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$022\$z Invalid ISBN</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[022][z]"
                                       value="{{ $f022['z'] ?? '' }}"
                                       placeholder="{{ __('Invalid ISBN note') }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$024\$a Other Std Number</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[024][a]"
                                       value="{{ $f024['a'] ?? '' }}"
                                       placeholder="">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$024\$2 Source</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[024][2]"
                                       value="{{ $f024['2'] ?? '' }}"
                                       placeholder="{{ __('urn') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">\$028\$a Publisher Number Label</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[028][a]"
                                       value="{{ $f028['a'] ?? '' }}"
                                       placeholder="{{ __('publisher label') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">\$028\$b Publisher Number</label>
                                <input type="text" class="form-control"
                                       name="identifier_fields[028][b]"
                                       value="{{ $f028['b'] ?? '' }}"
                                       placeholder="{{ __('barcode / publisher no.') }}">
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Publication Info --}}
                @if(!empty($formData['publication_info']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Publication Info (260/264)</h5>
                        </div>
                        <div class="card-body">
                            @if(isset($formData['publication_info']['264']))
                                @php $pub = $formData['publication_info']['264']; @endphp
                                <div class="row g-2 mb-2">
                                    <div class="col-md-2"><label class="form-label small">\$a (place)</label>
                                        <input type="text" class="form-control" name="publication_info[264][a]"
                                               value="{{ $pub['a'] ?? '' }}"></div>
                                    <div class="col-md-6"><label class="form-label small">\$b (name)</label>
                                        <input type="text" class="form-control" name="publication_info[264][b]"
                                               value="{{ $pub['b'] ?? '' }}"></div>
                                    <div class="col-md-4"><label class="form-label small">{{ __('Date') }}</label>
                                        <input type="text" class="form-control" name="publication_info[date_1]"
                                               value="{{ $formData == '' ? '' : ($formData['publication_info']['date_1'] ?? '') }}"></div>
                                </div>
                            @else
                                <div class="row g-2 mb-2">
                                    <div class="col-md-2"><label class="form-label small">\$a (place)</label>
                                        <input type="text" class="form-control" name="publication_info[264][a]" value=""></div>
                                    <div class="col-md-6"><label class="form-label small">\$b (name)</label>
                                        <input type="text" class="form-control" name="publication_info[264][b]" value=""></div>
                                    <div class="col-md-4"><label class="form-label small">{{ __('Date') }}</label>
                                        <input type="text" class="form-control" name="publication_info[date_1]" value=""></div>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- RDA Carrier & Content Type (336/337/338) --}}
                <section class="card mb-4">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-layer-group me-2"></i>RDA Carrier & Content Type (336/337/338)</h5>
                    </div>
                    <div class="card-body">
                        @php
                            $rda = $formData['rda_fields'] ?? [];
                            $f336 = $rda[336] ?? [];
                            $f337 = $rda[337] ?? [];
                            $f338 = $rda[338] ?? [];
                        @endphp
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small">\$336\$a Content Type</label>
                                <select class="form-select" name="rda_fields[336][a]">
                                    <option value="">-- select --</option>
                                    @foreach(['text','cartographic image','notated movement','performed music','computer dataset','program','still image','moving image','three-dimensional form','tactile text','tactile notated','tactile image','tactile moving image','tactile three-dimensional form'] as $ct)
                                        <option value="{{ $ct }}" {{ ($f336['a'] ?? '') === $ct ? 'selected' : '' }}>{{ $ct }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$337\$a Carrier Type</label>
                                <select class="form-select" name="rda_fields[337][a]">
                                    <option value="">-- select --</option>
                                    @foreach(['audio cylinder','audio disc','audio roll','audio tape cassette','audio tape reel','computer chip cartridge','computer disc','computer tape cartridge','computer tape cassette','computer tape reel','online resource','microfilm cartridge','microfilm cassette','microfilm reel','microfiche','micro opaque cassette','remote online resource','tape cassette','tape reel','video cassette','video disc','video reel'] as $cr)
                                        <option value="{{ $cr }}" {{ ($f337['a'] ?? '') === $cr ? 'selected' : '' }}>{{ $cr }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">\$338\$a Instance Type</label>
                                <select class="form-select" name="rda_fields[338][a]">
                                    <option value="">-- select --</option>
                                    @foreach(['volume','issue','article','part','collection','sub-unit','single unit','serial'] as $it)
                                        <option value="{{ $it }}" {{ ($f338['a'] ?? '') === $it ? 'selected' : '' }}>{{ $it }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Physical Description --}}
                @if(!empty($formData['physical_description']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-ruler me-2"></i>Physical Description (300/0XX)</h5>
                        </div>
                        <div class="card-body">
                            @if(isset($formData['physical_description']['300']))
                                @php $phys = $formData['physical_description']['300']; @endphp
                                <div class="row g-2">
                                    @foreach(['a','b','c','e','f'] as $code)
                                        @if(isset($phys[$code]))
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label small">\${{ $code }}</label>
                                                <input type="text" class="form-control"
                                                       name="physical_description[300][{{ $code }}]"
                                                       value="{{ $phys[$code] }}">
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                {{-- Subject Access (6XX) --}}
                @if(!empty($formData['subject_access']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-tags me-2"></i>Subject Access (6XX)</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>{{ __('Tag') }}</th><th>{{ __('Ind1') }}</th><th>{{ __('Ind2') }}</th><th>{{ __('\\$a (term)') }}</th><th>{{ __('\\$x (subdivision)') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($formData['subject_access'] as $idx => $sa)
                                        <tr>
                                            <td><code>{{ $sa['tag'] ?? '' }}</code></td>
                                            <td><input type="text" class="form-control form-control-sm font-monospace"
                                                       name="subject_access[{{ $idx }}][_ind1]"
                                                       value="{{ $sa['_ind1'] ?? ' ' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm font-monospace"
                                                       name="subject_access[{{ $idx }}][_ind2]"
                                                       value="{{ $sa['_ind2'] ?? ' ' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="subject_access[{{ $idx }}][a]"
                                                       value="{{ $sa['a'] ?? '' }}"></td>
                                            <td><input type="text" class="form-control form-control-sm"
                                                       name="subject_access[{{ $idx }}][x]"
                                                       value="{{ $sa['x'] ?? '' }}"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- Notes (5XX) --}}
                @if(!empty($formData['notes']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes (5XX)</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>{{ __('Tag') }}</th><th>{{ __('\\$a (content)') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($formData['notes'] as $idx => $note)
                                        <tr>
                                            <td><code>{{ $note['tag'] ?? '' }}</code></td>
                                            <td><textarea class="form-control form-control-sm" rows="2"
                                                          name="notes[{{ $idx }}][a]">{{ $note['a'] ?? '' }}</textarea></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- Electronic Access (856) --}}
                @if(!empty($formData['electronic_access']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Electronic Access (856)</h5>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr><th>{{ __('\\$u (URL)') }}</th><th>{{ __('\\$z (note)') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($formData['electronic_access'] as $idx => $ea)
                                        <tr>
                                            <td>
                                                <input type="url" class="form-control form-control-sm"
                                                       name="electronic_access[{{ $idx }}][u]"
                                                       value="{{ $ea['u'] ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm"
                                                       name="electronic_access[{{ $idx }}][z]"
                                                       value="{{ $ea['z'] ?? '' }}">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif

                {{-- Authority Control: link subject headings --}}
                @if(!empty($formData['subject_access']))
                    <section class="card mb-4">
                        <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Authority Control</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                Link subject headings above to authority records for controlled vocabulary.
                            </p>
                            @php
                                $firstSubject = $formData['subject_access'][0] ?? null;
                                $authorityId = null;
                                if ($firstSubject) {
                                    $match = DB::table('library_subject_authority')
                                        ->where('heading', $firstSubject['a'] ?? '')
                                        ->where('subject_type', 'topic')
                                        ->first();
                                    $authorityId = $match->id ?? null;
                                }
                            @endphp
                            @if($authorityId)
                                <a href="/library-manage/authority/{{ $authorityId }}/link"
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-link me-1"></i>Manage Authority Links
                                </a>
                            @else
                                <a href="/library-manage/authority/create"
                                   class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-plus me-1"></i>Create Subject Authority
                                </a>
                            @endif
                        </div>
                    </section>
                @endif

            </div>{{-- end col-lg-8 --}}

            {{-- RIGHT: actions sidebar --}}
            <div class="col-lg-4">
                <div class="card shadow-sm sticky-top" style="top:1rem;z-index:100">
                    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                        <h5 class="mb-0"><i class="fas fa-save me-2"></i>Actions</h5>
                    </div>
                    <div class="card-body">
                        <button type="submit" class="btn btn-success w-100 mb-2">
                            <i class="fas fa-save me-2"></i>Save MARC Edits
                        </button>
                        <a href="{{ route('library.browse') }}" class="btn atom-btn-white w-100 mb-2">
                            <i class="fas fa-list me-2"></i>Browse Catalogue
                        </a>
                        <hr>
                        <a href="{{ route('library.marc-download', $formData['library_item_id'] ?? 0) }}"
                           class="btn atom-btn-white w-100 mb-2">
                            <i class="fas fa-download me-2"></i>Download MARCXML
                        </a>
                        <a href="{{ route('library.marc-download-binary', $formData['library_item_id'] ?? 0) }}"
                           class="btn atom-btn-white w-100">
                            <i class="fas fa-file me-2"></i>Download MARC Binary
                        </a>
                    </div>
                    <div class="card-footer small text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Edits are applied directly to Heratio fields. Re-export as MARCXML at any time.
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
