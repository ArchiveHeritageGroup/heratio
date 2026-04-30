@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-journal-whills me-2"></i>Journal Entry</h1><h2>{{ e($entry->title ?: 'Untitled') }}</h2>@endsection
@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">{{ __('Edit Entry') }}</h5>
        <div>
            <span class="text-muted me-3"><i class="fas fa-calendar me-1"></i>{{ $entry->entry_date ?? '' }}</span>
            @if($entry->entry_type ?? null)<span class="badge bg-info">{{ $entry->entry_type }}</span>@endif
        </div>
    </div>
    <div class="card-body">
        <form method="POST">
            @csrf
            <div class="mb-3"><label class="form-label">Title <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><input type="text" class="form-control" name="title" value="{{ e($entry->title ?? '') }}"></div>
            <div class="mb-3"><label class="form-label">Content <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label><textarea class="form-control" name="content" rows="10">{!! e($entry->content ?? '') !!}</textarea></div>
            <div class="row">
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Project') }}</label>
                        <select name="project_id" class="form-select">
                            <option value="">{{ __('None') }}</option>
                            @foreach($projects as $p)
                                <option value="{{ $p->id }}" {{ ($entry->project_id ?? '') == $p->id ? 'selected' : '' }}>{{ e($p->title) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Entry Type') }}</label>
                        <select name="entry_type" class="form-select">
                            @foreach(['manual', 'observation', 'analysis', 'methodology', 'finding', 'auto_annotation', 'auto_search'] as $t)
                                <option value="{{ $t }}" {{ ($entry->entry_type ?? 'manual') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Time (min)') }}</label>
                        <input type="number" class="form-control" name="time_spent_minutes" value="{{ $entry->time_spent_minutes ?? '' }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Date') }}</label>
                        <input type="date" class="form-control" name="entry_date" value="{{ $entry->entry_date ?? '' }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label d-block">&nbsp;</label>
                        <div class="form-check mt-2">
                            <input type="checkbox" name="is_private" value="1" class="form-check-input" id="isPrivate" {{ ($entry->is_private ?? 1) ? 'checked' : '' }}>
                            <label class="form-check-label" for="isPrivate">{{ __('Private') }}</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">{{ __('Tags') }}</label>
                <input type="text" class="form-control" name="tags" value="{{ e($entry->tags ?? '') }}" placeholder="{{ __('Comma-separated') }}">
            </div>
            @if($entry->related_entity_type ?? null)
            <div class="mb-3">
                <small class="text-muted">
                    <i class="fas fa-link me-1"></i>Related: {{ ucfirst(str_replace('_', ' ', $entry->related_entity_type)) }} #{{ $entry->related_entity_id }}
                </small>
            </div>
            @endif
            <div class="d-flex gap-2">
                <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save Changes') }}</button>
                <a href="{{ route('research.journal') }}" class="btn atom-btn-white">Back to Journal</a>
                <button type="submit" name="form_action" value="delete" class="btn atom-btn-outline-danger ms-auto" onclick="return confirm('Delete this entry?')"><i class="fas fa-trash me-1"></i>{{ __('Delete') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
