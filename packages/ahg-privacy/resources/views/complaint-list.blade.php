@extends('theme::layouts.1col')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('ahgprivacy.index') }}" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            <span class="h2"><i class="fas fa-exclamation-circle me-2 text-warning"></i>{{ __('Privacy Complaints') }}</span>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">{{ __('All Statuses') }}</option>
                        <option value="received">{{ __('Received') }}</option>
                        <option value="investigating">{{ __('Investigating') }}</option>
                        <option value="resolved">{{ __('Resolved') }}</option>
                        <option value="escalated">{{ __('Escalated') }}</option>
                        <option value="closed">{{ __('Closed') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('Reference') }}</th>
                        <th>{{ __('Type') }}</th>
                        <th>{{ __('Complainant') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @if($complaints->isEmpty())
                    <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No complaints') }}</td></tr>
                    @else
                    @foreach($complaints as $c)
                    @php
$statusClasses = [
                        'received' => 'secondary', 'investigating' => 'primary', 
                        'resolved' => 'success', 'escalated' => 'danger', 'closed' => 'dark'
                    ];
@endphp
                    <tr>
                        <td><strong>{{ $c->reference_number }}</strong></td>
                        <td>{{ $complaintTypes[$c->complaint_type] ?? $c->complaint_type }}</td>
                        <td>
                            {{ $c->complainant_name }}
                            @if($c->complainant_email)
                            <br><small class="text-muted">{{ $c->complainant_email }}</small>
                            @endif
                        </td>
                        <td>{{ date('d M Y', strtotime($c->created_at)) }}</td>
                        <td>
                            <span class="badge bg-{{ $statusClasses[$c->status] ?? 'secondary' }}">
                                {{ ucfirst($c->status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('ahgprivacy.complaint-view', ['id' => $c->id]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
