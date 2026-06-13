{{-- Grant Engine - section-by-section draft editor (heratio#1239) --}}
@extends('theme::layouts.2col')

@section('sidebar')
    @include('research::research._sidebar', ['sidebarActive' => 'projects'])
@endsection

@section('title', __('Edit Grant Draft'))

@section('content')
@php
    $currentStatus = $draft['status'] ?? 'draft';
@endphp

<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">{{ __('Research') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.viewProject', $project->id ?? 0) }}">{{ e($project->title ?? '') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('research.grant.index', $project->id ?? 0) }}">{{ __('Grant Engine') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Edit') }}</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('research.grant.update', [$project->id ?? 0, $draft['id']]) }}" id="grantDraftForm" autocomplete="off">
    @csrf
    @method('PUT')

    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h2 mb-1"><i class="fas fa-hand-holding-usd text-primary me-2"></i>{{ __('Grant Draft') }}</h1>
            @if(!empty($template))
                <span class="badge bg-light text-dark border">{{ e($template['name']) }}</span>
                @if(!empty($template['funder']))<span class="text-muted small ms-1">{{ e($template['funder']) }}</span>@endif
            @endif
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save me-1"></i>{{ __('Save') }}</button>
            <a href="{{ route('research.grant.show', [$project->id ?? 0, $draft['id']]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-eye me-1"></i>{{ __('View') }}</a>
            <a href="{{ route('research.grant.index', $project->id ?? 0) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>{{ __('Back') }}</a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">{{ __('Draft title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="255" required value="{{ e($draft['title'] ?? '') }}" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label">{{ __('Status') }}</label>
                    <select name="status" class="form-select">
                        @foreach($statusOptions as $code => $label)
                            <option value="{{ e($code) }}" @selected($currentStatus === $code)>{{ e($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    @if(empty($sections))
        <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>{{ __('This draft has no sections. The funder template may have changed - start a fresh draft to pick up the latest section list.') }}</div>
    @else
        {{-- Sections --}}
        @foreach($sections as $s)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>{{ e($s['label']) }}</strong>
                <button type="button" class="btn btn-outline-secondary btn-sm js-ai-draft"
                    data-section-id="{{ $s['id'] }}"
                    data-section-key="{{ e($s['section_key']) }}"
                    data-section-label="{{ e($s['label']) }}">
                    <i class="fas fa-robot me-1"></i>{{ __('AI draft') }}
                </button>
            </div>
            <div class="card-body">
                <textarea name="sections[{{ $s['id'] }}]" id="section-{{ $s['id'] }}" class="form-control" rows="6">{{ $s['body'] ?? '' }}</textarea>
                <div class="js-ai-status small mt-2" data-for="{{ $s['id'] }}"></div>
            </div>
        </div>
        @endforeach
    @endif

    <div class="d-flex gap-2 my-4">
        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save draft') }}</button>
        <a href="{{ route('research.grant.show', [$project->id ?? 0, $draft['id']]) }}" class="btn btn-outline-secondary"><i class="fas fa-eye me-1"></i>{{ __('View / print') }}</a>
    </div>
</form>

@push('js')
<script>
(function () {
    var aiUrl = "{{ route('research.grant.ai-draft', [$project->id ?? 0, $draft['id']]) }}";
    var token = "{{ csrf_token() }}";
    var lblAssist = @json(__('AI-assisted suggestion - review and edit before use. Replace the section text?'));
    var lblWorking = @json(__('Asking the AI gateway...'));
    var lblFail = @json(__('AI drafting is unavailable. You can keep writing by hand.'));

    document.querySelectorAll('.js-ai-draft').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var sid = btn.getAttribute('data-section-id');
            var ta = document.getElementById('section-' + sid);
            var status = document.querySelector('.js-ai-status[data-for="' + sid + '"]');
            if (!ta) { return; }
            btn.disabled = true;
            if (status) { status.className = 'js-ai-status small mt-2 text-muted'; status.textContent = lblWorking; }

            fetch(aiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({
                    section_key: btn.getAttribute('data-section-key'),
                    section_label: btn.getAttribute('data-section-label'),
                    current_text: ta.value
                })
            }).then(function (r) { return r.json(); }).then(function (data) {
                btn.disabled = false;
                if (data && data.ok && data.text) {
                    if (window.confirm(lblAssist)) {
                        ta.value = data.text;
                    }
                    if (status) { status.className = 'js-ai-status small mt-2 text-success'; status.textContent = data.label || ''; }
                } else {
                    if (status) { status.className = 'js-ai-status small mt-2 text-warning'; status.textContent = (data && data.error) ? data.error : lblFail; }
                }
            }).catch(function () {
                btn.disabled = false;
                if (status) { status.className = 'js-ai-status small mt-2 text-warning'; status.textContent = lblFail; }
            });
        });
    });
})();
</script>
@endpush
@endsection
