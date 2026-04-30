{{-- Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems — johan@plainsailingisystems.co.za
     This file is part of Heratio. Licensed under the GNU Affero General Public License v3+. --}}
@extends('theme::layouts.1col')

@section('title', 'Data Subject Requests')

@section('content')
@php
    $currentStatus = request('status');
    $statusColors = ['pending' => 'warning', 'in_progress' => 'info', 'completed' => 'success', 'rejected' => 'danger', 'extended' => 'secondary'];
@endphp

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="{{ __('breadcrumb') }}">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('ahgcdpa.index') }}">CDPA</a></li>
                    <li class="breadcrumb-item active">Data Subject Requests</li>
                </ol>
            </nav>
            <h1><i class="fas fa-user-clock me-2"></i>Data Subject Requests</h1>
            <p class="text-muted">Statutory response deadline per CDPA requirements</p>
        </div>
        <div class="col-auto">
            <a href="{{ route('ahgcdpa.request-create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i> {{ __('New Request') }}
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="btn-group" role="group">
                <a href="{{ route('ahgcdpa.requests') }}" class="btn btn-{{ !$currentStatus ? 'primary' : 'outline-primary' }}">All</a>
                <a href="{{ route('ahgcdpa.requests', ['status' => 'pending']) }}" class="btn btn-{{ $currentStatus === 'pending' ? 'warning' : 'outline-warning' }}">Pending</a>
                <a href="{{ route('ahgcdpa.requests', ['status' => 'in_progress']) }}" class="btn btn-{{ $currentStatus === 'in_progress' ? 'info' : 'outline-info' }}">In Progress</a>
                <a href="{{ route('ahgcdpa.requests', ['status' => 'completed']) }}" class="btn btn-{{ $currentStatus === 'completed' ? 'success' : 'outline-success' }}">Completed</a>
                <a href="{{ route('ahgcdpa.requests', ['status' => 'rejected']) }}" class="btn btn-{{ $currentStatus === 'rejected' ? 'danger' : 'outline-danger' }}">Rejected</a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            @if ($requests->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-inbox fa-3x mb-3"></i>
                    <p>No requests found.</p>
                </div>
            @else
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ __('Reference') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Data Subject') }}</th>
                            <th>{{ __('Request Date') }}</th>
                            <th>{{ __('Due Date') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requests as $req)
                            @php $isOverdue = $req->status === 'pending' && strtotime($req->due_date) < time(); @endphp
                            <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                                <td>
                                    <a href="{{ route('ahgcdpa.request-view', ['id' => $req->id]) }}">{{ $req->reference_number }}</a>
                                </td>
                                <td><span class="badge bg-secondary">{{ ucfirst($req->request_type) }}</span></td>
                                <td>{{ $req->data_subject_name }}</td>
                                <td>{{ $req->request_date }}</td>
                                <td>
                                    @if ($isOverdue)
                                        <span class="text-danger fw-bold">{{ $req->due_date }} (OVERDUE)</span>
                                    @else
                                        {{ $req->due_date }}
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $statusColors[$req->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $req->status)) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('ahgcdpa.request-view', ['id' => $req->id]) }}" class="btn btn-sm atom-btn-outline-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if (method_exists($requests, 'links'))
                    <div class="p-3">{{ $requests->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</div>
@endsection
