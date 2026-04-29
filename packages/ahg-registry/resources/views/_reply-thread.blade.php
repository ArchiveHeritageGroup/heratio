{{--
  Cloned from atom-ahg-plugins/ahgRegistryPlugin/modules/registry/templates/_replyThread.php

  Vars: $reply (single) OR $replies (collection), $level (default 0), $discussionId, $groupSlug
  Recursive: included partial calls itself for $r->children up to $maxDepth.
--}}
@php
    $maxDepth = 4;
    $currentLevel = isset($level) ? (int) $level : (isset($depth) ? (int) $depth : 0);

    $replyList = [];
    if (isset($replies) && is_iterable($replies)) {
        $replyList = $replies;
    } elseif (isset($reply)) {
        $replyList = [$reply];
    }
@endphp
@if (!empty($replyList))
@foreach ($replyList as $r)
@php
    $msMargin = $currentLevel > 0 ? ' ms-' . min($currentLevel * 3, 12) : '';
    $rContent = $r->content ?? '';
    $allowedTags = '<p><br><strong><em><b><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><pre><code><hr>';
    $hasHtml = preg_match('/<[a-z][\s\S]*>/i', $rContent);

    $rAgo = '';
    if (!empty($r->created_at)) {
        $rTime = strtotime($r->created_at);
        $rDiff = time() - $rTime;
        if ($rDiff < 60) $rAgo = __('just now');
        elseif ($rDiff < 3600) $rAgo = sprintf(__('%d min ago'), (int) floor($rDiff / 60));
        elseif ($rDiff < 86400) $rAgo = sprintf(__('%d hours ago'), (int) floor($rDiff / 3600));
        elseif ($rDiff < 604800) $rAgo = sprintf(__('%d days ago'), (int) floor($rDiff / 86400));
        else $rAgo = date('M j, Y g:i A', $rTime);
    }

    $defaultReplyHref = \Illuminate\Support\Facades\Route::has('registry.discussionReply')
        ? route('registry.discussionReply', ['id' => (int) ($discussionId ?? 0)])
        : url('/registry/discussion/' . (int) ($discussionId ?? 0) . '/reply');
    $formAction = isset($replyUrl) && $replyUrl ? $replyUrl : $defaultReplyHref;
@endphp
<div class="card mb-2{{ $msMargin }}" id="reply-{{ (int) ($r->id ?? 0) }}">
  <div class="card-body py-2">
    <div class="d-flex align-items-start">
      <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width: 32px; height: 32px;">
        <i class="fas fa-user text-muted small"></i>
      </div>
      <div class="flex-grow-1">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <strong class="small">{{ $r->author_name ?? '' }}</strong>
            <small class="text-muted ms-2">{{ $rAgo }}</small>
          </div>
          @if (!empty($r->is_accepted_answer))
            <span class="badge bg-success"><i class="fas fa-check me-1"></i>{{ __('Accepted Answer') }}</span>
          @endif
        </div>

        <!-- Reply content -->
        <div class="small mt-1">
          @if ($hasHtml)
            {!! strip_tags($rContent, $allowedTags) !!}
          @else
            {!! nl2br(e($rContent)) !!}
          @endif
        </div>

        <!-- Reply button -->
        @if ($currentLevel < $maxDepth && !empty($discussionId))
        <div class="mt-2">
          <button type="button" class="btn btn-sm btn-outline-secondary reply-toggle-btn" data-reply-id="{{ (int) ($r->id ?? 0) }}">
            <i class="fas fa-reply me-1"></i>{{ __('Reply') }}
          </button>
          <div class="reply-form mt-2" id="reply-form-{{ (int) ($r->id ?? 0) }}" style="display: none;">
            <form method="post" action="{{ $formAction }}">
              @csrf
              <input type="hidden" name="parent_reply_id" value="{{ (int) ($r->id ?? 0) }}">
              <textarea class="form-control form-control-sm mb-2" name="content" rows="2" placeholder="{{ __('Write a reply...') }}" required></textarea>
              <button type="submit" class="btn btn-sm btn-primary">{{ __('Submit') }}</button>
            </form>
          </div>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@if (!empty($r->children) && $currentLevel < $maxDepth)
  @php
    $childParams = [
        'replies' => $r->children,
        'groupSlug' => $groupSlug ?? '',
        'discussionId' => $discussionId ?? 0,
        'level' => $currentLevel + 1,
        'depth' => $currentLevel + 1,
    ];
    if (isset($replyUrl) && $replyUrl) $childParams['replyUrl'] = $replyUrl;
  @endphp
  @include('ahg-registry::_reply-thread', $childParams)
@endif
@endforeach
@endif
