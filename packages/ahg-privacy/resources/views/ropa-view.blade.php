@extends('theme::layouts.1col')

@section('content')
@php
  // Cloned from PSIS ropaViewSuccess — the original instantiated a PrivacyService directly;
  // heratio doesn't have that class yet, so we fall back to empty collections and standard auth().
  $rawBases = $lawfulBases ?? [];
  $isOfficer = auth()->check() && method_exists(auth()->user(), 'isAdministrator') && auth()->user()->isAdministrator();
  $approvalHistory = $approvalHistory ?? collect();
  $officers = $officers ?? collect();
@endphp

@php
$statusClasses = [
    'draft' => 'secondary',
    'pending_review' => 'warning',
    'approved' => 'success',
    'archived' => 'dark'
];
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('ahgprivacy.ropa-list') }}" class="btn btn-outline-secondary btn-sm me-3">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="h2 mb-0">
                    <i class="fas fa-clipboard-list me-2"></i>
                    {{ $activity->name }}
                </h1>
                <small class="text-muted">{{ __('Processing Activity Record') }}</small>
            </div>
        </div>
        <div>
            <span class="badge bg-{{ $statusClasses[$activity->status] ?? 'secondary' }} fs-6">
                {{ ucfirst(str_replace('_', ' ', $activity->status)) }}
            </span>
            @if($activity->status === 'draft')
            <a href="{{ route('ahgprivacy.ropa-edit', ['id' => $activity->id]) }}" class="btn btn-primary ms-2">
                <i class="fas fa-edit me-1"></i>{{ __('Edit') }}
            </a>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($activity->rejection_reason)
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>{{ __('Changes Requested') }}:</strong> {{ $activity->rejection_reason }}
    </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>{{ __('Processing Activity Details') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Jurisdiction') }}</label>
                            <p class="mb-0"><strong>{{ strtoupper($activity->jurisdiction ?? 'POPIA') }}</strong></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted">{{ __('Lawful Basis') }}</label>
                            <p class="mb-0">{{ isset($rawBases[$activity->lawful_basis]) ? $rawBases[$activity->lawful_basis]['label'] : ucfirst($activity->lawful_basis ?? 'Not specified') }}</p>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Purpose of Processing') }}</label>
                        <p class="mb-0">{!! nl2br(e($activity->purpose)) !!}</p>
                    </div>

                    @if($activity->description)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Description') }}</label>
                        <p class="mb-0">{!! nl2br(e($activity->description)) !!}</p>
                    </div>
                    @endif
                </div>
            </div>

            @if($activity->data_categories || $activity->data_subjects || $activity->recipients)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-database me-2"></i>{{ __('Data Information') }}</h5>
                </div>
                <div class="card-body">
                    @if($activity->data_categories)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Categories of Personal Data') }}</label>
                        <p class="mb-0">{!! nl2br(e($activity->data_categories)) !!}</p>
                    </div>
                    @endif

                    @if($activity->data_subjects)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Categories of Data Subjects') }}</label>
                        <p class="mb-0">{!! nl2br(e($activity->data_subjects)) !!}</p>
                    </div>
                    @endif

                    @if($activity->recipients)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Recipients') }}</label>
                        <p class="mb-0">{!! nl2br(e($activity->recipients)) !!}</p>
                    </div>
                    @endif

                    @if($activity->retention_period)
                    <div class="mb-3">
                        <label class="form-label text-muted">{{ __('Retention Period') }}</label>
                        <p class="mb-0">{{ $activity->retention_period }}</p>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Approval History -->
            @if(count($approvalHistory) > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i>{{ __('Approval History') }}</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($approvalHistory as $log)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    @php
$actionIcons = ['submitted' => 'paper-plane text-primary', 'approved' => 'check-circle text-success', 'rejected' => 'times-circle text-danger'];
                                    $actionIcon = $actionIcons[$log->action] ?? 'circle text-secondary';
@endphp
                                    <i class="fas fa-{{ $actionIcon }} me-2"></i>
                                    <strong>{{ ucfirst($log->action) }}</strong>
                                    {{ __('by') }} {{ $log->username ?? 'Unknown' }}
                                    @if($log->comment)
                                    <br><small class="text-muted ms-4">{{ $log->comment }}</small>
                                    @endif
                                </div>
                                <small class="text-muted">{{ $log->created_at }}</small>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-4">
            <!-- Workflow Actions -->
            <div class="card mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>{{ __('Workflow') }}</h5>
                </div>
                <div class="card-body">
                    @if($activity->status === 'draft')
                        <p class="text-muted small">{{ __('Submit this record for review by a Privacy Officer.') }}</p>
                        <form method="post" action="{{ route('ahgprivacy.ropa-submit', ['id' => $activity->id]) }}">
                            @if(count($officers) > 1)
                            <div class="mb-3">
                                <label class="form-label">{{ __('Assign to Officer') }}</label>
                                <select name="officer_id" class="form-select">
                                    <option value="">{{ __('Auto-assign (Primary Officer)') }}</option>
                                    @foreach($officers as $officer)
                                    <option value="{{ $officer->user_id }}">{{ $officer->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endif
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-1"></i>{{ __('Submit for Review') }}
                            </button>
                        </form>

                    @elseif($activity->status === 'pending_review')
                        <p class="text-muted small">{{ __('This record is pending review.') }}</p>
                        
                        @if($isOfficer)
                        <!-- Approve -->
                        <form method="post" action="{{ route('ahgprivacy.ropa-approve', ['id' => $activity->id]) }}" class="mb-3">
                            <div class="mb-2">
                                <textarea name="comment" class="form-control" rows="2" placeholder="{{ __('Optional comment...') }}"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-check me-1"></i>{{ __('Approve') }}
                            </button>
                        </form>
                        
                        <!-- Reject -->
                        <form method="post" action="{{ route('ahgprivacy.ropa-reject', ['id' => $activity->id]) }}">
                            <div class="mb-2">
                                <textarea name="reason" class="form-control" rows="2" placeholder="{{ __('Reason for rejection (required)...') }}" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fas fa-times me-1"></i>{{ __('Request Changes') }}
                            </button>
                        </form>
                        @else
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-clock me-2"></i>{{ __('Awaiting review by Privacy Officer') }}
                        </div>
                        @endif

                    @elseif($activity->status === 'approved')
                        <div class="text-center">
                            <i class="fas fa-check-circle text-success fa-3x mb-2"></i>
                            <p class="text-success mb-0"><strong>{{ __('Approved') }}</strong></p>
                            @if($activity->approved_at)
                            <small class="text-muted">{{ $activity->approved_at }}</small>
                            @endif
                        </div>
                    @endif
                </div>
            </div>

            <!-- DPIA Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>{{ __('DPIA Status') }}</h5>
                </div>
                <div class="card-body">
                    @if($activity->dpia_required)
                    <p><i class="fas fa-exclamation-triangle text-warning me-2"></i><strong>{{ __('DPIA Required') }}</strong></p>
                    @if($activity->dpia_completed)
                    <p class="text-success mb-0"><i class="fas fa-check me-1"></i>{{ __('Completed') }}
                    @if($activity->dpia_date) - {{ $activity->dpia_date }}@endif</p>
                    @else
                    <p class="text-danger mb-0"><i class="fas fa-times me-1"></i>{{ __('Not Completed') }}</p>
                    @endif
                    @else
                    <p class="text-success mb-0"><i class="fas fa-check-circle me-2"></i>{{ __('DPIA Not Required') }}</p>
                    @endif
                </div>
            </div>

            <!-- Record Info -->
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>{{ __('Record Info') }}</h5>
                </div>
                <div class="card-body small">
                    @if($activity->owner)
                    <p class="mb-2"><strong>{{ __('Owner') }}:</strong> {{ $activity->owner }}</p>
                    @endif
                    @if($activity->department)
                    <p class="mb-2"><strong>{{ __('Department') }}:</strong> {{ $activity->department }}</p>
                    @endif
                    @if($activity->assigned_officer_id)
                    @php
$assignedOfficer = \Illuminate\Database\Capsule\Manager::table('privacy_officer')->where('id', $activity->assigned_officer_id)->first();
                    if ($assignedOfficer):
@endphp
                    <p class="mb-2"><strong>{{ __('Assigned Officer') }}:</strong> {{ $assignedOfficer->name }}</p>
                    @endif
                    @endif
                    <p class="mb-2"><strong>{{ __('Created') }}:</strong> {{ $activity->created_at }}</p>
                    @if($activity->next_review_date)
                    <p class="mb-0"><strong>{{ __('Next Review') }}:</strong> {{ $activity->next_review_date }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
