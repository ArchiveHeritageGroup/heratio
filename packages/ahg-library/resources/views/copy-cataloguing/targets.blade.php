@extends('theme::layouts.1col')
@section('title', 'Z39.50 Target Management')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.copy-cataloguing-index') }}"
               class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <h2 class="mb-0">{{ __('Z39.50 Targets') }}</h2>
            <a href="{{ route('library.copy-cataloguing-targets') }}"
               class="btn btn-sm btn-success ms-auto"
               data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="fas fa-plus me-2"></i>New Target
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Name') }}</th>
                        <th>{{ __('Host') }}</th>
                        <th>{{ __('Port') }}</th>
                        <th>{{ __('Database') }}</th>
                        <th>{{ __('Syntax') }}</th>
                        <th>{{ __('Active') }}</th>
                        <th>{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($targets as $t)
                        <tr>
                            <td>{{ $t->name }}</td>
                            <td><code>{{ $t->host }}</code></td>
                            <td>{{ $t->port }}</td>
                            <td><small>{{ $t->database_name }}</small></td>
                            <td><small>{{ $t->syntax }}</small></td>
                            <td>
                                @if($t->active)
                                    <span class="badge bg-success">Active</span>
                                @else
                                    <span class="badge bg-secondary">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal{{ $t->id }}">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" action="{{ route('library.copy-cataloguing-destroy-target', $t->id) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('Delete this target?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>

                        {{-- Edit Modal --}}
                        <div class="modal fade" id="editModal{{ $t->id }}" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit: {{ $t->name }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST" action="{{ route('library.copy-cataloguing-update-target', $t->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <div class="modal-body">
                                            <div class="mb-2">
                                                <label class="form-label">{{ __('Name') }}</label>
                                                <input type="text" name="name" class="form-control"
                                                       value="{{ $t->name }}" required>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">{{ __('Host') }}</label>
                                                <input type="text" name="host" class="form-control"
                                                       value="{{ $t->host }}" required>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label">{{ __('Port') }}</label>
                                                    <input type="number" name="port" class="form-control"
                                                           value="{{ $t->port }}" min="1" max="65535">
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label">{{ __('Database') }}</label>
                                                    <input type="text" name="database_name" class="form-control"
                                                           value="{{ $t->database_name }}">
                                                </div>
                                            </div>
                                            <div class="row g-2 mb-2">
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('Syntax') }}</label>
                                                    <select name="syntax" class="form-select">
                                                        @foreach(['USmarc','MARC21','UNIMARC','SUTRS'] as $s)
                                                            <option value="{{ $s }}" {{ $t->syntax === $s ? 'selected' : '' }}>{{ $s }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">{{ __('Element Set') }}</label>
                                                    <input type="text" name="element_set" class="form-control"
                                                           value="{{ $t->element_set }}">
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">{{ __('Username') }}</label>
                                                <input type="text" name="username" class="form-control"
                                                       value="{{ $t->username ?? '' }}">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">{{ __('Password') }}</label>
                                                <input type="password" name="password" class="form-control"
                                                       placeholder="{{ __('(unchanged)') }}">
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label">{{ __('Sort Order') }}</label>
                                                <input type="number" name="sort_order" class="form-control"
                                                       value="{{ $t->sort_order ?? 0 }}">
                                            </div>
                                            <div class="form-check">
                                                <input type="checkbox" name="active" class="form-check-input"
                                                       value="1" id="active{{ $t->id }}"
                                                       {{ $t->active ? 'checked' : '' }}>
                                                <label class="form-check-label" for="active{{ $t->id }}">
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-success">
                                                <i class="fas fa-save"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                No Z39.50 targets configured. Add one to start copy cataloguing.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Create Modal --}}
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ __('New Z39.50 Target') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('library.copy-cataloguing-store-target') }}">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control"
                                   placeholder="{{ __('Library of Congress') }}" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Host <span class="text-danger">*</span></label>
                            <input type="text" name="host" class="form-control"
                                   placeholder="{{ __('zcat.loc.gov') }}" required>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-4">
                                <label class="form-label">{{ __('Port') }}</label>
                                <input type="number" name="port" class="form-control" value="210">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">{{ __('Database') }}</label>
                                <input type="text" name="database_name" class="form-control"
                                       value="Default">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Syntax') }}</label>
                                <select name="syntax" class="form-select">
                                    <option value="USmarc">{{ __('USmarc / MARC21') }}</option>
                                    <option value="UNIMARC">{{ __('UNIMARC') }}</option>
                                    <option value="SUTRS">{{ __('SUTRS') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Element Set') }}</label>
                                <select name="element_set" class="form-select">
                                    <option value="F">{{ __('F (Full)') }}</option>
                                    <option value="B">{{ __('B (Brief)') }}</option>
                                    <option value="S">{{ __('S (Suggested)') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Username') }}</label>
                                <input type="text" name="username" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('Password') }}</label>
                                <input type="password" name="password" class="form-control">
                            </div>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="active" class="form-check-input"
                                   value="1" id="activeNew" checked>
                            <label class="form-check-label" for="activeNew">{{ __('Active') }}</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Save Target
                        </button>
                        <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
