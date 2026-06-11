{{--
  Language-revival corpus - ADMIN glossary moderation (north-star heratio#1208).

  The moderation queue for community-contributed glossary entries. New entries
  land as 'pending' and only appear on the public language pages once an admin
  approves them; rejected entries are hidden. Status filter chips, approve /
  reject actions, full empty-state. Admin-gated (auth + admin). Never 500s.

  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  AGPL-3.0-or-later
--}}

@extends('theme::layouts.1col')

@section('title', __('Glossary moderation'))

@section('content')
<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="fas fa-book me-2 text-muted"></i>{{ __('Glossary moderation') }}
        </h1>
        <a href="{{ route('language-corpus.index') }}" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
            <i class="fas fa-up-right-from-square me-1"></i>{{ __('View public pages') }}
        </a>
    </div>

    <p class="text-muted small mb-3">
        {{ __('Community-contributed words for the language-revival pages. Entries appear publicly only once approved here.') }}
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
            <a href="{{ route('language-corpus.glossary.moderate', ['status' => $key]) }}"
               class="badge rounded-pill text-decoration-none {{ $statusFilter === $key ? 'text-bg-dark' : 'text-bg-light border' }}">
                {{ __($meta['label']) }} <span class="opacity-75">{{ $c }}</span>
            </a>
        @endforeach
    </div>

    @if(!$available)
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                <h2 class="h5">{{ __('The glossary store is not ready') }}</h2>
                <p class="text-muted mb-0">{{ __('The glossary table has not been installed yet. It is created automatically on the next application boot.') }}</p>
            </div>
        </div>
    @elseif(empty($entries))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h2 class="h5">{{ __('Nothing in this queue') }}</h2>
                <p class="text-muted mb-0">{{ __('There are no glossary entries with this status.') }}</p>
            </div>
        </div>
    @else
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Language') }}</th>
                        <th>{{ __('Term') }}</th>
                        <th>{{ __('Meaning') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Contributor') }}</th>
                        <th class="text-end">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $e)
                        <tr>
                            <td class="small">
                                {{ $e['culture_label'] }}
                                <span class="badge text-bg-light border text-uppercase ms-1">{{ $e['culture'] }}</span>
                            </td>
                            <td class="fw-semibold">{{ $e['term'] }}</td>
                            <td class="small">
                                {{ \Illuminate\Support\Str::limit($e['meaning'], 160) }}
                                @if(!empty($e['usage_example']))
                                    <div class="text-muted fst-italic">{{ \Illuminate\Support\Str::limit($e['usage_example'], 100) }}</div>
                                @endif
                            </td>
                            <td class="small text-muted">{{ $e['source'] ?: '-' }}</td>
                            <td class="small text-muted">{{ $e['contributor_name'] ?: __('Anonymous') }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    @if($e['moderation_status'] !== 'approved')
                                        <form method="POST" action="{{ route('language-corpus.glossary.set', ['id' => $e['id']]) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="moderation_status" value="approved">
                                            <button type="submit" class="btn btn-outline-success">
                                                <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($e['moderation_status'] !== 'rejected')
                                        <form method="POST" action="{{ route('language-corpus.glossary.set', ['id' => $e['id']]) }}" class="d-inline">
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
