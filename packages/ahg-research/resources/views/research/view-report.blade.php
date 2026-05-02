@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'reports'])@endsection
@section('title', $report->title ?? 'Report')

@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.reports') }}">Reports</a></li>
        <li class="breadcrumb-item active">{{ e($report->title) }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="fas fa-file-alt text-primary me-2"></i>{{ e($report->title) }}</h1>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fas fa-file-export me-1"></i>{{ __('Export') }}</button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="{{ route('research.viewReport', $report->id) }}?export=pdf"><i class="fas fa-file-pdf me-2 text-danger"></i>PDF</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        {{-- Report Header --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="POST" class="d-flex align-items-center gap-3 mb-2">
                    @csrf
                    <input type="hidden" name="form_action" value="update_status">
                    <div class="flex-grow-1">
                        <label class="form-label small text-muted mb-0">{{ __('Status') }}</label>
                        <select name="status" class="form-select form-select-sm" data-csp-auto-submit>
                            @foreach(['draft' => 'Draft', 'in_progress' => 'In Progress', 'review' => 'Review', 'completed' => 'Completed'] as $sKey => $sLabel)
                                <option value="{{ $sKey }}" {{ ($report->status ?? 'draft') === $sKey ? 'selected' : '' }}>{{ $sLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label small text-muted mb-0">{{ __('Template') }}</label>
                        @php $tplColors = ['research_summary'=>'primary','genealogical'=>'success','historical'=>'info','source_analysis'=>'warning','finding_aid'=>'secondary']; @endphp
                        <span class="badge bg-{{ $tplColors[$report->template_type ?? 'custom'] ?? 'dark' }} d-block mt-1">{{ ucwords(str_replace('_', ' ', $report->template_type ?? 'custom')) }}</span>
                    </div>
                </form>
                @if($report->description ?? null)
                    <p class="text-muted mb-0">{{ e($report->description) }}</p>
                @endif
            </div>
        </div>

        {{-- Sections --}}
        <div id="reportSections">
        @forelse($report->sections as $index => $section)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-secondary">{{ ucwords(str_replace('_', ' ', $section->section_type ?? 'text')) }}</span>
                        <strong>{{ e($section->title ?? 'Untitled Section') }}</strong>
                    </div>
                    <div class="d-flex gap-1">
                        @if($index > 0)
                        <form method="POST" class="d-inline">@csrf<input type="hidden" name="form_action" value="move_section"><input type="hidden" name="section_id" value="{{ $section->id }}"><input type="hidden" name="direction" value="up"><button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Move Up') }}"><i class="fas fa-arrow-up"></i></button></form>
                        @endif
                        @if($index < count($report->sections) - 1)
                        <form method="POST" class="d-inline">@csrf<input type="hidden" name="form_action" value="move_section"><input type="hidden" name="section_id" value="{{ $section->id }}"><input type="hidden" name="direction" value="down"><button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Move Down') }}"><i class="fas fa-arrow-down"></i></button></form>
                        @endif
                        <button type="button" class="btn btn-sm btn-outline-primary edit-section-btn" data-id="{{ $section->id }}" title="{{ __('Edit') }}"><i class="fas fa-edit"></i></button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this section?')">@csrf<input type="hidden" name="form_action" value="delete_section"><input type="hidden" name="section_id" value="{{ $section->id }}"><button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button></form>
                    </div>
                </div>
                {{-- Display --}}
                <div class="card-body section-display" id="display-{{ $section->id }}">
                    @if($section->content ?? null)
                        @if(($section->content_format ?? 'html') === 'html')
                            {!! $section->content !!}
                        @else
                            <p>{{ e($section->content) }}</p>
                        @endif
                    @else
                        <p class="text-muted fst-italic">Click edit to add content to this section.</p>
                    @endif
                </div>
                {{-- Inline Edit (hidden) --}}
                <div class="card-body section-edit d-none" id="edit-{{ $section->id }}">
                    <form method="POST">
                        @csrf
                        <input type="hidden" name="form_action" value="update_section">
                        <input type="hidden" name="section_id" value="{{ $section->id }}">
                        <div class="mb-2"><input type="text" name="title" class="form-control form-control-sm" value="{{ e($section->title ?? '') }}" placeholder="{{ __('Section title...') }}"></div>
                        <div class="mb-2"><textarea name="content" class="form-control" rows="10">{{ e($section->content ?? '') }}</textarea></div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
                            <button type="button" class="btn btn-sm btn-secondary cancel-edit-btn" data-id="{{ $section->id }}">Cancel</button>
                        </div>
                    </form>
                </div>
                {{-- Section Comments --}}
                <div class="card-footer bg-transparent">
                    <details>
                        <summary class="text-muted small" style="cursor:pointer;"><i class="fas fa-comments me-1"></i>Comments</summary>
                        <div class="mt-2">
                            <form method="POST" class="mt-2">
                                @csrf
                                <input type="hidden" name="form_action" value="add_comment">
                                <input type="hidden" name="section_id" value="{{ $section->id }}">
                                <div class="input-group input-group-sm">
                                    <input type="text" name="comment_content" class="form-control" placeholder="{{ __('Add a comment...') }}">
                                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        @empty
            <div class="text-center py-4 text-muted">
                <i class="fas fa-file fa-2x mb-2 opacity-50"></i>
                <p>No sections yet. Add a section to start building your report.</p>
            </div>
        @endforelse
        </div>

        {{-- Add Section (tabbed) --}}
        <div class="card border-dashed mb-4">
            <div class="card-body">
                <ul class="nav nav-tabs nav-tabs-sm mb-3" role="tablist">
                    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#addSingleSection">Add Section</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#addFromTemplate">Load Template</a></li>
                    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#addMultipleSections">Add Multiple</a></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="addSingleSection">
                        <form method="POST" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="form_action" value="add_section">
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('Section Type') }}</label>
                                <select name="section_type" class="form-select form-select-sm">
                                    @foreach(['text'=>'Text','heading'=>'Heading','title_page'=>'Title Page','toc'=>'Table of Contents','bibliography'=>'Bibliography','collection_list'=>'Collection List','annotation_list'=>'Annotation List','timeline'=>'Timeline','custom'=>'Custom'] as $tk=>$tl)
                                    <option value="{{ $tk }}">{{ $tl }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-5"><label class="form-label small">{{ __('Title') }}</label><input type="text" name="title" class="form-control form-control-sm" placeholder="{{ __('Section title...') }}"></div>
                            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="fas fa-plus me-1"></i>{{ __('Add') }}</button></div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="addFromTemplate">
                        <form method="POST">
                            @csrf
                            <input type="hidden" name="form_action" value="load_template">
                            <p class="small text-muted mb-2">Load sections from a template. Existing sections are kept.</p>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-8">
                                    <select name="template_code" class="form-select form-select-sm">
                                        <option value="research_summary">{{ __('Research Summary') }}</option>
                                        <option value="genealogical">{{ __('Genealogical Report') }}</option>
                                        <option value="historical">{{ __('Historical Analysis') }}</option>
                                        <option value="source_analysis">{{ __('Source Analysis') }}</option>
                                        <option value="finding_aid">{{ __('Finding Aid') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-4"><button type="submit" class="btn btn-sm btn-outline-success w-100"><i class="fas fa-layer-group me-1"></i>{{ __('Load Sections') }}</button></div>
                            </div>
                        </form>
                    </div>
                    <div class="tab-pane fade" id="addMultipleSections">
                        <form method="POST">
                            @csrf
                            <input type="hidden" name="form_action" value="add_multiple">
                            <p class="small text-muted mb-2">Select multiple section types to add at once.</p>
                            <div class="row g-2 mb-2">
                                @foreach(['title_page'=>'Title Page','toc'=>'Table of Contents','heading'=>'Heading','text'=>'Text','bibliography'=>'Bibliography','collection_list'=>'Collection List','annotation_list'=>'Annotation List','timeline'=>'Timeline'] as $tk=>$tl)
                                <div class="col-auto"><div class="form-check"><input type="checkbox" name="section_types[]" value="{{ $tk }}" class="form-check-input" id="multi_{{ $tk }}"><label class="form-check-label small" for="multi_{{ $tk }}">{{ $tl }}</label></div></div>
                                @endforeach
                            </div>
                            <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus-circle me-1"></i>{{ __('Add Selected') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        {{-- Report Info --}}
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Report Info') }}</h6></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('Author') }}</span><span>{{ e(($researcher->first_name ?? '') . ' ' . ($researcher->last_name ?? '')) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('Created') }}</span><span>{{ date('M j, Y', strtotime($report->created_at)) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('Updated') }}</span><span>{{ date('M j, Y H:i', strtotime($report->updated_at)) }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span class="text-muted">{{ __('Sections') }}</span><span class="badge bg-primary">{{ count($report->sections) }}</span></li>
            </ul>
        </div>

        {{-- Delete Report --}}
        <div class="card border-danger">
            <div class="card-body">
                <h6 class="card-title text-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete Report') }}</h6>
                <p class="small text-muted">This will permanently delete the report and all its sections.</p>
                <form method="POST" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                    @csrf
                    <input type="hidden" name="form_action" value="delete_report">
                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash me-1"></i>{{ __('Delete Report') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.edit-section-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            document.getElementById('display-' + id).classList.add('d-none');
            document.getElementById('edit-' + id).classList.remove('d-none');
        });
    });
    document.querySelectorAll('.cancel-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.dataset.id;
            document.getElementById('display-' + id).classList.remove('d-none');
            document.getElementById('edit-' + id).classList.add('d-none');
        });
    });
});
</script>
@endpush
@endsection
