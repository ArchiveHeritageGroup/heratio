@extends('theme::layouts.1col')
@section('title', 'Subscription: ' . ($serial->title ?? ''))
@section('content')
<div class="container py-4">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="mb-0">Subscription</h2>
            <span class="badge bg-info mt-1">{{ e($serial->title ?? '') }}</span>
        </div>
    </div>

    @if(session('serial_success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('serial_success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('library.serial-subscription-store', $serial->id ?? 0) }}">
        @csrf
        <div class="card shadow-sm">
            <div class="card-header" style="background:var(--ahg-primary);color:#fff">
                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Subscription Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="subscription_start" class="form-label">Subscription Start</label>
                        <input type="date" name="subscription_start" id="subscription_start" class="form-control"
                               value="{{ old('subscription_start', $subscription->subscription_start ?? '') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="subscription_end" class="form-label">Subscription End</label>
                        <input type="date" name="subscription_end" id="subscription_end" class="form-control"
                               value="{{ old('subscription_end', $subscription->subscription_end ?? '') }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="subscription_cost" class="form-label">Annual Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">R</span>
                            <input type="number" step="0.01" min="0" name="subscription_cost" id="subscription_cost"
                                   class="form-control" value="{{ old('subscription_cost', $subscription->subscription_cost ?? '') }}">
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="auto_claim_max" class="form-label">Max Auto-Claims</label>
                        <input type="number" min="0" max="12" name="auto_claim_max" id="auto_claim_max"
                               class="form-control" value="{{ old('auto_claim_max', $subscription->auto_claim_max ?? 3) }}">
                        <div class="form-text">Number of automatic claim attempts before suspending the subscription.</div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="notification_email" class="form-label">Notification Email</label>
                        <input type="email" name="notification_email" id="notification_email" class="form-control"
                               value="{{old('notification_email', $subscription->notification_email ?? '') }}">
                        <div class="form-text">Receive overdue-issue alerts at this address.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea name="notes" id="notes" class="form-control" rows="3">{{ old('notes', $subscription->notes ?? '') }}</textarea>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i>Save Subscription
                </button>
                <a href="{{ route('library.serial-view', $serial->id ?? '') }}" class="btn btn-outline-secondary ms-2">
                    Back to Serial
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
