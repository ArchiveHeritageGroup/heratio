{{--
    ISBN Providers admin (PSIS-parity, issue #734)

    Copyright (C) 2026 Johan Pieterse
    Plain Sailing Information Systems
    Email: johan@plainsailingisystems.co.za
    Heratio - GNU Affero General Public License v3 or later.
--}}
@extends('theme::layouts.1col')
@section('title', 'ISBN Providers')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0"><i class="bi bi-upc-scan me-2"></i>{{ __('ISBN Lookup Providers') }}</h1>
        <a href="{{ route('library.isbn-provider-edit', 0) }}" class="btn atom-btn-white">
            <i class="bi bi-plus-lg me-1"></i>{{ __('Add provider') }}
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @php $coreNames = ['Open Library', 'Google Books', 'WorldCat']; @endphp

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('Provider') }}</th>
                        <th>{{ __('API URL') }}</th>
                        <th class="text-center">{{ __('Active') }}</th>
                        <th class="text-center">{{ __('Priority') }}</th>
                        <th class="text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($providers ?? [] as $p)
                        @php $isCore = in_array($p->name ?? '', $coreNames, true); @endphp
                        <tr>
                            <td>
                                <strong>{{ e($p->name ?? '') }}</strong>
                                @if ($isCore)
                                    <span class="badge bg-info ms-1">{{ __('Core') }}</span>
                                @endif
                            </td>
                            <td><small class="text-muted">{{ e($p->api_url ?? '') }}</small></td>
                            <td class="text-center">
                                @if ($p->active ?? 0)
                                    <span class="badge bg-success">{{ __('Active') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $p->priority ?? 0 }}</td>
                            <td class="text-end">
                                <a href="{{ route('library.isbn-provider-edit', $p->id ?? 0) }}"
                                   class="btn btn-sm atom-btn-white" title="{{ __('Edit') }}">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <form method="post"
                                      action="{{ route('library.isbn-provider-toggle', $p->id ?? 0) }}"
                                      class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm atom-btn-white"
                                            title="{{ ($p->active ?? 0) ? __('Disable') : __('Enable') }}">
                                        @if ($p->active ?? 0)
                                            <i class="bi bi-toggle-on text-success"></i>
                                        @else
                                            <i class="bi bi-toggle-off text-muted"></i>
                                        @endif
                                    </button>
                                </form>
                                @if (!$isCore)
                                    <form method="post"
                                          action="{{ route('library.isbn-provider-delete', $p->id ?? 0) }}"
                                          class="d-inline"
                                          onsubmit="return confirm('{{ __('Delete this provider?') }}');">
                                        @csrf
                                        <button type="submit" class="btn btn-sm atom-btn-white" title="{{ __('Delete') }}">
                                            <i class="bi bi-trash text-danger"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted text-center py-3">{{ __('No providers configured.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        {{ __('Core providers (Open Library, Google Books, WorldCat) can be disabled but not deleted.') }}
    </p>
</div>
@endsection
