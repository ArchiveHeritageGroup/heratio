@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'crossFonds'])@endsection
@section('title-block')
    <h1><i class="fas fa-network-wired me-2"></i>{{ __('Cross-fonds Query') }}</h1>
    <p class="text-muted mb-0">{{ __('Ask one question across multiple fonds. Each fonds is queried in parallel; results are merged and ranked.') }}</p>
@endsection
@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="post" action="{{ route('research.crossFondsQuery') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-bold">{{ __('Query') }}</label>
                <input type="text" name="q" class="form-control form-control-lg" required maxlength="1000"
                       value="{{ e($query) }}" placeholder="{{ __('e.g. every mention of the 1972 strike') }}">
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">{{ __('Fonds') }}</label>
                <div class="border rounded p-2" style="max-height:240px;overflow:auto">
                    @foreach($fondsList as $f)
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="fonds[]" value="{{ (int) $f->id }}"
                                   id="fonds_{{ (int) $f->id }}"
                                   @if(in_array((int) $f->id, $selected, true)) checked @endif>
                            <label class="form-check-label" for="fonds_{{ (int) $f->id }}">
                                {{ e($f->title ?? ('Fonds #' . $f->id)) }}
                                @if($f->identifier) <small class="text-muted">[{{ e($f->identifier) }}]</small>@endif
                                @if($f->repository_name) <span class="badge bg-light text-dark ms-1">{{ e($f->repository_name) }}</span>@endif
                            </label>
                        </div>
                    @endforeach
                </div>
                <div class="form-text">{{ __('Leave all unchecked to search across the 50 most-named fonds.') }}</div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="expand" value="1" id="expand_chk" @if($expand) checked @endif>
                <label class="form-check-label" for="expand_chk">{{ __('Expand with thesaurus synonyms (semantic search)') }}</label>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>{{ __('Run query') }}</button>
        </form>
    </div>
</div>

@if($result)
    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h6 class="mb-0">{{ __('Results') }} ({{ $result['total'] }})</h6>
            <small class="text-muted">{{ $result['elapsed_ms'] }} ms</small>
        </div>
        @if(empty($result['results']))
            <div class="card-body text-muted text-center py-4">{{ __('No matches across the selected fonds.') }}</div>
        @else
            <ul class="list-group list-group-flush" id="studio-citations">
                @foreach($result['results'] as $i => $hit)
                    <li class="list-group-item" data-citation-n="{{ $i + 1 }}">
                        <div class="d-flex justify-content-between">
                            <a href="{{ e($hit['url']) }}" target="_blank" class="fw-semibold text-decoration-none">
                                [{{ $i + 1 }}] {{ e($hit['title']) }}
                            </a>
                            <small class="text-muted">{{ number_format($hit['score'], 2) }}</small>
                        </div>
                        <div class="small text-muted">
                            <span class="badge bg-secondary me-1">{{ e($hit['fonds']) }}</span>
                            {!! $hit['snippet'] ?? '' !!}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
@endsection
