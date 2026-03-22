@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-book me-2"></i>{{ e($bibliography->name) }}</h1>@endsection
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h5 class="card-title mb-1">{{ e($bibliography->name) }}</h5>
                @if($bibliography->description ?? null)<p class="text-muted mb-1">{{ e($bibliography->description) }}</p>@endif
                <small class="text-muted"><i class="fas fa-quote-left me-1"></i>Citation Style: <strong>{{ ucfirst($bibliography->citation_style ?? 'chicago') }}</strong></small>
            </div>
            <div class="btn-group">
                <button class="btn atom-btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addEntryModal"><i class="fas fa-plus me-1"></i>Add Entry</button>
                <a href="{{ route('research.viewBibliography', $bibliography->id) }}?export=1" class="btn atom-btn-white btn-sm"><i class="fas fa-file-export me-1"></i>Export</a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this bibliography and all its entries?')">
                    @csrf
                    <input type="hidden" name="form_action" value="delete">
                    <button type="submit" class="btn atom-btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Entries ({{ count($entries) }})</h5>
    </div>
    <div class="card-body p-0">
        @if(count($entries) > 0)
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped mb-0">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Authors</th>
                        <th>Year</th>
                        <th>Type</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                    <tr>
                        <td>
                            {{ e($entry->title ?? 'Untitled') }}
                            @if($entry->doi ?? null)<br><small class="text-muted">DOI: {{ e($entry->doi) }}</small>@endif
                        </td>
                        <td>{{ e($entry->authors ?? '-') }}</td>
                        <td>{{ e($entry->year ?? '-') }}</td>
                        <td>
                            @if($entry->entry_type ?? null)
                            <span class="badge bg-secondary">{{ ucfirst($entry->entry_type) }}</span>
                            @else
                            -
                            @endif
                        </td>
                        <td class="text-end">
                            <form method="POST" class="d-inline" onsubmit="return confirm('Remove this entry?')">
                                @csrf
                                <input type="hidden" name="form_action" value="remove_entry">
                                <input type="hidden" name="entry_id" value="{{ $entry->id }}">
                                <button type="submit" class="btn atom-btn-outline-danger btn-sm"><i class="fas fa-times"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-list fa-3x mb-3 d-block"></i>
            No entries yet. Add entries to build your bibliography.
        </div>
        @endif
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('research.bibliographies') }}" class="btn atom-btn-white btn-sm"><i class="fas fa-arrow-left me-1"></i>Back to Bibliographies</a>
</div>

{{-- Add Entry Modal --}}
<div class="modal fade" id="addEntryModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="add_entry">
    <div class="modal-header"><h5 class="modal-title">Add Bibliography Entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span> <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" name="title" required></div>
        <div class="row">
            <div class="col-md-8"><div class="mb-3"><label class="form-label">Authors <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="authors" placeholder="Last, First; Last, First"></div></div>
            <div class="col-md-4"><div class="mb-3"><label class="form-label">Year <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="year" placeholder="e.g. 2024"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">Publication <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="publication"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Volume <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="volume"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Pages <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="pages" placeholder="e.g. 1-25"></div></div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">DOI <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="doi" placeholder="10.xxxx/xxxxx"></div></div>
            <div class="col-md-6"><div class="mb-3"><label class="form-label">URL <span class="badge bg-secondary ms-1">Optional</span></label><input type="url" class="form-control" name="url" placeholder="https://..."></div></div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Entry Type <span class="badge bg-secondary ms-1">Optional</span></label>
                    <select name="entry_type" class="form-select">
                        <option value="book">Book</option>
                        <option value="article">Article</option>
                        <option value="chapter">Chapter</option>
                        <option value="website">Website</option>
                        <option value="thesis">Thesis</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Archive Item ID <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" name="object_id" placeholder="Link to archive item (optional)"></div>
            </div>
        </div>
        <div class="mb-3"><label class="form-label">Notes <span class="badge bg-secondary ms-1">Optional</span></label><textarea class="form-control" name="notes" rows="2"></textarea></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Add Entry</button></div>
    </form>
</div></div></div>
@endsection
