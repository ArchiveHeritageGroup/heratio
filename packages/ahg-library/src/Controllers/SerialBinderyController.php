<?php

declare(strict_types=1);

/**
 * SerialBinderyController - Heratio ahg-library (heratio#1281, PSIS parity)
 *
 * Serials bindery dashboard: gather received issues into a batch sent to a commercial
 * bindery, then receive the batch back (marking its issues bound). Thin over
 * LibrarySerialService bindery methods. Ported from the PSIS serial/bindery action.
 *
 * Copyright (C) 2026 Johan Pieterse - Plain Sailing Information Systems - AGPL-3.0
 */

namespace AhgLibrary\Controllers;

use AhgLibrary\Services\LibrarySerialService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SerialBinderyController extends Controller
{
    public function __construct(private LibrarySerialService $serials)
    {
    }

    /** Bindery dashboard: bindable (received, unsent) issues + existing batches. */
    public function index(): \Illuminate\View\View
    {
        return view('ahg-library::serial.bindery', [
            'bindable' => $this->serials->getBindableIssues(),
            'batches' => $this->serials->listBinderyBatches(),
        ]);
    }

    /** Send selected received issues to a bindery as a new batch. */
    public function send(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'issue_ids' => 'required|array|min:1',
            'issue_ids.*' => 'integer|min:1',
            'vendor_id' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        $this->serials->createBinderyBatch(
            $data['issue_ids'],
            $data['vendor_id'] ?? null,
            $data['notes'] ?? null,
            auth()->id()
        );

        return redirect()->route('library.serial-bindery')
            ->with('success', __('Bindery batch created and sent.'));
    }

    /** Receive a bindery batch back; its issues are marked bound. */
    public function receive(Request $request): RedirectResponse
    {
        $data = $request->validate(['batch_id' => 'required|integer|min:1']);
        $this->serials->receiveBinderyBatch((int) $data['batch_id']);

        return redirect()->route('library.serial-bindery')
            ->with('success', __('Bindery batch received; issues marked bound.'));
    }
}
