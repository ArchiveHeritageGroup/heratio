@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-user-tag me-2"></i>Researcher Types</h1>@endsection
@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center" style="background:var(--ahg-primary);color:#fff">
        <h5 class="mb-0">Manage Researcher Types</h5>
        <button class="btn atom-atom-btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#addTypeModal"><i class="fas fa-plus me-1"></i>Add Type</button>
    </div>
    <div class="card-body p-0">
        @if(count($types) > 0)
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover mb-0">
                <thead>
                    <tr style="background:var(--ahg-primary);color:#fff">
                        <th>Name</th>
                        <th>Description</th>
                        <th>Privileges</th>
                        <th>Max Bookings</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($types as $type)
                    <tr>
                        <td class="fw-bold">{{ e($type->name) }}</td>
                        <td>{{ e($type->description ?? '-') }}</td>
                        <td>
                            @if($type->privileges ?? null)
                                @foreach(explode(',', $type->privileges) as $priv)
                                <span class="badge bg-info me-1">{{ trim($priv) }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">None</span>
                            @endif
                        </td>
                        <td>{{ $type->max_bookings ?? 'Unlimited' }}</td>
                        <td>
                            <a href="#" class="btn atom-btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#editTypeModal{{ $type->id }}" title="Edit"><i class="fas fa-edit"></i></a>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this researcher type?')">
                                @csrf
                                <input type="hidden" name="form_action" value="delete">
                                <input type="hidden" name="type_id" value="{{ $type->id }}">
                                <button type="submit" class="btn atom-atom-btn-outline-danger btn-sm" title="Delete"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>

                    {{-- Edit Modal --}}
                    <div class="modal fade" id="editTypeModal{{ $type->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                        <form method="POST">@csrf<input type="hidden" name="form_action" value="update"><input type="hidden" name="type_id" value="{{ $type->id }}">
                        <div class="modal-header"><h5 class="modal-title">Edit Type: {{ e($type->name) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" value="{{ e($type->name) }}" required></div>
                            <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2">{{ e($type->description ?? '') }}</textarea></div>
                            <div class="mb-3"><label class="form-label">Privileges</label><input type="text" class="form-control" name="privileges" value="{{ e($type->privileges ?? '') }}" placeholder="Comma-separated"></div>
                            <div class="mb-3"><label class="form-label">Max Bookings</label><input type="number" class="form-control" name="max_bookings" value="{{ $type->max_bookings ?? '' }}" placeholder="Leave empty for unlimited"></div>
                        </div>
                        <div class="modal-footer"><button type="submit" class="btn atom-atom-btn-outline-success"><i class="fas fa-save me-1"></i>Update</button></div>
                        </form>
                    </div></div></div>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="text-center text-muted py-4">
            <i class="fas fa-user-tag fa-3x mb-3 d-block"></i>
            No researcher types defined yet.
        </div>
        @endif
    </div>
</div>

{{-- Add Type Modal --}}
<div class="modal fade" id="addTypeModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">@csrf<input type="hidden" name="form_action" value="create">
    <div class="modal-header"><h5 class="modal-title">Add Researcher Type</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <div class="mb-3"><label class="form-label">Name <span class="text-danger">*</span></label><input type="text" class="form-control" name="name" required></div>
        <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label">Privileges</label><input type="text" class="form-control" name="privileges" placeholder="Comma-separated (e.g. booking,collections,api)"></div>
        <div class="mb-3"><label class="form-label">Max Bookings</label><input type="number" class="form-control" name="max_bookings" placeholder="Leave empty for unlimited"></div>
    </div>
    <div class="modal-footer"><button type="submit" class="btn atom-atom-btn-outline-success"><i class="fas fa-plus me-1"></i>Add Type</button></div>
    </form>
</div></div></div>
@endsection
