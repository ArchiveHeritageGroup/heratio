<?php

/**
 * TrashController - heratio#1734: deleted digital objects waiting in the trash.
 *
 * Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\DigitalObjectTrash;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use AhgCore\Support\I18nFallback;

class TrashController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->get('status'), ['trashed', 'restored', 'purged'], true) ? $request->get('status') : 'trashed';
        $items = Schema::hasTable(DigitalObjectTrash::TABLE)
            ? DB::table(DigitalObjectTrash::TABLE.' as t')
                ->leftJoin('user as u', 'u.id', '=', 't.deleted_by')
                ->leftJoin('slug as s', 's.object_id', '=', 't.information_object_id')
                ->tap(fn ($q) => I18nFallback::join($q, 'information_object_i18n as i', 't.information_object_id', app()->getLocale()))
                ->where('t.status', $status)->orderByDesc('t.deleted_at')
                ->select('t.*', 'u.username as deleted_by_name', 's.slug', I18nFallback::col('i', 'title', 'title'))
                ->paginate(50)->withQueryString()
            : collect();

        return view('ahg-core::trash.index', [
            'items' => $items, 'status' => $status,
            'enabled' => DigitalObjectTrash::enabled(), 'days' => DigitalObjectTrash::retentionDays(),
        ]);
    }

    public function restore(int $id)
    {
        $ok = DigitalObjectTrash::restore($id);

        return back()->with($ok ? 'success' : 'error', $ok ? __('Restored.') : __('Could not restore: the file is no longer in the trash, or one already exists where it was.'));
    }

    public function purge(int $id)
    {
        $infoId = DB::table(DigitalObjectTrash::TABLE)->where('id', $id)->value('information_object_id');
        if ($infoId && ($hold = \AhgCore\Services\LegalHoldGate::holdOn((int) $infoId))) {
            return back()->with('error', (new \AhgCore\Services\RecordUnderLegalHoldException($hold, (int) $infoId))->getMessage());
        }
        $ok = DigitalObjectTrash::purge($id);

        return back()->with($ok ? 'success' : 'error', $ok ? __('Deleted for good.') : __('Nothing to delete.'));
    }
}
