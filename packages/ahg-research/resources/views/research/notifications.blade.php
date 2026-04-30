@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar', ['sidebarActive' => 'notifications'])@endsection
@section('title', 'Notifications')

@section('content')
@php
    $unreadCount = collect($notifications)->where('is_read', 0)->count();
    $currentTab = request('tab', 'all');
    $currentType = request('type', '');
    $typeIcons = [
        'alert' => 'fas fa-exclamation-triangle text-warning',
        'invitation' => 'fas fa-user-plus text-primary',
        'comment' => 'fas fa-comment text-info',
        'reply' => 'fas fa-reply text-secondary',
        'system' => 'fas fa-cog text-dark',
        'reminder' => 'fas fa-clock text-success',
        'collaboration' => 'fas fa-users text-info',
        'booking' => 'fas fa-calendar-check text-primary',
        'approval' => 'fas fa-user-check text-success',
        'rejection' => 'fas fa-user-times text-danger',
        'reproduction' => 'fas fa-copy text-warning',
    ];
@endphp

<nav aria-label="{{ __('breadcrumb') }}" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('research.dashboard') }}">Research</a></li>
        <li class="breadcrumb-item active">Notifications</li>
    </ol>
</nav>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1><i class="fas fa-bell text-primary me-2"></i>Notifications</h1>
    @if($unreadCount > 0)
    <form method="POST"><@csrf<input type="hidden" name="do" value="mark_all_read">
        <button type="submit" class="btn btn-outline-secondary btn-sm"><i class="fas fa-check-double me-1"></i>{{ __('Mark All Read') }}</button>
    </form>
    @endif
</div>

{{-- Tabs --}}
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link {{ ($currentTab === 'all' && empty($currentType)) ? 'active' : '' }}" href="{{ route('research.notifications', ['tab' => 'all']) }}">All</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ $currentTab === 'unread' ? 'active' : '' }}" href="{{ route('research.notifications', ['tab' => 'unread']) }}">
            Unread @if($unreadCount > 0)<span class="badge bg-danger ms-1">{{ $unreadCount }}</span>@endif
        </a>
    </li>
    <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle {{ !empty($currentType) ? 'active' : '' }}" data-bs-toggle="dropdown" href="#">By Type</a>
        <ul class="dropdown-menu">
            @foreach(['alert' => 'Alerts', 'invitation' => 'Invitations', 'comment' => 'Comments', 'reply' => 'Replies', 'system' => 'System', 'reminder' => 'Reminders', 'collaboration' => 'Collaboration', 'booking' => 'Bookings'] as $tk => $tl)
                <li><a class="dropdown-item {{ $currentType === $tk ? 'active' : '' }}" href="{{ route('research.notifications', ['tab' => 'all', 'type' => $tk]) }}"><i class="{{ $typeIcons[$tk] ?? 'fas fa-bell' }} me-2"></i>{{ $tl }}</a></li>
            @endforeach
        </ul>
    </li>
    <li class="nav-item ms-auto">
        <a class="nav-link {{ $currentTab === 'preferences' ? 'active' : '' }}" href="{{ route('research.notifications', ['tab' => 'preferences']) }}"><i class="fas fa-cog me-1"></i>{{ __('Preferences') }}</a>
    </li>
</ul>

@if($currentTab === 'preferences')
    {{-- Preferences --}}
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Notification Preferences</h5></div>
        <div class="card-body">
            <form method="POST">
                @csrf
                <input type="hidden" name="do" value="update_preferences">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr><th>{{ __('Notification Type') }}</th><th class="text-center">{{ __('In-App') }}</th><th class="text-center">{{ __('Email') }}</th><th>{{ __('Digest Frequency') }}</th></tr>
                        </thead>
                        <tbody>
                        @foreach(['alert' => 'Alerts', 'invitation' => 'Invitations', 'comment' => 'Comments', 'reply' => 'Replies', 'system' => 'System', 'reminder' => 'Reminders', 'collaboration' => 'Collaboration'] as $tk => $tl)
                            @php $pref = $preferences[$tk] ?? (object)['email_enabled' => 1, 'in_app_enabled' => 1, 'digest_frequency' => 'immediate']; if(is_array($pref)) $pref = (object)$pref; @endphp
                            <tr>
                                <td><i class="{{ $typeIcons[$tk] ?? 'fas fa-bell' }} me-2"></i>{{ $tl }}</td>
                                <td class="text-center"><input type="checkbox" name="prefs[{{ $tk }}][in_app_enabled]" value="1" class="form-check-input" {{ ($pref->in_app_enabled ?? 1) ? 'checked' : '' }}></td>
                                <td class="text-center"><input type="checkbox" name="prefs[{{ $tk }}][email_enabled]" value="1" class="form-check-input" {{ ($pref->email_enabled ?? 1) ? 'checked' : '' }}></td>
                                <td>
                                    <select name="prefs[{{ $tk }}][digest_frequency]" class="form-select form-select-sm" style="width:150px;">
                                        @foreach(['immediate' => 'Immediate', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'never' => 'Never'] as $fk => $fl)
                                            <option value="{{ $fk }}" {{ (($pref->digest_frequency ?? 'immediate') === $fk) ? 'selected' : '' }}>{{ $fl }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>{{ __('Save Preferences') }}</button>
            </form>
        </div>
    </div>
@else
    {{-- Notification List --}}
    @php
        $filtered = collect($notifications);
        if ($currentTab === 'unread') $filtered = $filtered->where('is_read', 0);
        if ($currentType) $filtered = $filtered->where('notification_type', $currentType);
    @endphp

    @if($filtered->isNotEmpty())
    <div class="list-group">
        @foreach($filtered as $n)
        <div class="list-group-item list-group-item-action d-flex align-items-start gap-3 {{ empty($n->is_read) ? 'bg-light border-start border-primary border-3' : '' }}">
            <div class="flex-shrink-0 mt-1">
                <i class="{{ $typeIcons[$n->notification_type ?? $n->type ?? 'system'] ?? 'fas fa-bell text-muted' }} fa-lg"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="mb-0 {{ empty($n->is_read) ? 'fw-bold' : 'fw-normal' }}">
                            @if($n->link ?? null)
                                <a href="{{ $n->link }}" class="text-decoration-none">{{ e($n->title ?? $n->message ?? '') }}</a>
                            @else
                                {{ e($n->title ?? '') }}
                            @endif
                        </h6>
                        @if($n->message ?? null)
                            <p class="text-muted small mb-0 mt-1">{{ e(\Illuminate\Support\Str::limit($n->message, 150)) }}</p>
                        @endif
                    </div>
                    <div class="text-end ms-3 flex-shrink-0">
                        <small class="text-muted">{{ date('M j, H:i', strtotime($n->created_at)) }}</small>
                        @if(empty($n->is_read))
                        <form method="POST" class="d-inline ms-1">
                            @csrf
                            <input type="hidden" name="do" value="mark_read">
                            <input type="hidden" name="id" value="{{ $n->id }}">
                            <button type="submit" class="btn btn-link btn-sm p-0" title="{{ __('Mark as read') }}"><i class="fas fa-check text-muted"></i></button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-5">
        <i class="fas fa-bell-slash fa-4x text-muted mb-3 opacity-50"></i>
        <h4 class="text-muted">{{ __('No notifications') }}</h4>
        <p class="text-muted">{{ $currentTab === 'unread' ? 'You have no unread notifications.' : 'You have no notifications yet.' }}</p>
    </div>
    @endif
@endif
@endsection
