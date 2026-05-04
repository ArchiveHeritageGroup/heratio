@extends('theme::layouts.1col')
@section('title', 'Annotation Studio — ' . ($io->title ?? ''))

@section('content')
  @include('ahg-io-manage::partials.feature-header', [
    'icon' => 'fas fa-highlighter',
    'featureTitle' => 'Annotation Studio',
    'featureDescription' => 'W3C Web Annotations for this archival description',
  ])

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0"><i class="fas fa-sticky-note me-2"></i>{{ __('Annotations') }}
      <span class="badge bg-secondary ms-2">{{ count($annotations ?? []) }}</span>
    </h2>
    @auth
    <button type="button" class="btn atom-btn-outline-success" data-bs-toggle="modal" data-bs-target="#addAnnotationModal">
      <i class="fas fa-plus me-1"></i> {{ __('Add annotation') }}
    </button>
    @endauth
  </div>

  @if(empty($annotations))
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i> {{ __('No annotations recorded for this description.') }}
      @auth
        {{ __('Click "Add annotation" above to create one.') }}
      @endauth
    </div>
  @else
    <div class="row g-3 mb-3">
      @foreach($annotations as $ann)
        @php $a = is_object($ann) ? $ann : (object) $ann; @endphp
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-body">
              @if(!empty($a->title))
                <h6 class="card-title mb-1">{{ $a->title }}</h6>
              @endif
              <div class="small text-muted mb-2">
                @if(!empty($a->researcher_name)){{ $a->researcher_name }} &middot; @endif
                {{ \Carbon\Carbon::parse($a->created_at)->format('Y-m-d H:i') }}
                @if(!empty($a->visibility))
                  <span class="badge bg-{{ $a->visibility === 'public' ? 'success' : ($a->visibility === 'shared' ? 'info' : 'secondary') }} ms-1">{{ ucfirst($a->visibility) }}</span>
                @endif
              </div>
              <p class="card-text mb-2" style="white-space:pre-wrap;">{{ $a->content ?? $a->body ?? '' }}</p>
              @if(!empty($a->tags))
                <div class="small">
                  @foreach(explode(',', $a->tags) as $t)
                    @if(trim($t)!=='')<span class="badge bg-light text-dark me-1">{{ trim($t) }}</span>@endif
                  @endforeach
                </div>
              @endif
            </div>
          </div>
        </div>
      @endforeach
    </div>
  @endif

  @auth
  {{-- Add Annotation modal — posts to the existing research.annotations.store endpoint
       which already handles object_id, visibility, tags, etc. via the V1 research_annotation
       table. Per-record context is set by the hidden object_id field. --}}
  <div class="modal fade" id="addAnnotationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{ route('research.annotations') }}" method="POST">
          @csrf
          <input type="hidden" name="do" value="create">
          <input type="hidden" name="object_id" value="{{ $io->id }}">
          <input type="hidden" name="entity_type" value="information_object">
          <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-sticky-note me-2"></i>{{ __('New Annotation') }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">{{ __('Title') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
              <input type="text" name="title" class="form-control" maxlength="255">
            </div>
            <div class="mb-3">
              <label class="form-label">{{ __('Note') }} <span class="text-danger">*</span></label>
              <textarea name="content" class="form-control" rows="6" required></textarea>
            </div>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">{{ __('Tags') }} <span class="badge bg-secondary ms-1">{{ __('Optional') }}</span></label>
                <input type="text" name="tags" class="form-control" placeholder="comma, separated, tags">
              </div>
              <div class="col-md-6">
                <label class="form-label">{{ __('Visibility') }}</label>
                <select name="visibility" class="form-select">
                  <option value="private" selected>{{ __('Private') }}</option>
                  <option value="shared">{{ __('Shared') }}</option>
                  <option value="public">{{ __('Public') }}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn atom-btn-white" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
            <button type="submit" class="btn atom-btn-outline-success"><i class="fas fa-save me-1"></i>{{ __('Save annotation') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  @endauth
@endsection
