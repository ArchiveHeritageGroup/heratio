@extends('theme::layout')

@section('title', 'Custom Fields')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="bi bi-input-cursor-text"></i> Custom Fields</h2>
        <div>
            <a href="{{ route('customFields.export') }}" class="atom-btn-white me-2"><i class="bi bi-download me-1"></i>{{ __('Export') }}</a>
            <a href="{{ route('customFields.add') }}" class="atom-btn-white"><i class="bi bi-plus me-1"></i>{{ __('Add Field') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            @if($definitions->isEmpty())
                <div class="p-4 text-center text-muted">
                    <p>No custom fields defined.</p>
                    <a href="{{ route('customFields.add') }}" class="atom-btn-white">Create your first custom field</a>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;">{{ __('Order') }}</th>
                                <th>{{ __('Label') }}</th>
                                <th>{{ __('Machine Name') }}</th>
                                <th>{{ __('Entity Type') }}</th>
                                <th>{{ __('Field Type') }}</th>
                                <th>{{ __('Required') }}</th>
                                <th>{{ __('Active') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($definitions as $def)
                                <tr>
                                    <td>{{ $def->sort_order ?? 0 }}</td>
                                    <td><strong>{{ $def->field_label }}</strong></td>
                                    <td><code>{{ $def->machine_name ?? '' }}</code></td>
                                    <td>{{ $def->entity_type ?? '' }}</td>
                                    <td>{{ $def->field_type ?? '' }}</td>
                                    <td>
                                        @if($def->is_required ?? false)
                                            <span class="badge bg-warning text-dark">{{ __('Yes') }}</span>
                                        @else
                                            <span class="text-muted">No</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($def->is_active ?? true)
                                            <span class="badge bg-success">{{ __('Active') }}</span>
                                        @else
                                            <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('customFields.edit', $def->id) }}" class="atom-btn-white btn-sm me-1">Edit</a>
                                        <form method="post" action="{{ route('customFields.delete', $def->id) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete this field?')"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
