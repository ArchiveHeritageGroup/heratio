{{--
    auth-res::_link-different-modal - Tailwind modal with typeahead lookup.

    Args:
        $mention : object - mention row (for entity_type + POST URL)
--}}
<div id="auth-res-link-different-modal"
     class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/40 px-4"
     role="dialog" aria-modal="true">
    <div class="w-full max-w-xl rounded-lg bg-white shadow-xl p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Link to a different existing authority</h2>
                <p class="text-xs text-slate-500 mt-1">
                    Search the local authority store (actors for PERSON/ORG, places for GPE/PLACE/LOC).
                </p>
            </div>
            <button type="button" class="text-slate-400 hover:text-slate-700" data-auth-res-close="link-different">
                &times;
            </button>
        </div>

        <form method="POST"
              action="{{ route('auth-res.review.linkDifferent', ['mention' => $mention->id]) }}"
              class="mt-4">
            @csrf
            <input type="hidden" name="authority_id" id="auth-res-link-different-authority-id" value="">

            <label class="block text-xs font-medium text-slate-700 mb-1">Search</label>
            <input type="text"
                   id="auth-res-link-different-input"
                   class="w-full rounded-md border-slate-300 text-sm"
                   data-entity-type="{{ $mention->entity_type }}"
                   autocomplete="off"
                   placeholder="Type at least two characters...">

            <div id="auth-res-link-different-results"
                 class="mt-2 max-h-72 overflow-y-auto rounded-md border border-slate-200 bg-white divide-y divide-slate-100 hidden">
            </div>

            <div id="auth-res-link-different-chosen" class="mt-3 text-sm text-slate-700 hidden">
                Selected: <span class="font-semibold" id="auth-res-link-different-chosen-name"></span>
            </div>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        data-auth-res-close="link-different">Cancel</button>
                <button type="submit"
                        id="auth-res-link-different-submit"
                        disabled
                        class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white disabled:bg-slate-300 hover:bg-indigo-500">
                    Link
                </button>
            </div>
        </form>
    </div>
</div>
