{{--
  Discussion row for community / group threads.
  Vars: $item (or $disc), $groupSlug (optional).

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
--}}
@php
    $disc = (object) ($item ?? $disc ?? null);
    $topicIcons = [
        'discussion' => 'fas fa-comments text-primary',
        'question' => 'fas fa-question-circle text-success',
        'announcement' => 'fas fa-bullhorn text-warning',
        'event' => 'fas fa-calendar-alt text-info',
        'showcase' => 'fas fa-star text-purple',
        'help' => 'fas fa-life-ring text-danger',
    ];
    $tt = $disc->topic_type ?? 'discussion';
    $tIcon = $topicIcons[$tt] ?? 'fas fa-comments text-muted';
    $isPinned = ! empty($disc->is_pinned);
    $isLocked = ! empty($disc->is_locked);
    $isResolved = ! empty($disc->is_resolved);
    $href = \Illuminate\Support\Facades\Route::has('registry.discussionView')
        ? route('registry.discussionView', ['id' => (int) ($disc->id ?? 0)])
        : '#';
    $actTime = strtotime($disc->last_activity_at ?? $disc->last_reply_at ?? $disc->created_at ?? 'now');
    $diff = time() - $actTime;
    $ago = match (true) {
        $diff < 60     => __('just now'),
        $diff < 3600   => trans_choice(':n min ago|:n min ago', (int) floor($diff / 60), ['n' => (int) floor($diff / 60)]),
        $diff < 86400  => trans_choice(':n hours ago|:n hours ago', (int) floor($diff / 3600), ['n' => (int) floor($diff / 3600)]),
        default        => trans_choice(':n days ago|:n days ago', (int) floor($diff / 86400), ['n' => (int) floor($diff / 86400)]),
    };
@endphp
<a href="{{ $href }}" class="list-group-item list-group-item-action @if ($isPinned)list-group-item-warning @endif">
    <div class="d-flex align-items-start">
        <div class="me-3 text-center flex-shrink-0" style="min-width: 30px;">
            <i class="{{ $tIcon }}" title="{{ ucfirst($tt) }}"></i>
        </div>
        <div class="flex-grow-1 min-width-0">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h6 class="mb-1">
                        @if ($isPinned)<i class="fas fa-thumbtack text-warning me-1 small"></i>@endif
                        @if ($isLocked)<i class="fas fa-lock text-secondary me-1 small"></i>@endif
                        {{ $disc->title ?? '' }}
                        @if ($isResolved)<span class="badge bg-success ms-1">{{ __('Resolved') }}</span>@endif
                    </h6>
                    <small class="text-muted">
                        {{ $disc->author_name ?? '' }}
                        &middot; {{ ! empty($disc->created_at) ? \Carbon\Carbon::parse($disc->created_at)->format('M j, Y') : '' }}
                        @if (! empty($disc->last_reply_at) && ($disc->last_reply_at ?? '') !== ($disc->created_at ?? ''))
                            &middot; {{ __('Last reply') }} {{ \Carbon\Carbon::parse($disc->last_reply_at)->format('M j') }}
                        @endif
                    </small>
                </div>
                <div class="text-end text-nowrap ms-2 flex-shrink-0">
                    <span class="badge bg-primary" title="{{ __('Replies') }}">{{ (int) ($disc->reply_count ?? 0) }}</span>
                    <br>
                    <small class="text-muted"><i class="fas fa-eye"></i> {{ (int) ($disc->view_count ?? 0) }}</small>
                    <br><small class="text-muted">{{ $ago }}</small>
                </div>
            </div>
        </div>
    </div>
</a>
