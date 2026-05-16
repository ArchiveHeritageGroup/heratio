@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'notebooks'])@endsection
@section('title-block')
    <h1><i class="fas fa-book me-2"></i>{{ __('My Notebooks') }}</h1>
    <p class="text-muted mb-0">{{ __('Private scratchpads for saved queries, AI outputs and pinned sources. Promote to a public project when ready.') }}</p>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="row">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">{{ __('Existing notebooks') }}</h6></div>
            <div class="card-body p-0">
                @if(empty($notebooks))
                    <div class="text-muted text-center py-4">{{ __('No notebooks yet. Create one on the right.') }}</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($notebooks as $nb)
                            <li class="list-group-item d-flex justify-content-between align-items-start">
                                <div>
                                    <a class="fw-semibold text-decoration-none" href="{{ route('research.notebookShow', $nb->id) }}">{{ e($nb->title) }}</a>
                                    @if($nb->promoted_to_project_id)
                                        <span class="badge bg-success ms-2">{{ __('Promoted') }}</span>
                                    @endif
                                    @if($nb->summary)
                                        <div class="small text-muted">{{ e(\Illuminate\Support\Str::limit($nb->summary, 160)) }}</div>
                                    @endif
                                </div>
                                <small class="text-muted">{{ \Carbon\Carbon::parse($nb->updated_at)->diffForHumans() }}</small>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-plus me-2"></i>{{ __('New notebook') }}</h6></div>
            <div class="card-body">
                <form method="post" action="{{ route('research.notebooks') }}">
                    @csrf
                    <input type="hidden" name="form_action" value="create">
                    <div class="mb-3">
                        <label class="form-label">{{ __('Title') }}</label>
                        <input type="text" name="title" class="form-control" required maxlength="255">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Summary') }}</label>
                        <textarea name="summary" rows="3" class="form-control" maxlength="2000"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-book me-1"></i>{{ __('Create') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
