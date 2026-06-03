@extends('theme::layouts.1col')
@section('title', $authority->heading)

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12 d-flex align-items-center">
            <a href="{{ route('library.authority-index') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h2 class="mb-0">{{ $authority->heading }}</h2>
                <span class="badge bg-info text-dark mt-1">{{ $authority->subject_type }}</span>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Authority detail card --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Authority Detail') }}</h5>
                        <a href="{{ route('library.authority-edit', $authority->id) }}"
                           class="btn btn-sm btn-light">
                            <i class="fas fa-pen me-1"></i>Edit
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-md-3 text-muted">ID</dt>
                        <dd class="col-md-9"><code>{{ $authority->id }}</code></dd>

                        <dt class="col-md-3 text-muted">Heading</dt>
                        <dd class="col-md-9">{{ $authority->heading }}</dd>

                        <dt class="col-md-3 text-muted">Type</dt>
                        <dd class="col-md-9"><span class="badge bg-light text-dark">{{ $authority->subject_type }}</span></dd>

                        <dt class="col-md-3 text-muted">Source</dt>
                        <dd class="col-md-9">{{ strtoupper($authority->source) }}</dd>

                        <dt class="col-md-3 text-muted">URI</dt>
                        <dd class="col-md-9">
                            @if($authority->uri)
                                <a href="{{ $authority->uri }}" target="_blank" rel="noopener">
                                    {{ $authority->uri }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </dd>

                        <dt class="col-md-3 text-muted">Linked Items</dt>
                        <dd class="col-md-9">
                            <span class="badge bg-secondary">{{ $authority->linked_count ?? 0 }}</span>
                        </dd>

                        <dt class="col-md-3 text-muted">Created</dt>
                        <dd class="col-md-9"><small class="text-muted">{{ $authority->created_at }}</small></dd>

                        <dt class="col-md-3 text-muted">Updated</dt>
                        <dd class="col-md-9"><small class="text-muted">{{ $authority->updated_at }}</small></dd>
                    </dl>
                </div>
            </div>

            {{-- Linked library items --}}
            <div class="card shadow-sm">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{ __('Linked Library Items') }}</h5>
                        <a href="{{ route('library.authority-link', $authority->id) }}"
                           class="btn btn-sm btn-light">
                            <i class="fas fa-link me-1"></i>Add Link
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @if(empty($linkedItems))
                        <p class="text-muted small p-3 mb-0">No library items linked to this authority record.</p>
                    @else
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Library Item') }}</th>
                                    <th>{{ __('Source Tag') }}</th>
                                    <th>{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($linkedItems as $item)
                                    <tr>
                                        <td>
                                            <a href="{{ route('library.marc-edit', $item->library_item_id) }}">
                                                {{ $item->title ?? 'Item #' . $item->library_item_id }}
                                            </a>
                                        </td>
                                        <td><code>{{ $item->source_tag }}</code></td>
                                        <td>
                                            <form method="POST"
                                                  action="{{ route('library.authority-unlink', $item->link_id) }}"
                                                  class="d-inline"
                                                  onsubmit="return confirm('Remove this link?');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm sticky-top" style="top:1rem">
                <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                    <h5 class="mb-0">{{ __('Actions') }}</h5>
                </div>
                <div class="card-body">
                    <a href="{{ route('library.authority-link', $authority->id) }}"
                       class="btn btn-primary w-100 mb-2">
                        <i class="fas fa-link me-2"></i>Link to Item
                    </a>
                    <a href="{{ route('library.authority-edit', $authority->id) }}"
                       class="btn btn-outline-secondary w-100 mb-2">
                        <i class="fas fa-pen me-2"></i>Edit Authority
                    </a>
                    <form method="POST" action="{{ route('library.authority-destroy', $authority->id) }}"
                          class="d-inline w-100"
                          onsubmit="return confirm('Delete this authority record and all its links?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-trash me-2"></i>Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
