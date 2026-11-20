{{--
    Release clearance for one audio/video master (heratio#1771), shown under
    the media player to staff only. Administrators and editors can clear the
    media for release or hold it back; the change is audited.

    Variables: $prot - MediaProtection::playerState().
--}}
@php
    $__c = $prot['clearance'];
    $__u = auth()->user();
    $__canDecide = $__u && (\AhgCore\Services\AclService::isAdministrator($__u) || \AhgCore\Services\AclService::isEditor($__u));
    $__by = ($__c && $__c->cleared_by) ? \Illuminate\Support\Facades\DB::table('user')->where('id', $__c->cleared_by)->value('username') : null;
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mt-2 small text-start" data-media-clearance="{{ $prot['master']->id }}">
    @if($prot['needsClearance'])
        <span class="badge bg-warning text-dark"><i class="fas fa-lock me-1" aria-hidden="true"></i>{{ __('Not cleared for release - staff only') }}</span>
    @elseif($__c && $__c->status === \AhgCore\Services\MediaProtection::STATUS_CLEARED)
        <span class="badge bg-success"><i class="fas fa-check me-1" aria-hidden="true"></i>{{ __('Cleared for release') }}</span>
        <span class="text-muted">{{ __('by :user on :date', ['user' => $__by ?? '?', 'date' => \Illuminate\Support\Carbon::parse($__c->cleared_at)->format('Y-m-d H:i')]) }}</span>
    @endif
    @if($__canDecide && \Illuminate\Support\Facades\Route::has('media-streaming.clearance'))
        <form method="post" action="{{ route('media-streaming.clearance', $prot['master']->id) }}" class="d-inline">
            @csrf
            @if($prot['needsClearance'])
                <input type="hidden" name="decision" value="clear">
                <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Clear for release') }}</button>
            @else
                <input type="hidden" name="decision" value="withhold">
                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ __('Hold back from release') }}</button>
            @endif
        </form>
    @endif
</div>
