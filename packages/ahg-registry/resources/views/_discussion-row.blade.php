{{-- Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_discussionRow.php --}}
@php
    $topicIcons = [
        'discussion' => 'fas fa-comments text-primary',
        'question' => 'fas fa-question-circle text-success',
        'announcement' => 'fas fa-bullhorn text-warning',
        'event' => 'fas fa-calendar-alt text-info',
        'showcase' => 'fas fa-star text-purple',
        'help' => 'fas fa-life-ring text-danger',
    ];
    // Support both $item and $disc variable names
    $disc = $item ?? ($disc ?? null);
    if (!$disc) return;

    $isPinned = !empty($disc->is_pinned);
    $isLocked = !empty($disc->is_locked);
    $isResolved = !empty($disc->is_resolved);
    $tt = $disc->topic_type ?? 'discussion';
    $tIcon = $topicIcons[$tt] ?? 'fas fa-comments text-muted';

    $gSlug = $disc->group_slug ?? ($groupSlug ?? '');
    $discUrl = $gSlug && \Illuminate\Support\Facades\Route::has('registry.discussionView')
        ? route('registry.discussionView', ['id' => (int) $disc->id])
        : '#';

    $actTime = strtotime($disc->last_activity_at ?? $disc->last_reply_at ?? $disc->created_at ?? 'now');
    $diff = time() - $actTime;
    if ($diff < 60) {
        $ago = __('just now');
    } elseif ($diff < 3600) {
        $ago = sprintf(__('%d min ago'), (int) floor($diff / 60));
    } elseif ($diff < 86400) {
        $ago = sprintf(__('%d hours ago'), (int) floor($diff / 3600));
    } else {
        $ago = sprintf(__('%d days ago'), (int) floor($diff / 86400));
    }
@endphp
<a href="{{ $discUrl }}" class="list-group-item list-group-item-action @if ($isPinned)list-group-item-warning @endif">
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
            @if ($isResolved)
              <span class="badge bg-success ms-1">{{ __('Resolved') }}</span>
            @endif
          </h6>
          <small class="text-muted">
            {{ $disc->author_name ?? '' }}
            &middot; {{ !empty($disc->created_at) ? date('M j, Y', strtotime($disc->created_at)) : '' }}
            @if (!empty($disc->last_reply_at) && ($disc->last_reply_at ?? '') !== ($disc->created_at ?? ''))
              &middot; {{ __('Last reply') }} {{ date('M j', strtotime($disc->last_reply_at)) }}
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
