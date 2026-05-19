{{--
    auth-res::_park-modal - reason textarea, parks the mention for later.

    Args:
        $mention : object - mention row
--}}
<div id="auth-res-park-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4"
     role="dialog" aria-modal="true">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Park for later</h2>
                <p class="text-xs text-slate-500 mt-1">
                    The mention will sit in the parked queue. The Task 7 rescan job flags it when
                    a fresh candidate becomes available.
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" data-auth-res-close="park">&times;</button>
        </div>

        <form method="POST"
              action="{{ route('auth-res.review.park', ['mention' => $mention->id]) }}"
              class="mt-4">
            @csrf

            <label class="block text-xs font-medium text-slate-700 mb-1">Reason (required)</label>
            <textarea name="reason" rows="4" required
                      class="w-full rounded-md border-slate-300 text-sm"
                      placeholder="e.g. waiting for VIAF record to surface; conflicting dates need archivist review..."></textarea>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        data-auth-res-close="park">Cancel</button>
                <button type="submit"
                        class="rounded-md bg-amber-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-amber-500">
                    Park mention
                </button>
            </div>
        </form>
    </div>
</div>
