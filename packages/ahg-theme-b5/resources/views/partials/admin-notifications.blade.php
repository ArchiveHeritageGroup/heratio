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
