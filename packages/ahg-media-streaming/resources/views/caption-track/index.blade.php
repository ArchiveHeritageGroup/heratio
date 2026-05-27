@extends('theme::layouts.3col')

@section('title', "Caption Tracks — {$doName}")
@section('body-class', 'caption-tracks')

@section('sidebar')
    @include('ahg-menu-manage::_static-pages-menu')
@endsection

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-closed-captioning me-2"></i>Caption & Subtitle Tracks</h1>
    <a href="{{ route('caption-tracks.create', $digitalObjectId) }}" class="btn atom-btn-white">
        <i class="fas fa-plus me-1"></i>Add Track
    </a>
</div>

<p class="text-muted">
    Digital object: <strong>{{ $doName }}</strong>
    @if($tracks->count())
        &mdash; {{ $tracks->count() }} track(s) configured
    @else
        &mdash; no tracks configured
    @endif
</p>

@if(session('success'))
    <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i>{{ session('error') }}</div>
@endif

@if($tracks->isEmpty())

    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-closed-captioning fa-3x text-muted mb-3 d-block"></i>
            <p class="text-muted mb-3">No caption or subtitle tracks have been added yet.</p>
            <a href="{{ route('caption-tracks.create', $digitalObjectId) }}" class="btn atom-btn-white">
                <i class="fas fa-plus me-1"></i>Add your first track
            </a>
        </div>
    </div>

@else

    @foreach(['caption', 'subtitle', 'description', 'chapters'] as $type)
        @if(isset($grouped[$type]) && count($grouped[$type]))
            <div class="card mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <div class="d-flex justify-content-between align-items-center">
                        <span>
                            <i class="fas fa-{{ $type === 'description' ? 'audio-description' : 'closed-captioning' }} me-1"></i>
                            {{ ucfirst($type == 'description' ? 'Audio Description' : ($type == 'chapters' ? 'Chapter Markers' : ucfirst($type))) }}
                            <span class="badge bg-light text-dark ms-1">{{ count($grouped[$type]) }}</span>
                        </span>
                        <a href="{{ route('caption-tracks.create', $digitalObjectId) }}?type={{ $type }}" class="btn btn-sm atom-btn-white">
                            <i class="fas fa-plus me-1"></i>Add {{ ucfirst($type) }}
                        </a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Language</th>
                                <th>SDH</th>
                                <th>Default</th>
                                <th>Source</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($grouped[$type] as $track)
                                <tr>
                                    <td><strong>{{ e($track->label) }}</strong></td>
                                    <td>
                                        <span class="badge bg-secondary">{{ strtoupper($track->language_code) }}</span>
                                        <br><small class="text-muted">{{\Locale::getDisplayLanguage($track->language_code, 'en')}}</small>
                                    </td>
                                    <td>
                                        @if($track->is_sdh)
                                            <span class="badge bg-warning text-dark" title="Subtitle for Deaf and Hard of Hearing">SDH</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($track->is_default)
                                            <span class="badge bg-success">Default</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($track->source_url)
                                            <a href="{{ $track->source_url }}" target="_blank" class="text-decoration-none" title="{{ $track->source_url }}">
                                                <i class="fas fa-globe me-1"></i>Remote
                                            </a>
                                            @if(empty($track->vtt_content))
                                                <span class="badge bg-warning text-dark ms-1">Not cached</span>
                                            @endif
                                        @elseif(!empty($track->vtt_content))
                                            <span class="badge bg-info">Inline</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($track->active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('caption-tracks.edit', [$digitalObjectId, $track->id]) }}"
                                               class="btn btn-sm atom-btn-white" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" action="{{ route('caption-tracks.toggle-active', [$digitalObjectId, $track->id]) }}" class="m-0">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $track->active ? 'atom-btn-white' : 'btn-success' }}"
                                                        title="{{ $track->active ? 'Disable' : 'Enable' }}">
                                                    <i class="fas fa-{{ $track->active ? 'pause' : 'play' }}"></i>
                                                </button>
                                            </form>
                                            @if($track->source_url && empty($track->vtt_content))
                                                <form method="POST" action="{{ route('caption-tracks.fetch', [$digitalObjectId, $track->id]) }}" class="m-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm atom-btn-white" title="Fetch and cache VTT content">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('caption-tracks.destroy', [$digitalObjectId, $track->id]) }}"
                                                  onsubmit="return confirm('Delete this track?');" class="m-0">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm atom-btn-white text-danger" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @if(!empty($track->vtt_content))
                                    <tr>
                                        <td colspan="7" class="bg-light">
                                            <small class="text-muted">
                                                <i class="fas fa-code me-1"></i>VTT preview:
                                                {{ Str::limit(preg_replace('/\s+/', ' ', trim(strip_tags(preg_replace('/<[^>]+>/', ' ', $track->vtt_content)))), 120) }}
                                            </small>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

@endif

<div class="card mt-4">
    <div class="card-header" style="background:var(--ahg-primary);color:#fff">
        <i class="fas fa-question-circle me-2"></i>Quick Reference
    </div>
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-sm-3">Caption</dt>
            <dd class="col-sm-9">Full transcription with audio cues; essential for accessibility compliance.</dd>
            <dt class="col-sm-3">Subtitle</dt>
            <dd class="col-sm-9">Dialogue-only text tracks. Suitable for foreign-language dubs or same-language subtitles.</dd>
            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9">Audio description — narrated descriptions of visual elements for blind viewers.</dd>
            <dt class="col-sm-3">Chapters</dt>
            <dd class="col-sm-9">Chapter markers for navigation within a long-form video.</dd>
            <dt class="col-sm-3">SDH</dt>
            <dd class="col-sm-9">Subtitles for the Deaf and Hard of Hearing — include speaker identification and sound descriptions.</dd>
            <dt class="col-sm-3">Remote URL</dt>
            <dd class="col-sm-9">Link to an external VTT/SRT file. Content is cached locally on first use or manual fetch.</dd>
        </dl>
    </div>
</div>

@endsection
