@extends('theme::layouts.1col')
@section('title', 'Subject Authority Control')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <h2 class="mb-0">{{ __('Subject Authority Control') }}</h2>
            <a href="{{ route('library.marc-index') }}" class="btn btn-outline-secondary btn-sm ms-auto">
                <i class="fas fa-arrow-left me-1"></i>MARC Editor
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    {{-- Search / create bar --}}
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <form method="GET" action="{{ route('library.authority-index') }}" class="d-flex gap-2">
                        <input type="text" name="search" class="form-control"
                               placeholder="{{ __('Search headings…') }}" value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-3">
                    <select name="subject_type" class="form-select"
                            onchange="this.form.submit()">
                        <option value="">{{ __('All types') }}</option>
                        @foreach(['topic','geographic','temporal','genre','form','uniform','names'] as $type)
                            <option value="{{ $type }}" {{ request('subject_type') === $type ? 'selected' : '' }}>
                                {{ ucfirst($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 text-end">
                    <a href="{{ route('library.authority-create') }}" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>New Authority
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Heading') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Source') }}</th>
                        <th>{{ __('Linked') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($authorities as $auth)
                        <tr>
                            <td>
                                <a href="{{ route('library.authority-view', $auth->id) }}">
                                    {{ $auth->heading }}
                                </a>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ $auth->subject_type }}</span></td>
                            <td><small class="text-muted">{{ $auth->source }}</small></td>
                            <td><span class="badge bg-secondary">{{ $auth->linked_count ?? 0 }}</span></td>
                            <td>
                                <a href="{{ route('library.authority-view', $auth->id) }}"
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <form method="POST" action="{{ route('library.authority-destroy', $auth->id) }}"
                                      class="d-inline" onsubmit="return confirm('Delete this authority record?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No authority records found. <a href="{{ route('library.authority-create') }}">Create one.</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($total > $limit)
            <div class="card-footer">
                @php $lastPage = ceil($total / $limit); @endphp
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        @if($page > 1)
                            <li class="page-item">
                                <a class="page-link" href="{{ route('library.authority-index', array_merge(request()->query(), ['page' => $page - 1])) }}">Prev</a>
                            </li>
                        @endif
                        @for($p = max(1, $page - 2); $p <= min($lastPage, $page + 2); $p++)
                            <li class="page-item {{ $p === $page ? 'active' : '' }}">
                                <a class="page-link" href="{{ route('library.authority-index', array_merge(request()->query(), ['page' => $p])) }}">{{ $p }}</a>
                            </li>
                        @endfor
                        @if($page < $lastPage)
                            <li class="page-item">
                                <a class="page-link" href="{{ route('library.authority-index', array_merge(request()->query(), ['page' => $page + 1])) }}">Next</a>
                            </li>
                        @endif
                    </ul>
                </nav>
            </div>
        @endif
    </div>
</div>
@endsection
