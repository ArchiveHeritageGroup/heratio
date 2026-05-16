@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'notebooks'])@endsection
@section('title-block')
    <h1><i class="fas fa-book me-2"></i>{{ e($notebook->title) }}</h1>
    @if($notebook->summary)<p class="text-muted mb-0">{{ e($notebook->summary) }}</p>@endif
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="d-flex justify-content-between mb-3">
    <a href="{{ route('research.notebooks') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>{{ __('All notebooks') }}</a>
    <div>
        @if($notebook->promoted_to_project_id)
            <a href="{{ route('research.viewProject', $notebook->promoted_to_project_id) }}" class="btn btn-sm btn-outline-success">
                <i class="fas fa-folder-open me-1"></i>{{ __('Open promoted project') }}
            </a>
        @else
            <form method="post" action="{{ route('research.notebookPromote', $notebook->id) }}" class="d-inline" onsubmit="return confirm('Promote this notebook to a public research project? This action cannot be undone.')">
                @csrf
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-rocket me-1"></i>{{ __('Promote to project') }}</button>
            </form>
        @endif
        <form method="post" action="{{ route('research.notebookDelete', $notebook->id) }}" class="d-inline" onsubmit="return confirm('Delete this notebook and all its items?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0">{{ __('Items') }} ({{ count($items) }})</h6></div>
            <div class="card-body p-0">
                @if(empty($items))
                    <div class="text-muted text-center py-4">{{ __('No items yet.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($items as $it)
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-secondary me-1">{{ str_replace('_', ' ', $it->item_type) }}</span>
                                        @if($it->title)<strong>{{ e($it->title) }}</strong>@endif
                                        @if($it->source_object_id)
                                            <a href="/{{ $it->source_object_id }}" target="_blank" class="ms-2 small">{{ __('View source') }}<i class="fas fa-external-link-alt ms-1"></i></a>
                                        @endif
                                    </div>
                                    <div>
                                        <form method="post" action="{{ route('research.notebookShow', $notebook->id) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="form_action" value="pin_item">
                                            <input type="hidden" name="item_id" value="{{ $it->id }}">
                                            <input type="hidden" name="pinned" value="{{ $it->pinned ? 0 : 1 }}">
                                            <button class="btn btn-sm btn-link p-0" title="{{ $it->pinned ? 'Unpin' : 'Pin' }}"><i class="fas fa-thumbtack {{ $it->pinned ? 'text-warning' : 'text-muted' }}"></i></button>
                                        </form>
                                        <form method="post" action="{{ route('research.notebookShow', $notebook->id) }}" class="d-inline" onsubmit="return confirm('Remove this item?')">
                                            @csrf
                                            <input type="hidden" name="form_action" value="remove_item">
                                            <input type="hidden" name="item_id" value="{{ $it->id }}">
                                            <button class="btn btn-sm btn-link p-0 text-danger" title="Remove"><i class="fas fa-times"></i></button>
                                        </form>
                                    </div>
                                </div>
                                @if($it->body)
                                    <div class="small text-muted mt-1">{!! nl2br(e(\Illuminate\Support\Str::limit($it->body, 400))) !!}</div>
                                @endif
                                @if($it->ai_output_payload)
                                    <details class="mt-1"><summary class="small text-muted">{{ __('AI output payload') }}</summary><pre class="small bg-light p-2 mt-1" style="max-height:200px;overflow:auto">{{ $it->ai_output_payload }}</pre></details>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus me-2"></i>{{ __('Add note') }}</h6></div>
            <div class="card-body">
                <form method="post" action="{{ route('research.notebookShow', $notebook->id) }}">
                    @csrf
                    <input type="hidden" name="form_action" value="add_item">
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Type') }}</label>
                        <select name="item_type" class="form-select form-select-sm">
                            @foreach($itemTypes as $t)
                                <option value="{{ $t }}">{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Title') }}</label>
                        <input type="text" name="item_title" class="form-control form-control-sm" maxlength="500">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Body') }}</label>
                        <textarea name="item_body" rows="4" class="form-control form-control-sm"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">{{ __('Source object id (optional)') }}</label>
                        <input type="number" name="source_object_id" class="form-control form-control-sm">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="pinned" value="1" id="pin_chk">
                        <label class="form-check-label small" for="pin_chk">{{ __('Pin to top') }}</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">{{ __('Add') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
