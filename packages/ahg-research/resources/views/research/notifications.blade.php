@extends('theme::layouts.2col')
@section('sidebar')@include('research::research._sidebar')@endsection
@section('title-block')<h1><i class="fas fa-bell me-2"></i>Notifications</h1>@endsection
@section('content')
@php
    $unreadCount = collect($notifications)->where('is_read', 0)->count();
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        @if($unreadCount > 0)
            <span class="badge bg-primary">{{ $unreadCount }} unread</span>
        @else
            <span class="text-muted">All caught up</span>
        @endif
    </div>
    @if($unreadCount > 0)
    <form method="POST" class="d-inline">
        @csrf
        <input type="hidden" name="do" value="mark_all_read">
        <button type="submit" class="btn atom-btn-white btn-sm"><i class="fas fa-check-double me-1"></i>Mark All Read</button>
    </form>
    @endif
</div>

@if(count($notifications) > 0)
<div class="list-group">
    @foreach($notifications as $notification)
    @php
        $isUnread = !($notification->is_read ?? false);
        $typeIcons = [
            'booking' => 'fa-calendar-check',
            'approval' => 'fa-user-check',
            'rejection' => 'fa-user-times',
            'collection' => 'fa-folder',
            'report' => 'fa-file-alt',
            'reproduction' => 'fa-copy',
            'system' => 'fa-cog',
            'reminder' => 'fa-clock',
        ];
        $icon = $typeIcons[$notification->notification_type ?? 'system'] ?? 'fa-bell';
        $typeColors = [
            'booking' => 'text-primary',
            'approval' => 'text-success',
            'rejection' => 'text-danger',
            'collection' => 'text-info',
            'report' => 'text-secondary',
            'reproduction' => 'text-warning',
            'system' => 'text-muted',
            'reminder' => 'text-warning',
        ];
        $iconColor = $typeColors[$notification->notification_type ?? 'system'] ?? 'text-muted';
    @endphp
    <div class="list-group-item {{ $isUnread ? 'list-group-item-light border-start border-primary border-3' : '' }}">
        <div class="d-flex justify-content-between align-items-start">
            <div class="d-flex gap-3 align-items-start">
                <div class="mt-1">
                    <i class="fas {{ $icon }} fa-lg {{ $iconColor }}"></i>
                </div>
                <div>
                    @if($notification->title ?? null)
                        <strong>{{ e($notification->title) }}</strong><br>
                    @endif
                    <span class="{{ $isUnread ? 'fw-semibold' : '' }}">{{ e($notification->message ?? '') }}</span>
                    <br>
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>{{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                        @if($notification->notification_type ?? null)
                            <span class="ms-2 badge bg-light text-dark">{{ ucfirst($notification->notification_type) }}</span>
                        @endif
                    </small>
                </div>
            </div>
            <div>
                @if($isUnread)
                <form method="POST" class="d-inline">
                    @csrf
                    <input type="hidden" name="do" value="mark_read">
                    <input type="hidden" name="id" value="{{ $notification->id }}">
                    <button type="submit" class="btn atom-btn-white btn-sm" title="Mark as read"><i class="fas fa-check"></i></button>
                </form>
                @else
                <span class="text-muted" title="Read"><i class="fas fa-check-circle"></i></span>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@else
<div class="text-center text-muted py-4">
    <i class="fas fa-bell-slash fa-3x mb-3 d-block"></i>
    No notifications yet.
</div>
@endif
@endsection
