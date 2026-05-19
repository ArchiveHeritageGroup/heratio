{{--
    auth-res::_park-modal - Bootstrap 5 reason-textarea modal, parks the mention for later.

    Args:
        $mention : object - mention row
--}}
<div class="modal fade" id="ar-park-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('auth-res.review.park', ['mention' => $mention->id]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pause-circle me-2"></i>{{ __('Park this mention') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Parking removes the mention from the active queue but keeps it open for revisit when new authority candidates appear. State changes to "parked".') }}
                    </p>
                    <div class="mb-3">
                        <label for="ar-park-reason" class="form-label">
                            {{ __('Reason') }} <span class="text-danger">*</span>
                        </label>
                        <textarea name="reason"
                                  id="ar-park-reason"
                                  class="form-control"
                                  rows="4"
                                  placeholder="{{ __('e.g. Awaiting external authority lookup; insufficient distinguishing context.') }}"
                                  required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-info">
                        <i class="bi bi-pause-fill me-1"></i>{{ __('Park') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
