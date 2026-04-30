@extends('theme::layouts.1col')
@section('title', $node->code . ' - ' . $node->title)
@section('body-class', 'admin records')

@section('title-block')
<nav aria-label="{{ __('breadcrumb') }}">
    <ol class="breadcrumb mb-1">
        <li class="breadcrumb-item"><a href="{{ route('records.fileplan.index') }}">File Plan</a></li>
        @foreach($breadcrumb as $crumb)
            @if($crumb->id === $node->id)
                <li class="breadcrumb-item active" aria-current="page">{{ $crumb->code }}</li>
            @else
                <li class="breadcrumb-item"><a href="{{ route('records.fileplan.show', $crumb->id) }}">{{ $crumb->code }}</a></li>
            @endif
        @endforeach
    </ol>
</nav>
<div class="d-flex justify-content-between align-items-center">
    <h1 class="mb-0">
        <span class="badge bg-secondary">{{ $node->code }}</span>
        {{ $node->title }}
    </h1>
    <div>
        <a href="{{ route('records.fileplan.edit', $node->id) }}" class="btn btn-outline-primary btn-sm">Edit</a>
        <form method="post" action="{{ route('records.fileplan.destroy', $node->id) }}" class="d-inline" onsubmit="return confirm('Delete this node?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Delete') }}</button>
        </form>
    </div>
</div>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row mb-3">
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header">Node Details</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th style="width:180px;">{{ __('Code') }}</th><td>{{ $node->code }}</td></tr>
                        <tr><th>{{ __('Title') }}</th><td>{{ $node->title }}</td></tr>
                        <tr>
                            <th>{{ __('Type') }}</th>
                            <td>
                                @php
                                    $typeBadges = [
                                        'plan' => 'bg-primary',
                                        'series' => 'bg-info',
                                        'sub_series' => 'bg-warning text-dark',
                                        'file_group' => 'bg-success',
                                        'volume' => 'bg-dark',
                                    ];
                                    $badgeClass = $typeBadges[$node->node_type] ?? 'bg-secondary';
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ str_replace('_', ' ', $node->node_type) }}</span>
                            </td>
                        </tr>
                        <tr><th>{{ __('Status') }}</th><td><span class="badge {{ $node->status === 'active' ? 'bg-success' : ($node->status === 'closed' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ ucfirst($node->status) }}</span></td></tr>
                        <tr><th>{{ __('Description') }}</th><td>{{ $node->description ?: '-' }}</td></tr>
                        <tr><th>{{ __('Parent') }}</th><td>
                            @if($node->parent_id)
                                <a href="{{ route('records.fileplan.show', $node->parent_id) }}">{{ $node->parent_code }} - {{ $node->parent_title }}</a>
                            @else
                                <em>Root node</em>
                            @endif
                        </td></tr>
                        <tr><th>{{ __('Depth') }}</th><td>{{ $node->depth }}</td></tr>
                        @if($node->source_department)
                            <tr><th>{{ __('Source Department') }}</th><td>{{ $node->source_department }}</td></tr>
                        @endif
                        @if($node->source_agency_code)
                            <tr><th>{{ __('Agency Code') }}</th><td>{{ $node->source_agency_code }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Retention &amp; Disposal</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><th style="width:180px;">{{ __('Disposal Class') }}</th><td>
                            @if($node->disposal_class_id)
                                {{ $node->disposal_class_code ?? '' }} {{ $node->disposal_class_title ?? '(ID: ' . $node->disposal_class_id . ')' }}
                            @else
                                -
                            @endif
                        </td></tr>
                        <tr><th>{{ __('Retention Period') }}</th><td>{{ $node->retention_period ?: '-' }}</td></tr>
                        <tr><th>{{ __('Disposal Action') }}</th><td>{{ $node->disposal_action ? ucfirst($node->disposal_action) : '-' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">Move Node</div>
            <div class="card-body">
                <form method="post" action="{{ route('records.fileplan.move', $node->id) }}">
                    @csrf
                    <div class="mb-2">
                        <label for="new_parent_id" class="form-label">{{ __('New Parent') }}</label>
                        <select name="new_parent_id" id="new_parent_id" class="form-select form-select-sm">
                            @php
                                $allNodes = app(\AhgRecordsManage\Services\FilePlanService::class)->getNodesForDropdown();
                            @endphp
                            @foreach($allNodes as $pn)
                                @if($pn->id !== $node->id)
                                    <option value="{{ $pn->id }}">{{ str_repeat('-- ', $pn->depth) }}{{ $pn->code }} - {{ $pn->title }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Move this node?');">{{ __('Move') }}</button>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Summary</div>
            <div class="card-body">
                <p class="mb-1"><strong>{{ $node->child_count }}</strong> child node(s)</p>
                <p class="mb-1"><strong>{{ $node->record_count }}</strong> linked record(s)</p>
                <p class="mb-0 text-muted small">Created: {{ $node->created_at }}</p>
            </div>
        </div>
    </div>
</div>

@if(!empty($children))
<div class="card mb-3">
    <div class="card-header">Child Nodes ({{ count($children) }})</div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('Code') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($children as $child)
                <tr>
                    <td><a href="{{ route('records.fileplan.show', $child->id) }}">{{ $child->code }}</a></td>
                    <td>{{ $child->title }}</td>
                    <td><span class="badge bg-secondary">{{ str_replace('_', ' ', $child->node_type) }}</span></td>
                    <td>{{ ucfirst($child->status) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(!empty($records['data']))
<div class="card mb-3">
    <div class="card-header">Linked Records ({{ $records['total'] }})</div>
    <div class="card-body p-0">
        <table class="table table-sm table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('Identifier') }}</th>
                    <th>{{ __('Title') }}</th>
                    <th>{{ __('Created') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($records['data'] as $record)
                <tr>
                    <td><a href="{{ url('/informationobject/show/' . $record->id) }}">{{ $record->identifier }}</a></td>
                    <td>{{ $record->title }}</td>
                    <td>{{ $record->created_at }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($records['total'] > $records['perPage'])
    <div class="card-footer">
        @php
            $totalPages = ceil($records['total'] / $records['perPage']);
            $currentPage = $records['page'];
        @endphp
        <nav>
            <ul class="pagination pagination-sm mb-0 justify-content-center">
                @for($p = 1; $p <= $totalPages; $p++)
                    <li class="page-item {{ $p === $currentPage ? 'active' : '' }}">
                        <a class="page-link" href="{{ route('records.fileplan.show', $node->id) }}?page={{ $p }}">{{ $p }}</a>
                    </li>
                @endfor
            </ul>
        </nav>
    </div>
    @endif
</div>
@endif

<a href="{{ route('records.fileplan.index') }}" class="btn btn-secondary">Back to File Plan</a>
@endsection
