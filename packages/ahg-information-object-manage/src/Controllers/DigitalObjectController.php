<?php

/**
 * DigitalObjectController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */


use AhgCore\Services\DigitalObjectService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DigitalObjectController extends Controller
{
    /**
     * Handle file upload for an information object.
     *
     * @param Request $request
     * @param string  $slug    IO slug
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function upload(Request $request, string $slug)
    {
        $request->validate([
            'digital_object' => 'required|file|max:102400', // 100 MB
        ]);

        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        // Check if a digital object already exists for this IO
        $existing = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->where('usage_id', DigitalObjectService::USAGE_MASTER)
            ->first();

        if ($existing) {
            return redirect()->route('informationobject.edit', $slug)
                ->with('error', 'A digital object already exists. Delete the current one before uploading a new file.');
        }

        try {
            $masterId = DigitalObjectService::upload($io->id, $request->file('digital_object'));

            return redirect()->route('informationobject.edit', $slug)
                ->with('success', 'Digital object uploaded successfully.');
        } catch (\Exception $e) {
            return redirect()->route('informationobject.edit', $slug)
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a digital object and its derivatives.
     *
     * @param Request $request
     * @param int     $id      Digital object ID
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function delete(Request $request, int $id)
    {
        // Find the IO that owns this digital object so we can redirect back
        $doRow = DB::table('digital_object')->where('id', $id)->first();
        if (!$doRow) {
            abort(404);
        }

        // Get the IO slug for redirect
        $objectId = $doRow->object_id;
        if ($doRow->parent_id) {
            // This is a derivative — get the master's object_id
            $master = DB::table('digital_object')->where('id', $doRow->parent_id)->first();
            $objectId = $master ? $master->object_id : $objectId;
            // Delete the master (which cascades to derivatives)
            $id = $doRow->parent_id;
        }

        $slug = DB::table('slug')->where('object_id', $objectId)->value('slug');
        if (!$slug) {
            abort(404);
        }

        $success = DigitalObjectService::delete($id);

        if ($success) {
            return redirect()->route('informationobject.edit', $slug)
                ->with('success', 'Digital object deleted successfully.');
        }

        return redirect()->route('informationobject.edit', $slug)
            ->with('error', 'Failed to delete digital object.');
    }

    /**
     * Display digital object metadata page.
     *
     * @param int $id Digital object ID
     *
     * @return \Illuminate\View\View
     */
    public function show(int $id)
    {
        $doRow = DB::table('digital_object')->where('id', $id)->first();
        if (!$doRow) {
            abort(404);
        }

        $metadata = DigitalObjectService::getMetadata($id);
        $mediaTypeName = DigitalObjectService::getMediaTypeName($doRow->media_type_id);
        $usageName = DigitalObjectService::getUsageName($doRow->usage_id);
        $fileSize = DigitalObjectService::formatFileSize($doRow->byte_size);
        $url = DigitalObjectService::getUrl($doRow);

        // Get IO title for breadcrumb
        $ioTitle = '';
        $ioSlug = '';
        if ($doRow->object_id) {
            $ioI18n = DB::table('information_object_i18n')
                ->where('id', $doRow->object_id)
                ->where('culture', 'en')
                ->first();
            $ioTitle = $ioI18n->title ?? '';
            $ioSlug = DB::table('slug')->where('object_id', $doRow->object_id)->value('slug') ?? '';
        }

        return view('ahg-io-manage::digitalobject.show', [
            'digitalObject' => $doRow,
            'metadata' => $metadata,
            'mediaTypeName' => $mediaTypeName,
            'usageName' => $usageName,
            'fileSize' => $fileSize,
            'url' => $url,
            'ioTitle' => $ioTitle,
            'ioSlug' => $ioSlug,
        ]);
    }

    /**
     * Get information object by slug.
     *
     * @param string $slug
     *
     * @return object|null
     */
    protected function getIO(string $slug): ?object
    {
        $slugRow = DB::table('slug')->where('slug', $slug)->first();
        if (!$slugRow) {
            return null;
        }

        $io = DB::table('information_object')
            ->where('id', $slugRow->object_id)
            ->first();

        if (!$io) {
            return null;
        }

        $i18n = DB::table('information_object_i18n')
            ->where('id', $io->id)
            ->where('culture', 'en')
            ->first();

        $io->title = $i18n->title ?? null;
        $io->slug = $slug;

        return $io;
    }
}
