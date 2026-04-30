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
@php $formErrors = is_array($errors ?? null) ? $errors : []; $errors = new \Illuminate\Support\ViewErrorBag(); @endphp
@extends('theme::layouts.1col')

@section('title', 'New Vendor Transaction')

@section('content')
<div class="container-fluid px-4">
    @if (session('notice'))
        <div class="alert alert-success">{{ session('notice') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.index') }}">Vendor Management</a></li>
            <li class="breadcrumb-item"><a href="{{ route('ahgvendor.transactions') }}">Transactions</a></li>
            <li class="breadcrumb-item active">New Transaction</li>
        </ol>
    </nav>

    <h1 class="h2 mb-4"><i class="fas fa-plus-circle me-2"></i>New Vendor Transaction</h1>

    @if (!empty($formErrors))
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($formErrors as $field => $error)
            <li>{{ e(is_array($error) ? implode(', ', $error) : $error) }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="post" action="{{ route('ahgvendor.add-transaction') }}" id="transactionForm">
        @csrf
        <div class="row">
            <div class="col-md-8">
                {{-- Basic Info --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Transaction Details
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Vendor *') }}</label>
                                <select name="vendor_id" class="form-select" required>
                                    <option value="">{{ __('Select vendor...') }}</option>
                                    @foreach (($vendors ?? []) as $vendor)
                                    <option value="{{ $vendor->id }}" {{ ($form['vendor_id'] ?? '') == $vendor->id ? 'selected' : '' }}>
                                        {{ e($vendor->name) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">{{ __('Service Type *') }}</label>
                                <select name="service_type_id" class="form-select" required>
                                    <option value="">{{ __('Select service...') }}</option>
                                    @foreach (($serviceTypes ?? []) as $service)
                                    <option value="{{ $service->id }}" {{ ($form['service_type_id'] ?? '') == $service->id ? 'selected' : '' }}>
                                        {{ e($service->name) }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Request Date *') }}</label>
                                <input type="date" name="request_date" class="form-control" value="{{ e($form['request_date'] ?? date('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Due Date') }}</label>
                                <input type="date" name="due_date" class="form-control" value="{{ e($form['due_date'] ?? '') }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">{{ __('Priority') }}</label>
                                <select name="priority" class="form-select">
                                    <option value="normal">{{ __('Normal') }}</option>
                                    <option value="low">{{ __('Low') }}</option>
                                    <option value="high">{{ __('High') }}</option>
                                    <option value="urgent">{{ __('Urgent') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">{{ __('Description') }}</label>
                            <textarea name="description" class="form-control" rows="2">{{ e($form['description'] ?? '') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- GLAM Items --}}
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-archive me-2"></i>{{ __('GLAM/DAM Items') }}</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Search and add archival items to this transaction. You can add multiple items.
                        </div>

                        {{-- Search Box --}}
                        <div class="mb-3">
                            <label class="form-label">{{ __('Search GLAM Items') }}</label>
                            <div class="input-group">
                                <input type="text" id="glamSearch" class="form-control" placeholder="{{ __('Type title or identifier to search...') }}" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div id="searchResults" class="autocomplete-dropdown"></div>
                        </div>

                        {{-- Selected Items Table --}}
                        <div id="selectedItemsContainer">
                            <table class="table table-sm" id="selectedItemsTable" style="display: none;">
                                <thead class="table-light">
                                    <tr>
                                        <th>{{ __('Item') }}</th>
                                        <th width="120">{{ __('Condition') }}</th>
                                        <th width="120">{{ __('Value (R)') }}</th>
                                        <th width="50">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="selectedItemsBody">
                                </tbody>
                            </table>
                            <div id="noItemsMessage" class="text-center text-muted py-3">
                                <i class="fas fa-archive fa-2x mb-2"></i>
                                <p class="mb-0">No items added yet. Use the search above to add items.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-sticky-note me-2"></i>Notes
                    </div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="{{ __('Additional notes about this transaction...') }}">{{ e($form['notes'] ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                {{-- Cost Estimate --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-dollar-sign me-2"></i>Cost Estimate
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Estimated Cost') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">R</span>
                                <input type="number" name="estimated_cost" class="form-control" step="0.01" value="{{ $form['estimated_cost'] ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Reference --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-hashtag me-2"></i>Reference
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">{{ __('Reference Number') }}</label>
                            <input type="text" name="reference_number" class="form-control" value="{{ e($form['reference_number'] ?? '') }}" placeholder="{{ __('Optional external reference') }}">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>{{ __('Create Transaction') }}
                            </button>
                            <a href="{{ route('ahgvendor.transactions') }}" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>{{ __('Cancel') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.autocomplete-dropdown {
    position: absolute;
    z-index: 1050;
    width: calc(100% - 2rem);
    max-height: 300px;
    overflow-y: auto;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    display: none;
}
.autocomplete-dropdown .ac-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}
.autocomplete-dropdown .ac-item:hover {
    background-color: #e9ecef;
}
.autocomplete-dropdown .ac-item:last-child {
    border-bottom: none;
}
.autocomplete-dropdown .ac-item .ac-title {
    font-weight: 500;
}
.autocomplete-dropdown .ac-item .ac-meta {
    font-size: 0.85em;
    color: #6c757d;
}
</style>

<script>
var selectedItems = [];
var searchTimeout;

var conditionRatings = @json($conditionRatings ?? []);

document.getElementById('glamSearch').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    var query = this.value.trim();
    var resultsDiv = document.getElementById('searchResults');

    if (query.length < 2) {
        resultsDiv.style.display = 'none';
        return;
    }

    searchTimeout = setTimeout(function() {
        fetch('/api/autocomplete/glam?q=' + encodeURIComponent(query))
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.length > 0) {
                    var html = '';
                    data.forEach(function(item) {
                        var isSelected = selectedItems.some(function(s) { return s.id === item.id; });
                        if (!isSelected) {
                            html += '<div class="ac-item" onclick=\'addItem(' + JSON.stringify(item) + ')\'>'
                                + '<div class="ac-title">' + escapeHtml(item.value) + '</div>'
                                + '<div class="ac-meta">'
                                + (item.identifier ? '<code>' + escapeHtml(item.identifier) + '</code> &bull; ' : '')
                                + (item.level ? '<span class="badge bg-secondary">' + escapeHtml(item.level) + '</span>' : '')
                                + '</div></div>';
                        }
                    });
                    if (html) {
                        resultsDiv.innerHTML = html;
                        resultsDiv.style.display = 'block';
                    } else {
                        resultsDiv.innerHTML = '<div class="ac-item text-muted">All matching items already added</div>';
                        resultsDiv.style.display = 'block';
                    }
                } else {
                    resultsDiv.innerHTML = '<div class="ac-item text-muted">No results found</div>';
                    resultsDiv.style.display = 'block';
                }
            })
            .catch(function(error) {
                console.error('Search error:', error);
                resultsDiv.style.display = 'none';
            });
    }, 300);
});

function addItem(item) {
    selectedItems.push(item);
    renderSelectedItems();
    document.getElementById('glamSearch').value = '';
    document.getElementById('searchResults').style.display = 'none';
}

function removeItem(id) {
    selectedItems = selectedItems.filter(function(item) { return item.id !== id; });
    renderSelectedItems();
}

function renderSelectedItems() {
    var tbody = document.getElementById('selectedItemsBody');
    var table = document.getElementById('selectedItemsTable');
    var noItemsMsg = document.getElementById('noItemsMessage');

    if (selectedItems.length === 0) {
        table.style.display = 'none';
        noItemsMsg.style.display = 'block';
        return;
    }

    table.style.display = 'table';
    noItemsMsg.style.display = 'none';

    var conditionOptions = '<option value="">Select...</option>';
    for (var key in conditionRatings) {
        conditionOptions += '<option value="' + key + '">' + conditionRatings[key] + '</option>';
    }

    tbody.innerHTML = selectedItems.map(function(item) {
        return '<tr>'
            + '<td><input type="hidden" name="information_object_ids[]" value="' + item.id + '">'
            + '<strong>' + escapeHtml(item.value) + '</strong><br>'
            + '<small class="text-muted">' + (item.identifier ? escapeHtml(item.identifier) : '') + '</small></td>'
            + '<td><select name="condition_ratings[]" class="form-select form-select-sm">' + conditionOptions + '</select></td>'
            + '<td><input type="number" name="declared_values[]" class="form-control form-control-sm" step="0.01" placeholder="0.00"></td>'
            + '<td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(' + item.id + ')"><i class="fas fa-times"></i></button></td>'
            + '</tr>';
    }).join('');
}

function clearSearch() {
    document.getElementById('glamSearch').value = '';
    document.getElementById('searchResults').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#glamSearch') && !e.target.closest('#searchResults')) {
        document.getElementById('searchResults').style.display = 'none';
    }
});

document.getElementById('glamSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('searchResults').style.display = 'none';
    }
});
</script>
@endsection
