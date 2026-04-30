@extends('theme::layouts.1col')

@section('title', __('Spectrum Notifications'))

@section('content')

<h1>{{ __('Spectrum Notifications') }}</h1>

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ url('/') }}">{{ __('Home') }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('ahgspectrum.dashboard') }}">{{ __('Spectrum') }}</a></li>
        <li class="breadcrumb-item active">{{ __('Notifications') }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="text-muted">
        {{ $unreadCount }} {{ __('unread notification') }}{{ $unreadCount !== 1 ? 's' : '' }}
    </span>
    @if ($unreadCount > 0)
    <form method="post" action="{{ route('ahgspectrum.notification.mark-all-read') }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-check-double me-1"></i> {{ __('Mark all as read') }}
        </button>
    </form>
    @endif
</div>

@if (empty($notifications))
<div class="alert alert-info">
    {{ __('No notifications.') }}
</div>
@else
<div class="list-group">
    @foreach ($notifications as $notif)
    @php $isUnread = empty($notif->read_at); @endphp
    <div class="list-group-item {{ $isUnread ? 'list-group-item-light' : '' }} d-flex justify-content-between align-items-start">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center mb-1">
                @if ($isUnread)
                <span class="badge bg-primary me-2">{{ __('New') }}</span>
                @endif
                @if ($notif->notification_type === 'task_assignment')
                <i class="fas fa-tasks text-primary me-2"></i>
                @else
                <i class="fas fa-exchange-alt text-info me-2"></i>
                @endif
                <strong>{{ $notif->subject }}</strong>
            </div>
            <div class="text-muted small" style="white-space: pre-line;">{{ $notif->message }}</div>
            <small class="text-muted">{{ $notif->created_at }}</small>
        </div>
        @if ($isUnread)
        <form method="post" action="{{ route('ahgspectrum.notification.mark-read') }}" class="ms-2">
            @csrf
            <input type="hidden" name="id" value="{{ $notif->id }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ __('Mark as read') }}">
                <i class="fas fa-check"></i>
            </button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endif

@endsection
