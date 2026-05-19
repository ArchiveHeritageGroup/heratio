{{--
    auth-res::_reject-modal - Task 9: capture a rejection_reason on the
    "reject as false positive" action. The reason flows through to
    DecisionRecorder::recordReject() and is persisted on ahg_ner_feedback
    so the next NER retraining pass at /opt/ahg-ai sees the archivist's note.

    The reason is OPTIONAL - empty submissions still reject the mention and
    still record a feedback row (rejection_reason="(no reason supplied)").
    The audit row on ahg_mention_decision is the durable spine and is
    unconditionally written.

    Args:
        $mention : object - mention row
--}}
<div id="auth-res-reject-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4"
     role="dialog" aria-modal="true">
    <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Reject as false positive</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Mark this mention as a NER mistake. The reason is captured on
                    <code>ahg_ner_feedback</code> for the next NER retraining pass.
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" data-auth-res-close="reject">&times;</button>
        </div>

        <form method="POST"
              action="{{ route('auth-res.review.reject', ['mention' => $mention->id]) }}"
              class="mt-4">
            @csrf

            <label class="block text-xs font-medium text-slate-700 mb-1">Reason (optional but encouraged)</label>
            <textarea name="rejection_reason" rows="4"
                      class="w-full rounded-md border-slate-300 text-sm"
                      placeholder="e.g. NER mis-typed; this is a place, not a person. Or: stopword caught by entity extractor."></textarea>

            <p class="mt-2 text-[11px] text-slate-500">
                Used by the NER retrainer to learn this is a false positive. Leave empty if you only want to suppress the mention.
            </p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        data-auth-res-close="reject">Cancel</button>
                <button type="submit"
                        class="rounded-md bg-rose-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-rose-500">
                    Reject mention
                </button>
            </div>
        </form>
    </div>
</div>
