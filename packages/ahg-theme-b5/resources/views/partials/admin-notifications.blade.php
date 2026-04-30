{{-- Notification bars — pending items requiring attention --}}
{{-- Shown to admins (all notifications) and any authenticated user with Spectrum tasks --}}
@php
  $isAdmin = $themeData['isAdmin'] ?? false;
  $isAuth  = $themeData['isAuthenticated'] ?? false;
  $userId  = $isAuth ? auth()->id() : null;

  $notifications = [];
  $spectrumTaskCount = 0;

  // Spectrum workflow tasks — for ANY authenticated user (matching AtoM)
  if ($isAuth && $userId) {
      try {
          $spectrumTaskCount = \AhgSpectrum\Services\SpectrumNotificationService::getActiveTaskCount($userId);
      } catch (\Exception $e) {}
  }

  // Only continue if admin, or user has spectrum tasks
  $showBar = $isAdmin || $spectrumTaskCount > 0;
@endphp

@if($showBar)
  @php
    // Admin-only notifications
    if ($isAdmin) {
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
                    'action' => 'Review',
                ];
            }
        } catch (\Exception $e) {}

        // Background jobs with errors
        try {
            $errorJobs = \Illuminate\Support\Facades\DB::table('job')
                ->where('status_id', 167)
                ->count();
            if ($errorJobs > 0) {
                $notifications[] = [
                    'type' => 'danger',
                    'icon' => 'fa-exclamation-triangle',
                    'message' => $errorJobs . ' job' . ($errorJobs > 1 ? 's' : '') . ' with errors',
                    'url' => url('/jobs/browse'),
                    'action' => 'Review',
                ];
            }
        } catch (\Exception $e) {}

        // Unread error log entries (only Heratio errors)
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
                    'action' => 'View Errors',
                ];
            }
        } catch (\Exception $e) {}
    }

    // Spectrum task notification — for ANY authenticated user
    if ($spectrumTaskCount > 0) {
        $notifications[] = [
            'type' => 'primary',
            'icon' => 'fa-clipboard-list',
            'message' => 'You have ' . $spectrumTaskCount . ' task' . ($spectrumTaskCount > 1 ? 's' : '') . ' assigned to you',
            'url' => route('ahgspectrum.my-tasks'),
            'action' => 'View Tasks',
        ];
    }
  @endphp

  @foreach($notifications as $notif)
    <div class="alert alert-{{ $notif['type'] }} alert-dismissible fade show d-flex align-items-center justify-content-center py-2 mb-0 rounded-0 border-0" role="alert">
      <div class="container-xxl d-flex align-items-center">
        <i class="fas {{ $notif['icon'] }} me-2"></i>
        <span class="flex-grow-1">{{ $notif['message'] }}</span>
        <a href="{{ $notif['url'] }}" class="btn btn-sm btn-light ms-2">{{ $notif['action'] }}</a>
        <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
      </div>
    </div>
  @endforeach
@endif
