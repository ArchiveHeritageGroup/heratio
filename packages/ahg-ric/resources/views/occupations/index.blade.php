@extends('theme::layouts.2col')
@section('title', __('Occupations'))
@section('body-class', 'admin ric')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="mb-0"><i class="fas fa-briefcase me-2"></i>{{ __('Occupations') }}</h1>
    <a href="{{ route('ric.occupations.create') }}" class="btn btn-primary">
        <i class="fas fa-plus"></i> {{ __('New Occupation') }}
    </a>
</div>

<p class="text-muted small">
    {{ __('A rico:Occupation is a role, profession, or position held by an actor over a time-span (ISAAR(CPF) section 5.2.6).') }}
</p>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<form method="get" class="mb-3">
    <div class="input-group" style="max-width: 420px;">
        <input type="text" name="q" value="{{ $q }}" class="form-control"
               placeholder="{{ __('Search title or actor name…') }}">
        <button class="btn btn-outline-secondary" type="submit">
            <i class="fas fa-search"></i>
        </button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-sm table-striped align-middle">
        <thead>
            <tr>
                <th>{{ __('Title') }}</th>
                <th>{{ __('Actor') }}</th>
                <th>{{ __('Start') }}</th>
                <th>{{ __('End') }}</th>
                <th>{{ __('Current') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($occupations as $o)
                <tr>
                    <td>{{ $o->title }}</td>
                    <td>{{ $o->actor_name ?: ('Actor #' . $o->actor_id) }}</td>
                    <td>{{ $o->start_date ?? '—' }}</td>
                    <td>{{ $o->end_date ?? '—' }}</td>
                    <td>
                        @if($o->is_current)
                            <span class="badge bg-success">{{ __('Yes') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-end">
                        <a href="{{ route('ric.occupations.edit', $o->id) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="post" action="{{ route('ric.occupations.destroy', $o->id) }}"
                              class="d-inline" onsubmit="return confirm('{{ __('Delete this occupation?') }}');">
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
                    <td colspan="6" class="text-center text-muted py-4">
                        {{ __('No occupations recorded yet.') }}
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $occupations->links() }}
@endsection
