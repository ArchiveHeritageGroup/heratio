@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-university me-2"></i>Affiliated Institutions</h1>@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Institutions</h5>
        <span class="badge bg-primary">{{ count($institutions) }} institution(s)</span>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search by name or location...">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-filter"></i></button>
            </div>
        </form>

        @if(count($institutions) > 0)
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Researcher Count</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($institutions as $inst)
                    <tr>
                        <td class="fw-bold">{{ e($inst->name) }}</td>
                        <td>{{ e($inst->location ?? $inst->city ?? '-') }}</td>
                        <td>{{ $inst->researcher_count ?? 0 }}</td>
                        <td>
                            @if(($inst->status ?? 'active') === 'active')
                                <span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Active</span>
                            @else
                                <span class="badge bg-secondary"><i class="fas fa-times-circle me-1"></i>Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-university fa-3x mb-3 d-block"></i>
            No institutions found.
        </div>
        @endif
    </div>
</div>
@endsection
