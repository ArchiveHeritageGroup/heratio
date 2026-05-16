@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'projects'])@endsection
@section('title-block')
    <h1><i class="fas fa-flask me-2"></i>{{ __('Studio') }} - {{ e($project->title) }}</h1>
    <p class="text-muted mb-0">{{ __('Generate grounded research artefacts from this project\'s evidence sets.') }}</p>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-magic me-2"></i>{{ __('New artefact') }}</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('research.studioGenerate', $project->id) }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ __('Output type') }}</label>
                            <select name="output_type" class="form-select" id="studio_output_type" required>
                                @foreach($supportedTypes as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">{{ __('Title (optional)') }}</label>
                            <input type="text" name="title" class="form-control" maxlength="500" placeholder="{{ __('Leave blank to use the default') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">{{ __('Sources') }}</label>
                            @if(count($availableSources) === 0)
                                <div class="alert alert-warning mb-0">
                                    {{ __('This project has no evidence-set items yet. Add items to a collection on the project page first.') }}
                                </div>
                            @else
                                <div class="border rounded p-2" style="max-height:240px;overflow:auto">
                                    @foreach($availableSources as $src)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="source_object_ids[]" value="{{ (int) $src->object_id }}" id="src_{{ (int) $src->object_id }}">
                                            <label class="form-check-label" for="src_{{ (int) $src->object_id }}">
                                                <span class="badge bg-secondary me-1">{{ e($src->collection_name) }}</span>
                                                {{ e($src->title ?? ('IO #' . $src->object_id)) }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="form-text">{{ __('Pick the source items the artefact should be grounded in. Citations [N] in the output will refer back to these.') }}</div>
                            @endif
                        </div>

                        <div class="col-12 d-none" id="spreadsheet_options">
                            <label class="form-label fw-bold">{{ __('Spreadsheet columns') }}</label>
                            <input type="text" name="columns_request" class="form-control" placeholder="date, actor, location, event_summary, source_ref">
                            <div class="form-text">{{ __('Comma-separated. The model will project source content into these columns.') }}</div>
                        </div>

                        <div class="col-12 d-none" id="audio_options">
                            <label class="form-label fw-bold">{{ __('Voice (optional, e.g. f5:my_voice_id)') }}</label>
                            <input type="text" name="voice_id" class="form-control" placeholder="f5:host">
                            <div class="form-text">{{ __('Leave blank for the configured default. TTS endpoint must be set in HERATIO_TTS_ENDPOINT.') }}</div>
                        </div>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"@if(count($availableSources)===0) disabled @endif>
                            <i class="fas fa-cogs me-1"></i>{{ __('Generate') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Recent artefacts') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($artefacts))
                    <div class="text-muted text-center py-4">{{ __('No artefacts yet.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($artefacts as $a)
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <a href="{{ route('research.studioShow', ['projectId' => $project->id, 'artefactId' => $a->id]) }}" class="fw-semibold text-decoration-none">
                                        {{ e($a->title ?: $supportedTypes[$a->output_type] ?? $a->output_type) }}
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        {{ $supportedTypes[$a->output_type] ?? $a->output_type }}
                                        - {{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}
                                    </small>
                                </div>
                                <span class="badge bg-{{ ['ready' => 'success', 'generating' => 'warning', 'error' => 'danger', 'pending' => 'secondary'][$a->status] ?? 'dark' }}">
                                    {{ ucfirst($a->status) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@push('js')
<script>
(function () {
    var sel = document.getElementById('studio_output_type');
    var spreadsheetBlock = document.getElementById('spreadsheet_options');
    var audioBlock = document.getElementById('audio_options');
    function refresh() {
        spreadsheetBlock.classList.toggle('d-none', sel.value !== 'spreadsheet');
        audioBlock.classList.toggle('d-none', sel.value !== 'audio');
    }
    sel.addEventListener('change', refresh);
    refresh();
})();
</script>
@endpush
@endsection
