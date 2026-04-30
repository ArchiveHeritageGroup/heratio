{{--
  Copyright (C) 2026 Johan Pieterse
  Plain Sailing Information Systems
  Email: johan@plainsailingisystems.co.za

  This file is part of Heratio.

  Heratio is free software: you can redistribute it and/or modify
  it under the terms of the GNU Affero General Public License as published by
  the Free Software Foundation, either version 3 of the License, or
  (at your option) any later version.
--}}
@extends('theme::layouts.1col')

@section('title', 'Service Types')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice') || session('success'))
        <div class="alert alert-success">{{ session('notice') ?? session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item active">Service Types</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-tags me-2"></i>Service Types</h1>
        <div>
            <a href="{{ route('ahgvendor.index') }}" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>{{ __('Back to Dashboard') }}
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceTypeModal">
                <i class="fas fa-plus me-1"></i>{{ __('Add Service Type') }}
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-list me-2"></i>Available Service Types</h5>
        </div>
        <div class="card-body p-0">
            @if (!empty($serviceTypes) && count($serviceTypes) > 0)
            <table class="table table-striped table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="sortable" data-sort="name" style="cursor:pointer">Name <i class="fas fa-sort text-muted"></i></th>
                        <th class="sortable" data-sort="description" style="cursor:pointer">Description <i class="fas fa-sort text-muted"></i></th>
                        <th class="sortable" data-sort="status" style="cursor:pointer">Status <i class="fas fa-sort text-muted"></i></th>
                        <th class="text-end" style="width: 120px;">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($serviceTypes as $type)
                    <tr>
                        <td><strong>{{ e($type->name) }}</strong></td>
                        <td>{{ e($type->description ?? '-') }}</td>
                        <td>
                            @if ($type->is_active ?? true)
                                <span class="badge bg-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-primary edit-type-btn"
                                    data-id="{{ $type->id }}"
                                    data-name="{{ e($type->name) }}"
                                    data-description="{{ e($type->description ?? '') }}"
                                    data-active="{{ ($type->is_active ?? true) ? '1' : '0' }}"
                                    title="{{ __('Edit') }}">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-type-btn"
                                    data-id="{{ $type->id }}"
                                    data-name="{{ e($type->name) }}"
                                    title="{{ __('Delete') }}">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <div class="text-center py-5 text-muted">
                <i class="fas fa-tags display-4 mb-3 d-block"></i>
                <p>No service types defined yet.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceTypeModal">
                    <i class="fas fa-plus me-1"></i>{{ __('Add First Service Type') }}
                </button>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Add Service Type Modal --}}
<div class="modal fade" id="addServiceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgvendor.service-types') }}">
                @csrf
                <input type="hidden" name="form_action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Add Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="{{ __('e.g., Conservation, Digitisation, Storage') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="{{ __('Brief description of this service type') }}"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="addIsActive" value="1" checked>
                        <label class="form-check-label" for="addIsActive">{{ __('Active') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Add Service Type') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Edit Service Type Modal --}}
<div class="modal fade" id="editServiceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgvendor.service-types') }}">
                @csrf
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="id" id="editTypeId">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editTypeName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="description" id="editTypeDescription" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" class="form-check-input" id="editIsActive" value="1">
                        <label class="form-check-label" for="editIsActive">{{ __('Active') }}</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div class="modal fade" id="deleteServiceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="{{ route('ahgvendor.service-types') }}">
                @csrf
                <input type="hidden" name="form_action" value="delete">
                <input type="hidden" name="id" id="deleteTypeId">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Delete Service Type</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the service type "<strong id="deleteTypeName"></strong>"?</p>
                    <p class="text-danger mb-0"><small>{{ __('This action cannot be undone. Transactions using this type will not be affected.') }}</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Delete') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Edit button handler
    document.querySelectorAll('.edit-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('editTypeId').value = this.dataset.id;
            document.getElementById('editTypeName').value = this.dataset.name;
            document.getElementById('editTypeDescription').value = this.dataset.description;
            document.getElementById('editIsActive').checked = this.dataset.active === '1';
            new bootstrap.Modal(document.getElementById('editServiceTypeModal')).show();
        });
    });

    // Delete button handler
    document.querySelectorAll('.delete-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById('deleteTypeId').value = this.dataset.id;
            document.getElementById('deleteTypeName').textContent = this.dataset.name;
            new bootstrap.Modal(document.getElementById('deleteServiceTypeModal')).show();
        });
    });

    // Client-side table sorting
    var table = document.querySelector('.table tbody');
    if (!table) return;

    var headers = document.querySelectorAll('th.sortable');
    var sortDirection = {};

    headers.forEach(function(header) {
        header.addEventListener('click', function() {
            var sortKey = this.dataset.sort;
            var direction = sortDirection[sortKey] === 'asc' ? 'desc' : 'asc';
            sortDirection[sortKey] = direction;

            headers.forEach(function(h) {
                var icon = h.querySelector('i');
                if (icon) icon.className = 'fas fa-sort text-muted';
            });
            var icon = this.querySelector('i');
            if (icon) icon.className = direction === 'asc' ? 'fas fa-sort-up text-primary' : 'fas fa-sort-down text-primary';

            var rows = Array.from(table.querySelectorAll('tr'));
            rows.sort(function(a, b) {
                var colIndex = sortKey === 'name' ? 0 : (sortKey === 'description' ? 1 : 2);
                var aVal = a.cells[colIndex] ? a.cells[colIndex].textContent.trim().toLowerCase() : '';
                var bVal = b.cells[colIndex] ? b.cells[colIndex].textContent.trim().toLowerCase() : '';

                return direction === 'asc' ? aVal.localeCompare(bVal) : bVal.localeCompare(aVal);
            });

            rows.forEach(function(row) { table.appendChild(row); });
        });
    });
});
</script>
@endsection
