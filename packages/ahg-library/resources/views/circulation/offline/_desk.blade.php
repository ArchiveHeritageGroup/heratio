@php
    $strings = [
        'online' => __('Online'),
        'offline' => __('Offline - transactions are saved on this computer'),
        'noCache' => __('No desk data on this computer yet. Download it while online.'),
        'cacheInfo' => __('Desk data from :when: :copies copies, :patrons patrons.'),
        'cacheOld' => __('This desk data is more than a day old.'),
        'queued' => __('Saved. Waiting to sync.'),
        'needCopy' => __('Scan a copy barcode.'),
        'needPatron' => __('Scan a patron card for a checkout.'),
        'unknownCopy' => __('This copy is not in the desk data.'),
        'unknownPatron' => __('This patron is not in the desk data.'),
        'copyOut' => __('The desk data shows this copy as not available.'),
        'patronBlocked' => __('The desk data shows this patron cannot borrow.'),
        'atLimit' => __('The desk data shows this patron at the loan limit.'),
        'noLoan' => __('The desk data shows no open loan for this copy.'),
        'confirm' => __('Save this transaction anyway? It will be checked when the desk reconnects.'),
        'due' => __('Indicative due date: :date'),
        'syncing' => __('Syncing...'),
        'synced' => __('Sync finished: :applied applied, :conflicts sent to the conflicts report.'),
        'signIn' => __('Sign in again to sync. Your saved transactions are safe on this computer.'),
        'wrongUser' => __('A different user is signed in. These transactions stay here until their owner syncs.'),
        'syncFailed' => __('Sync stopped. Saved transactions will be tried again.'),
        'refused' => __('The server refused a transaction. It has been kept here.'),
        'needOnline' => __('This needs a connection.'),
        'needEmpty' => __('Sync the saved transactions first.'),
        'downloading' => __('Downloading desk data...'),
        'downloaded' => __('Desk data downloaded.'),
        'cleared' => __('Desk data removed from this computer.'),
        'clearConfirm' => __('Remove the desk data from this computer?'),
        'checkout' => __('Checkout'),
        'return' => __('Return'),
        'lockedPrompt' => __('This desk is locked. Enter your offline PIN.'),
        'noPin' => __('Offline capture needs a PIN, set while online and signed in. None is set for the user who last opened this desk.'),
        'pinNeeded' => __('No offline PIN set. Set one now, or you cannot capture when the link drops.'),
        'pinIsSet' => __('Your offline PIN is set on this computer.'),
        'pinSaved' => __('Offline PIN saved on this computer.'),
        'pinShort' => __('Use at least 6 characters.'),
        'pinWrong' => __('That PIN is not correct.'),
    ];
@endphp
<div id="od-root"
     data-user-id="{{ $userId ?? 0 }}"
     data-branch-id="{{ $branchId ?? '' }}"
     data-base-url="{{ url('/library-manage/circulation/offline') }}"
     data-strings='@json($strings, JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)'>
    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-1"><span id="od-net" class="badge bg-secondary" role="status"></span></p>
            <p id="od-cache" class="small text-secondary mb-2"></p>
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" id="od-refresh" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-download me-1"></i>{{ __('Download desk data') }}
                </button>
                <button type="button" id="od-sync" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-sync me-1"></i>{{ __('Sync now') }}
                </button>
                <button type="button" id="od-clear" class="btn btn-sm atom-btn-white">
                    <i class="fas fa-trash me-1"></i>{{ __('Remove desk data') }}
                </button>
            </div>
        </div>
    </div>

    <div id="od-locked" class="card mb-3" hidden>
        <div class="card-body">
            <p id="od-locked-text" class="mb-2"></p>
            <form id="od-unlock" class="d-flex gap-2 flex-wrap align-items-end" autocomplete="off">
                <div>
                    <label for="od-pin" class="form-label">{{ __('Offline PIN') }}</label>
                    <input type="password" id="od-pin" class="form-control form-control-sm" maxlength="64" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-sm atom-btn-white">{{ __('Unlock') }}</button>
            </form>
        </div>
    </div>

    <div id="od-pin-setup" class="card mb-3" hidden>
        <div class="card-body">
            <p id="od-pin-state" class="small mb-2"></p>
            <div class="d-flex gap-2 flex-wrap align-items-end">
                <div>
                    <label for="od-pin-new" class="form-label">{{ __('Set offline PIN') }}</label>
                    <input type="password" id="od-pin-new" class="form-control form-control-sm" minlength="6" maxlength="64" autocomplete="new-password">
                </div>
                <button type="button" id="od-pin-save" class="btn btn-sm atom-btn-white">{{ __('Save PIN') }}</button>
                <button type="button" id="od-lock" class="btn btn-sm atom-btn-white" hidden>
                    <i class="fas fa-lock me-1"></i>{{ __('Lock desk') }}
                </button>
            </div>
            <p class="small text-secondary mt-2 mb-0">{{ __('On a shared computer the PIN stops the next person using this desk by accident. It does not encrypt the desk data held in this browser, so use a computer that staff alone can reach.') }}</p>
        </div>
    </div>

    <form id="od-form" class="card mb-3" autocomplete="off" hidden>
        <div class="card-body">
            <fieldset class="mb-3">
                <legend class="form-label fs-6">{{ __('Transaction') }}</legend>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="od-action" id="od-action-checkout" value="checkout" checked>
                    <label class="form-check-label" for="od-action-checkout">{{ __('Checkout') }}</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="od-action" id="od-action-return" value="return">
                    <label class="form-check-label" for="od-action-return">{{ __('Return') }}</label>
                </div>
            </fieldset>
            <div class="row g-2">
                <div class="col-md-5" id="od-patron-wrap">
                    <label for="od-patron" class="form-label">{{ __('Patron card') }}</label>
                    <input type="text" id="od-patron" class="form-control" maxlength="50">
                </div>
                <div class="col-md-5">
                    <label for="od-copy" class="form-label">{{ __('Copy barcode') }}</label>
                    <input type="text" id="od-copy" class="form-control" maxlength="50">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn atom-btn-white w-100">{{ __('Save') }}</button>
                </div>
            </div>
            <div id="od-message" class="small mt-2" role="status" aria-live="polite"></div>
        </div>
    </form>

    <div class="card">
        <div class="card-header">{{ __('Waiting to sync') }} (<span id="od-count">0</span>)</div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('Time') }}</th>
                        <th scope="col">{{ __('Transaction') }}</th>
                        <th scope="col">{{ __('Copy') }}</th>
                        <th scope="col">{{ __('Patron') }}</th>
                    </tr>
                </thead>
                <tbody id="od-queue"></tbody>
            </table>
        </div>
    </div>
</div>
