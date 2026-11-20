<?php

/**
 * MediaCheckoutController - heratio#1757 / #1758: check a master file out for
 * editing in an outside tool and check the edited file back in.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\MediaCheckedOutException;
use AhgCore\Services\MediaCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MediaCheckoutController extends Controller
{
    /** Administrators: every file checked out now. */
    public function index()
    {
        return view('ahg-core::media-checkout.index', ['items' => MediaCheckoutService::allActive()]);
    }

    public function checkout(Request $request, int $id)
    {
        $request->validate(['checkout_note' => 'nullable|string|max:1000']);
        $master = $this->master($id);
        try {
            MediaCheckoutService::checkout($master, $request->input('checkout_note'));
        } catch (\RuntimeException $e) {
            return $this->back($id, 'error', $e->getMessage());
        }

        return $this->back($id, 'success', __('Checked out to you. Download the master and its sidecar below; check the edited file in when you are done.'));
    }

    /** The master, or its sidecar (format=xmp|json), for the holder of the check-out or an administrator. */
    public function download(Request $request, int $id)
    {
        $master = $this->master($id);
        $lock = MediaCheckoutService::active($id);
        abort_unless($lock && ((int) $lock->user_id === (int) auth()->id() || AclService::isAdministrator(auth()->user())), 403);

        $base = pathinfo((string) $master->name, PATHINFO_FILENAME);
        $format = (string) $request->query('format', 'master');
        if ($format === 'xmp') {
            return response(MediaCheckoutService::sidecarXmp($master, $lock), 200, [
                'Content-Type' => 'application/rdf+xml',
                'Content-Disposition' => 'attachment; filename="'.addslashes($base).'.xmp"',
            ]);
        }
        if ($format === 'json') {
            return response()->json(MediaCheckoutService::sidecar($master, $lock), 200, [
                'Content-Type' => 'application/ld+json',
                'Content-Disposition' => 'attachment; filename="'.addslashes($base).'.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        $path = DigitalObjectService::resolveDiskPath($master);
        abort_unless($path && is_file($path), 404);

        return response()->download($path, (string) $master->name);
    }

    public function checkin(Request $request, int $id)
    {
        $request->validate([
            'checkin_file' => 'required|file',
            'checkin_note' => 'nullable|string|max:1000',
            'version_type' => 'nullable|in:minor,major',
        ]);
        $file = $request->file('checkin_file');
        if (($ext = DigitalObjectService::deniedUploadExtension($file)) !== null) {
            return $this->back($id, 'error', DigitalObjectService::deniedUploadMessage($ext));
        }
        $master = $this->master($id);
        try {
            $v = MediaCheckoutService::checkin($master, $file->getRealPath(), $request->input('checkin_note'),
                $request->input('version_type') === 'major', $file->getClientOriginalName());
        } catch (MediaCheckedOutException $e) {
            return $this->back($id, 'error', __('Only :user, who checked this file out, or an administrator can check it in.', ['user' => $e->checkout->username ?? '#'.$e->checkout->user_id]), 403);
        } catch (\RuntimeException $e) {
            return $this->back($id, 'error', $e->getMessage());
        }

        return $this->back($id, 'success', __('Checked in as version :v.', ['v' => $v->version_major.'.'.$v->version_minor]));
    }

    public function cancel(Request $request, int $id)
    {
        $request->validate(['cancel_note' => 'nullable|string|max:1000']);
        $master = $this->master($id);
        try {
            MediaCheckoutService::cancel($master, $request->input('cancel_note'));
        } catch (MediaCheckedOutException $e) {
            return $this->back($id, 'error', __('Only :user, who checked this file out, or an administrator can cancel the check-out.', ['user' => $e->checkout->username ?? '#'.$e->checkout->user_id]), 403);
        } catch (\RuntimeException $e) {
            return $this->back($id, 'error', $e->getMessage());
        }

        return $this->back($id, 'success', __('Check-out cancelled.'));
    }

    private function master(int $id): object
    {
        $master = DB::table('digital_object')->where('id', $id)->first();
        // Check-out is for the master only, not a reference or thumbnail copy.
        abort_unless($master && empty($master->parent_id), 404);

        return $master;
    }

    private function back(int $id, string $kind, string $message, int $status = 302)
    {
        if ($status !== 302 && request()->expectsJson()) {
            return response()->json(['error' => $message], $status);
        }
        $to = request()->input('return') === 'admin' && AclService::isAdministrator(auth()->user())
            ? route('admin.media-checkout') : route('io.digitalobject.show', $id);

        return redirect($to)->with($kind, $message);
    }
}
