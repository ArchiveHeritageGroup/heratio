{{-- Research Outputs register - create / edit form (heratio#1222) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@php $isEdit = ! empty($output); @endphp

@section('title', $isEdit ? __('Edit Research Output') : __('New Research Output'))

@section('content')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.outputs.index', $project->id ?? 0) }}">{{ __('Research Outputs') }}</a></li>
        <li class="breadcrumb-item active">{{ $isEdit ? __('Edit') : __('New') }}</li>
    </ol>
</nav>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3"><i class="fas fa-book text-primary me-2"></i>{{ $isEdit ? __('Edit Research Output') : __('New Research Output') }}</h1>
    <a href="{{ route('research.outputs.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
</div>

<form method="POST" action="{{ $isEdit ? route('research.outputs.update', [$project->id ?? 0, $output['id']]) : route('research.outputs.store', $project->id ?? 0) }}" autocomplete="off">
    @csrf
    @if($isEdit)@method('PUT')@endif

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Output details') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="512" required value="{{ old('title', $output['title'] ?? '') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Type') }} <span class="text-danger">*</span></label>
                    <select name="output_type" class="form-select" required>
                        @foreach($typeOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('output_type', $output['output_type'] ?? 'journal_article') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Authors') }}</label>
                    <input type="text" name="authors" class="form-control" maxlength="1024" value="{{ old('authors', $output['authors'] ?? '') }}" placeholder="{{ __('e.g. Surname, A.; Surname, B.') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Date') }}</label>
                    <input type="date" name="output_date" class="form-control" value="{{ old('output_date', $output['output_date'] ?? '') }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">{{ __('Venue') }}</label>
                    <input type="text" name="venue" class="form-control" maxlength="512" value="{{ old('venue', $output['venue'] ?? '') }}" placeholder="{{ __('journal, conference, repository or publisher') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }} <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('status', $output['status'] ?? 'planned') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Persistent identifier') }}</h6></div>
        <div class="card-body">
            <p class="text-muted small">{{ __('A DOI, handle, ISBN or URL makes the output citable and resolvable. The identifier is turned into a link automatically (a DOI resolves to https://doi.org/...). To override the link, set an explicit URL.') }}</p>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">{{ __('Identifier type') }}</label>
                    <select name="identifier_type" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach($identifierOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected(old('identifier_type', $output['identifier_type'] ?? '') === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Identifier') }}</label>
                    <input type="text" name="identifier" class="form-control" maxlength="512" value="{{ old('identifier', $output['identifier'] ?? '') }}" placeholder="{{ __('e.g. 10.1234/abcd') }}">
                </div>
                <div class="col-md-5">
                    <label class="form-label">{{ __('Explicit URL (optional)') }}</label>
                    <input type="url" name="identifier_url" class="form-control" maxlength="1024" value="{{ old('identifier_url', $output['identifier_url'] ?? '') }}" placeholder="{{ __('https://...') }}">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><h6 class="mb-0">{{ __('Notes and links') }}</h6></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">{{ __('Abstract / notes') }}</label>
                    <textarea name="notes" class="form-control" rows="4" maxlength="65000">{{ old('notes', $output['notes'] ?? '') }}</textarea>
                </div>
                @if(! empty($dmpOptions))
                <div class="col-md-6">
                    <label class="form-label">{{ __('Linked data management plan (optional)') }}</label>
                    <select name="dmp_id" class="form-select">
                        <option value="">{{ __('None') }}</option>
                        @foreach($dmpOptions as $id => $label)
                            <option value="{{ $id }}" @selected((int) old('dmp_id', $output['dmp_id'] ?? 0) === (int) $id)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">{{ __('Link this output to the project data management plan that governs its data.') }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mb-5">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ $isEdit ? __('Save output') : __('Create output') }}</button>
        <a href="{{ route('research.outputs.index', $project->id ?? 0) }}" class="btn btn-outline-secondary">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
