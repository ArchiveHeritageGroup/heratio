{{-- Admin notification bars — pending items requiring attention --}}
@if($themeData['isAdmin'] ?? false)
  @php
    $notifications = [];

    // Pending access requests
    try {
        $pendingAccess = \Illuminate\Support\Facades\DB::table('access_request')
            ->where('status', 'pending')
            ->count();
        if ($pendingAccess > 0) {
            $notifications[] = [
                'type' => 'warning',
                'icon' => 'fa-key',
                'message' => $pendingAccess . ' pending access request' . ($pendingAccess > 1 ? 's' : ''),
                'url' => url('/admin/accessRequests'),
            ];
        }
    } catch (\Exception $e) {}

    // Background jobs with errors
    try {
        $errorJobs = \Illuminate\Support\Facades\DB::table('job')
            ->where('status_id', 167) // error status
            ->count();
        if ($errorJobs > 0) {
            $notifications[] = [
                'type' => 'danger',
                'icon' => 'fa-exclamation-triangle',
                'message' => $errorJobs . ' job' . ($errorJobs > 1 ? 's' : '') . ' with errors',
                'url' => url('/jobs/browse'),
            ];
        }
    } catch (\Exception $e) {}

    // Spectrum workflow task notifications
    try {
        $spectrumUnread = \Illuminate\Support\Facades\DB::table('spectrum_notification')
            ->where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();
        if ($spectrumUnread > 0) {
            $notifications[] = [
                'type' => 'info',
                'icon' => 'fa-tasks',
                'message' => $spectrumUnread . ' unread Spectrum notification' . ($spectrumUnread > 1 ? 's' : ''),
                'url' => route('ahgspectrum.notifications'),
            ];
        }
    } catch (\Exception $e) {}

    // Unread error log entries (only Heratio errors, not AtoM/registry)
    try {
        $heratioHost = request()->getHost();
        $unreadErrors = \Illuminate\Support\Facades\DB::table('ahg_error_log')
            ->where('is_read', 0)
            ->where('url', 'LIKE', '%' . $heratioHost . '%')
            ->count();
        if ($unreadErrors > 0) {
            $recentError = \Illuminate\Support\Facades\DB::table('ahg_error_log')
                ->where('is_read', 0)
                ->where('url', 'LIKE', '%' . $heratioHost . '%')
                ->orderByDesc('created_at')
                ->first();
            $errorMsg = $unreadErrors . ' unread error' . ($unreadErrors > 1 ? 's' : '');
            if ($recentError) {
                $errorMsg .= ' — latest: ' . \Illuminate\Support\Str::limit($recentError->message, 80);
            }
            $notifications[] = [
                'type' => 'danger',
                'icon' => 'fa-bug',
                'message' => $errorMsg,
                'url' => route('settings.error-log'),
            ];
        }
    } catch (\Exception $e) {}
  @endphp

  @foreach($notifications as $notif)
    <div class="alert alert-{{ $notif['type'] }} rounded-0 mb-0 py-1 d-flex align-items-center justify-content-between">
      <span>
        <i class="fas {{ $notif['icon'] }} me-2"></i>
        {{ $notif['message'] }}
      </span>
      <a href="{{ $notif['url'] }}" class="btn btn-sm btn-outline-{{ $notif['type'] }}">Review</a>
    </div>
  @endforeach
@endif
