{{--
    auth-res::_assign-modal - Bootstrap 5 archivist-picker modal.

    Assign / Workflow feature. Posts the chosen archivist to the single-mention
    assign route. The archivist <select> is rendered server-side from
    $archivists (a list of ['id','name','username'] from AssignmentService).

    Args:
        $mention    : object - the mention row (needs ->id, optionally
                      ->assigned_to_user_id for the "currently assigned" note)
        $archivists : list   - eligible assignees
--}}
<div class="modal fade" id="ar-assign-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST"
                  action="{{ route('auth-res.review.assign', ['mention' => $mention->id]) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-check me-2"></i>{{ __('Assign this mention') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        {{ __('Assigning a mention routes it through the Workflow plugin: a review task is created (or re-targeted) for the chosen archivist, who will see it in their workflow dashboard.') }}
                    </p>

                    @if(!empty($mention->assigned_to_user_id))
                        <div class="alert alert-info small py-2">
                            <i class="bi bi-info-circle me-1"></i>
                            {{ __('Currently assigned to') }}:
                            <strong>{{ $currentAssigneeName ?? ('User #' . (int) $mention->assigned_to_user_id) }}</strong>
                            - {{ __('re-assigning will move the existing task.') }}
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="ar-assign-archivist" class="form-label">
                            {{ __('Archivist') }} <span class="text-danger">*</span>
                        </label>
                        <select name="archivist_user_id"
                                id="ar-assign-archivist"
                                class="form-select"
                                required>
                            <option value="">{{ __('Select an archivist...') }}</option>
                            @foreach($archivists as $a)
                                <option value="{{ (int) $a['id'] }}"
                                    {{ (int) ($mention->assigned_to_user_id ?? 0) === (int) $a['id'] ? 'selected' : '' }}>
                                    {{ $a['name'] }}@if(!empty($a['username'])) ({{ $a['username'] }})@endif
                                </option>
                            @endforeach
                        </select>
                        @if(empty($archivists))
                            <div class="form-text text-danger">
                                {{ __('No eligible archivists found.') }}
                            </div>
                        @endif
                    </div>

                    <div class="mb-2">
                        <label for="ar-assign-reason" class="form-label">
                            {{ __('Reason / message (optional)') }}
                        </label>
                        <textarea name="reason"
                                  id="ar-assign-reason"
                                  class="form-control"
                                  rows="3"
                                  maxlength="2000"
                                  placeholder="{{ __('Add a note for the archivist - why this mention came to them, what to check, etc.') }}"></textarea>
                        <div class="form-text">
                            {{ __('Recorded on the workflow task history so the assignee sees it.') }}
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        {{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-primary" @if(empty($archivists)) disabled @endif>
                        <i class="bi bi-person-check me-1"></i>{{ __('Assign') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
