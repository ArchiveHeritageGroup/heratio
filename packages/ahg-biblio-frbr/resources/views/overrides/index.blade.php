@extends('theme::layouts.1col')

@section('title', __('FRBR work-set overrides'))

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">{{ __('FRBR work-set overrides') }}</h1>
        <a href="{{ route('admin.frbr.overrides.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> {{ __('New override') }}
        </a>
    </div>

    <p class="text-muted">{{ __('Cataloguer-controlled exceptions to the algorithmic FRBR work-key. Use force-group to pin two items together, force-split to pull them apart.') }}</p>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <table class="table table-sm table-hover">
        <thead>
            <tr>
                <th>{{ __('Item') }}</th>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Mode') }}</th>
                <th>{{ __('Override key') }}</th>
                <th>{{ __('Reason') }}</th>
                <th>{{ __('Created') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse ($rows as $r)
            <tr>
                <td>{{ $r->library_item_id }}</td>
                <td>{{ $r->title ?? '—' }}</td>
                <td><span class="badge bg-{{ $r->mode === 'force_group' ? 'success' : 'warning' }}">{{ $r->mode }}</span></td>
                <td><code>{{ $r->override_key }}</code></td>
                <td><small>{{ $r->reason }}</small></td>
                <td><small>{{ $r->created_at }}</small></td>
                <td>
                    <form method="POST" action="{{ route('admin.frbr.overrides.destroy', $r->id) }}" class="d-inline" onsubmit="return confirm('{{ __('Clear this override?') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Clear') }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-muted text-center py-3">{{ __('No overrides yet.') }}</td></tr>
        @endforelse
        </tbody>
    </table>

    {{ $rows->links() }}
</div>
@endsection
