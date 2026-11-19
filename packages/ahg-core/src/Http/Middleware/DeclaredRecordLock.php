<?php

/**
 * DeclaredRecordLock - heratio#1760: a declared record is read-only.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * On a route that edits or deletes a description or its files: refuse when
 * the record has been declared (record_declaration.status declared or
 * archived). The record is found from the route's {slug}, a digital object
 * {id} (its record, or its master's record), or an attachment {id}.
 * Undeclaring stays with records management.
 */
class DeclaredRecordLock
{
    public function handle(Request $request, Closure $next, string $kind = 'record')
    {
        $ioId = self::recordFor($request, $kind);
        if ($ioId && self::isDeclared($ioId)) {
            $message = __('This is a declared record: its description and files are read-only.');
            if ($request->expectsJson()) {
                return response()->json(['error' => $message], 423);
            }
            $slug = DB::table('slug')->where('object_id', $ioId)->value('slug');

            return ($slug ? redirect('/'.$slug) : back())->with('error', $message);
        }

        return $next($request);
    }

    public static function isDeclared(int $ioId): bool
    {
        return Schema::hasTable('record_declaration')
            && DB::table('record_declaration')->where('information_object_id', $ioId)->whereIn('status', ['declared', 'archived'])->exists();
    }

    private static function recordFor(Request $request, string $kind): ?int
    {
        $route = $request->route();
        if ($slug = $route?->parameter('slug')) {
            return (int) DB::table('slug')->where('slug', $slug)->value('object_id') ?: null;
        }
        $id = (int) ($route?->parameter('id') ?? 0);
        if ($id <= 0) {
            return null;
        }
        if ($kind === 'attachment') {
            return class_exists(\AhgCore\Services\AttachedDigitalObjectService::class)
                ? app(\AhgCore\Services\AttachedDigitalObjectService::class)->ownerIoId($id) : null;
        }
        $do = DB::table('digital_object')->where('id', $id)->first(['object_id', 'parent_id']);
        if (! $do) {
            return null;
        }

        return (int) ($do->object_id ?: DB::table('digital_object')->where('id', $do->parent_id)->value('object_id')) ?: null;
    }
}
