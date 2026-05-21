{{--
    auth-res::_context-modal - Bootstrap 5 "View full context" modal.

    "View full context" feature of the AHG Authority Resolution Engine. The
    review screen's left region carries a button that opens this modal; on
    open, review.blade.php's JS fetches the auth-res.review.context endpoint,
    builds the highlighted source text, and drops it into #ar-context-body.

    The mention occurrence (character offsets) is wrapped in a <mark>; the
    enclosing paragraph (paragraph offsets) gets a subtle background. When the
    offsets are NULL the full text is shown with an "exact position not
    recorded" note instead.

    Args:
        $mention : object - the mention row (needs ->id, ->entity_value)
--}}
<div class="modal fade" id="ar-context-modal" tabindex="-1" aria-hidden="true"
     aria-labelledby="ar-context-modal-title">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ar-context-modal-title">
                    <i class="bi bi-file-text me-2"></i>{{ __('Full source context') }}
                    <span class="text-muted">- {{ $mention->entity_value }}</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    {{ __('The complete source text of the information object this mention was extracted from. The mention occurrence is highlighted; its enclosing paragraph is shaded.') }}
                </p>

                {{-- Loading / error / position-note slots, toggled by JS. --}}
                <div id="ar-context-loading" class="text-center text-muted py-4">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    {{ __('Loading source text...') }}
                </div>
                <div id="ar-context-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="ar-context-nooffset" class="alert alert-info small d-none py-2" role="alert">
                    <i class="bi bi-info-circle me-1"></i>
                    {{ __('Exact position not recorded for this mention - showing the full source text without a highlight.') }}
                </div>

                {{-- Scrollable rendered source text. JS fills #ar-context-body. --}}
                <div id="ar-context-text"
                     class="border rounded bg-light p-3 d-none"
                     style="max-height: 60vh; overflow: auto; white-space: pre-wrap;
                            word-break: break-word; font-family: var(--bs-font-monospace, monospace);
                            font-size: .85rem; line-height: 1.6;">
                    <span id="ar-context-body"></span>
                </div>

                <div id="ar-context-empty" class="alert alert-warning small d-none py-2" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    {{ __('No source text is available for this information object.') }}
                </div>
            </div>
            <div class="modal-footer">
                <span class="text-muted small me-auto" id="ar-context-meta"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    {{ __('Close') }}
                </button>
            </div>
        </div>
    </div>
</div>
