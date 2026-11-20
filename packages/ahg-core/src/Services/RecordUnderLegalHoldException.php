<?php

/**
 * RecordUnderLegalHoldException - Heratio ahg-core
 *
 * heratio#1740 follow-up: thrown when a delete is refused because the record,
 * a file or series containing it, or a record inside it is under legal hold.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Services;

class RecordUnderLegalHoldException extends \RuntimeException
{
    public function __construct(public readonly object $hold, public readonly int $ioId)
    {
        $heldId = (int) ($hold->information_object_id ?? 0);
        parent::__construct($heldId === $ioId
            ? __('This record is under an active legal hold (hold #:hold), so it cannot be deleted.', ['hold' => $hold->id ?? '?'])
            : __('This cannot be deleted: record #:record, which it belongs to or which is inside it, is under an active legal hold (hold #:hold).', ['record' => $heldId, 'hold' => $hold->id ?? '?']));
    }

    /** An expected refusal, not a fault: keep it out of the error log. */
    public function report(): void
    {
    }

    /** A clear message wherever the refusal surfaces uncaught. */
    public function render($request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'Locked', 'message' => $this->getMessage(), 'legal_hold_id' => $this->hold->id ?? null], 423);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
