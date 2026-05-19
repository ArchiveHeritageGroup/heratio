{{--
    auth-res::_reject-modal - Bootstrap 5 reject-with-reason modal.

    Captures rejection_reason for NER retraining (Task 9). Reason is OPTIONAL
    on the backend so empty submissions still reject the mention; the audit
    row on ahg_mention_decision is unconditionally written.

    Args:
        $mention : object - mention row
--}}
<div class="modal fade" id="ar-reject-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('auth-res.review.reject', ['mention' => $mention->id]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle me-2"></i>{{ __('Reject this mention') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Rejecting marks the NER entity as not corresponding to any authority. The reason is captured as a training signal for the NER model.') }}
                    </p>
                    <div class="mb-3">
                        <label for="ar-reject-reason" class="form-label">
                            {{ __('Rejection reason') }}
                            <span class="text-muted small">({{ __('feeds NER retraining') }})</span>
                        </label>
                        <textarea name="rejection_reason"
                                  id="ar-reject-reason"
                                  class="form-control"
                                  rows="4"
                                  placeholder="{{ __('e.g. NER mis-typed; this is a date, not a place.') }}"></textarea>
                        <div class="form-text">
                            {{ __('Examples: "not an entity", "wrong type (this is a date, not a place)", "OCR artefact", "synonym for an existing record but cannot identify which".') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-lg me-1"></i>{{ __('Reject') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
