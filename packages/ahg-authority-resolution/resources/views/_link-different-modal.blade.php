{{--
    auth-res::_link-different-modal - Bootstrap 5 modal with typeahead lookup.

    Args:
        $mention : object - mention row (for entity_type + POST URL)
--}}
<div class="modal fade" id="ar-link-different-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('auth-res.review.linkDifferent', ['mention' => $mention->id]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-search me-2"></i>{{ __('Link to a different authority') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Search the local authority store for a record other than those listed in the ranked candidates. Selected record will be linked and a link_different decision will be recorded.') }}
                    </p>

                    <div class="mb-3">
                        <label for="ar-link-different-search" class="form-label">{{ __('Search') }}</label>
                        <input type="text"
                               id="ar-link-different-search"
                               class="form-control"
                               placeholder="{{ __('Type at least two characters...') }}"
                               autocomplete="off"
                               data-entity-type="{{ $mention->entity_type }}">
                        <div id="ar-link-different-results"
                             class="list-group mt-2"
                             style="max-height: 320px; overflow-y: auto;"></div>
                    </div>

                    <input type="hidden" name="authority_id" id="ar-link-different-authority-id" value="">
                    <div id="ar-link-different-selected" class="alert alert-success d-none">
                        <strong>{{ __('Selected') }}:</strong>
                        <span id="ar-link-different-selected-name"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit"
                            id="ar-link-different-submit"
                            class="btn btn-warning"
                            disabled>
                        <i class="bi bi-link-45deg me-1"></i>{{ __('Link to selected') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
