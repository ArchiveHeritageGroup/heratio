@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'journal'])@endsection
@section('title-block')
<div class="d-flex justify-content-between align-items-center">
  <h1><i class="fas fa-book text-primary me-2"></i>Research Journal</h1>
  <div class="d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-file-export me-1"></i>Export Journal
      </button>
      <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="{{ url('/research/export-journal?format=pdf') }}"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
        <li><a class="dropdown-item" href="{{ url('/research/export-journal?format=docx') }}"><i class="fas fa-file-word me-2 text-primary"></i>DOCX</a></li>
      </ul>
    </div>
    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newEntryModal">
      <i class="fas fa-plus me-1"></i>New Entry
    </button>
  </div>
</div>
@endsection
@section('content')
<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
    <li class="breadcrumb-item active">Journal</li>
  </ol>
</nav>
<div class="card mb-3">
    <div class="card-body">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="q" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search...') }}"></div>
            <div class="col-md-2">
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($projects as $p)<option value="{{ $p->id }}" {{ ($filters['project_id'] ?? '') == $p->id ? 'selected' : '' }}>{{ e($p->title) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-md-2"><input type="date" class="form-control form-control-sm" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-md-1"><button type="submit" class="btn btn-outline-secondary btn-sm w-100"><i class="fas fa-filter"></i></button></div>
        </form>
    </div>
</div>

@php $grouped = collect($entries)->groupBy('entry_date'); @endphp
@forelse($grouped as $date => $dayEntries)
<div class="mb-3">
    <h6 class="text-muted border-bottom pb-1"><i class="fas fa-calendar-day me-2"></i>{{ \Carbon\Carbon::parse($date)->format('l, j F Y') }}</h6>
    @foreach($dayEntries as $entry)
    <div class="card mb-2">
        <div class="card-body py-2">
            <div class="d-flex justify-content-between">
                <div>
                    <a href="{{ route('research.journalEntry', $entry->id) }}" class="fw-bold text-decoration-none">{{ e($entry->title ?: 'Untitled Entry') }}</a>
                    @if($entry->entry_type ?? null)<span class="badge bg-info ms-2">{{ $entry->entry_type }}</span>@endif
                    @if($entry->tags ?? null)@foreach(explode(',', $entry->tags) as $tag)<span class="badge bg-secondary ms-1">{{ trim($tag) }}</span>@endforeach @endif
                </div>
                @if($entry->time_spent_minutes ?? null)<small class="text-muted"><i class="fas fa-clock me-1"></i>{{ $entry->time_spent_minutes }}m</small>@endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@empty
<div class="text-center text-muted py-4"><i class="fas fa-journal-whills fa-3x mb-3 d-block"></i>No journal entries yet. Start documenting your research!</div>
@endforelse

<div class="modal fade" id="newEntryModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST">@csrf <input type="hidden" name="do" value="create">
    <div class="modal-header"><h5 class="modal-title">{{ __('New Journal Entry') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Title <span class="badge bg-danger ms-1">Required</span></label><input type="text" class="form-control" name="title"></div>
        <div class="mb-3"><label class="form-label">Content <span class="badge bg-danger ms-1">Required</span></label><textarea class="form-control" name="content" rows="6" required></textarea></div>
        <div class="row">
            <div class="col-md-4"><div class="mb-3"><label class="form-label">Project <span class="badge bg-secondary ms-1">Optional</span></label><select name="project_id" class="form-select"><option value="">{{ __('None') }}</option>@foreach($projects as $p)<option value="{{ $p->id }}">{{ e($p->title) }}</option>@endforeach</select></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Type <span class="badge bg-secondary ms-1">Optional</span></label><select name="entry_type" class="form-select"><option value="manual">{{ __('Manual') }}</option><option value="observation">{{ __('Observation') }}</option><option value="finding">{{ __('Finding') }}</option><option value="reflection">{{ __('Reflection') }}</option></select></div></div>
            <div class="col-md-2"><div class="mb-3"><label class="form-label">Time (min) <span class="badge bg-secondary ms-1">Optional</span></label><input type="number" class="form-control" name="time_spent_minutes"></div></div>
            <div class="col-md-3"><div class="mb-3"><label class="form-label">Date <span class="badge bg-secondary ms-1">Optional</span></label><input type="date" class="form-control" name="entry_date" value="{{ date('Y-m-d') }}"></div></div>
        </div>
        <div class="mb-3"><label class="form-label">Tags <span class="badge bg-secondary ms-1">Optional</span></label><input type="text" class="form-control" name="tags" placeholder="{{ __('Comma-separated') }}"></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Entry</button></div>
    </form>
</div></div></div>
@endsection
