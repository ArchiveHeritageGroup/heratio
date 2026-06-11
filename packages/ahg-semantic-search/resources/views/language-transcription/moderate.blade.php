{{--
  Language-revival corpus - ADMIN moderation of community transcription /
  correction / translation contributions (north-star heratio#1208).

  The moderation queue for community contributions on heritage-language items.
  New contributions land as 'pending' and only appear on the public item pages
  once an admin approves them; rejected ones are hidden. Status filter chips,
  approve / reject actions, full empty-state. Admin-gated (auth + admin). Mirrors
  the glossary moderation queue. Never 500s.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Transcription moderation'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-feather-pointed me-2 text-muted"></i>{{ __('Transcription moderation') }}
        </h1>
        <a href="{{ route('language-corpus.index') }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-up-right-from-square me-1"></i>{{ __('View public pages') }}
        </a>
    </div>

    <p class="text-muted small mb-3">
        {{ __('Community transcriptions, corrections, translations and notes on heritage-language items. Contributions appear publicly only once approved here.') }}
    </p>

    @if(session('success'))
        <div class="alert alert-success" role="alert">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    {{-- Status filter chips --}}
    <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
        <span class="text-muted small me-1">{{ __('Show:') }}</span>
        @foreach($statuses as $key => $meta)
            @php $c = (int) ($counts[$key] ?? 0); @endphp
            <a href="{{ route('language-transcribe.moderate', ['status' => $key]) }}"
               class="badge rounded-pill text-decoration-none {{ $statusFilter === $key ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
            </a>
        @endforeach
    </div>

    @if(!$available)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                <h2 class="h5">{{ __('The contributions store is not ready') }}</h2>
                <p class="text-muted mb-0">{{ __('The contributions table has not been installed yet. It is created automatically on the next application boot.') }}</p>
            </div>
        </div>
    @elseif(empty($entries))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h2 class="h5">{{ __('Nothing in this queue') }}</h2>
                <p class="text-muted mb-0">{{ __('There are no contributions with this status.') }}</p>
            </div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Item') }}</th>
                        <th>{{ __('Language') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Contribution') }}</th>
                        <th>{{ __('Contributor') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $e)
                        <tr>
                            <td class="small">
                                @php $item = $e['item'] ?? null; @endphp
                                @if($item && !empty($item['slug']))
                                    <a href="{{ url('/'.$item['slug']) }}" target="_blank" rel="noopener" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($item['title'], 60) }}</a>
                                @elseif($item)
                                    {{ \Illuminate\Support\Str::limit($item['title'], 60) }}
                                @else
                                    <span class="text-muted">#{{ $e['item_ref'] }}</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $e['culture_label'] }}
                                <span class="badge text-bg-light border text-uppercase ms-1">{{ $e['culture'] }}</span>
                            </td>
                            <td class="small">
                                <span class="badge text-bg-light border">
                                    <i class="fas {{ $e['type_meta']['icon'] ?? 'fa-comment' }} me-1"></i>{{ __($e['type_meta']['label'] ?? $e['contribution_type']) }}
                                </span>
                            </td>
                            <td class="small" style="max-width: 32rem;">
                                <div style="white-space: pre-line;">{{ \Illuminate\Support\Str::limit($e['body'], 260) }}</div>
                                @if(!empty($e['source']))
                                    <div class="text-muted"><i class="fas fa-book-open me-1"></i>{{ \Illuminate\Support\Str::limit($e['source'], 80) }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $e['contributor_name'] ?: __('Anonymous') }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($e['moderation_status'] !== 'approved')
                                        <form method="POST" action="{{ route('language-transcribe.set', ['id' => $e['id']]) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="moderation_status" value="approved">
                                            <button type="submit" class="btn btn-outline-success">
                                                <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($e['moderation_status'] !== 'rejected')
                                        <form method="POST" action="{{ route('language-transcribe.set', ['id' => $e['id']]) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="moderation_status" value="rejected">
                                            <button type="submit" class="btn btn-outline-secondary">
                                                <i class="fas fa-xmark me-1"></i>{{ __('Reject') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</div>
@endsection
