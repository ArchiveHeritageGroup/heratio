<?php

/**
 * DigitalObjectController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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



namespace AhgInformationObjectManage\Controllers;

use AhgCore\Services\AhgSettingsService;
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

            // Auto-scan for condition if enabled.
            // Deferred: dispatch happens once the AI condition service ships. Scope-locked
            // to HTR work for now, so the hook stays observable but inert.
            if (AhgSettingsService::getBool('ai_condition_auto_scan', false)) {
                \Log::info('ai_condition_auto_scan: would dispatch condition scan for IO ' . $io->id);
            }

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
    /**
     * Edit digital object page.
     * Migrated from AtoM digitalobject/editSuccess.php.
     */
    public function show(int $id)
    {
        $culture = app()->getLocale();

        $doRow = DB::table('digital_object')->where('id', $id)->first();
        if (!$doRow) {
            abort(404);
        }

        // Get parent IO
        $ioTitle = '';
        $ioSlug = '';
        if ($doRow->object_id) {
            $ioI18n = DB::table('information_object_i18n')
                ->where('id', $doRow->object_id)->where('culture', $culture)->first();
            $ioTitle = $ioI18n->title ?? '';
            $ioSlug = DB::table('slug')->where('object_id', $doRow->object_id)->value('slug') ?? '';
        }

        // Master file URL
        $masterUrl = DigitalObjectService::getUrl($doRow);

        // Reference image (usage_id = 141)
        $referenceImage = DB::table('digital_object')
            ->where('parent_id', $doRow->id)->where('usage_id', 141)->first();
        $refUrl = $referenceImage ? (rtrim($referenceImage->path, '/') . '/' . $referenceImage->name) : null;

        // Thumbnail image (usage_id = 142)
        $thumbnailImage = DB::table('digital_object')
            ->where('parent_id', $doRow->id)->where('usage_id', 142)->first();
        $thumbUrl = $thumbnailImage ? (rtrim($thumbnailImage->path, '/') . '/' . $thumbnailImage->name) : null;

        // Media types for dropdown
        $mediaTypes = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 46)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->pluck('term_i18n.name', 'term.id')
            ->toArray();

        // Determine media icon
        $mediaIcon = 'fa-file';
        if ($doRow->media_type_id == 138) $mediaIcon = 'fa-film';
        elseif ($doRow->media_type_id == 135) $mediaIcon = 'fa-music';
        elseif ($doRow->media_type_id == 136) $mediaIcon = 'fa-image';

        // Check if compound object toggle should show
        $hasChildren = DB::table('information_object')
            ->where('parent_id', $doRow->object_id)->exists();

        return view('ahg-io-manage::digitalobject.edit-page', [
            'do' => $doRow,
            'ioTitle' => $ioTitle,
            'ioSlug' => $ioSlug,
            'masterUrl' => $masterUrl,
            'referenceImage' => $referenceImage,
            'refUrl' => $refUrl,
            'thumbnailImage' => $thumbnailImage,
            'thumbUrl' => $thumbUrl,
            'mediaTypes' => $mediaTypes,
            'mediaIcon' => $mediaIcon,
            'hasChildren' => $hasChildren,
        ]);
    }

    /**
     * Update digital object (media type, replace file, upload derivatives).
     */
    public function update(Request $request, int $id)
    {
        $doRow = DB::table('digital_object')->where('id', $id)->first();
        if (!$doRow) {
            abort(404);
        }

        $updates = [];

        // Update media type
        if ($request->filled('media_type_id')) {
            $updates['media_type_id'] = (int) $request->input('media_type_id');
        }

        if (!empty($updates)) {
            DB::table('digital_object')->where('id', $id)->update($updates);
        }

        // Replace master file
        if ($request->hasFile('replace_file')) {
            $file = $request->file('replace_file');
            $dir = rtrim($doRow->path, '/');
            if ($dir && is_dir($dir)) {
                $file->move($dir, $doRow->name);
                DB::table('digital_object')->where('id', $id)->update([
                    'byte_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                ]);
            }
        }

        // Upload reference image
        if ($request->hasFile('repFile_reference') && !DB::table('digital_object')->where('parent_id', $id)->where('usage_id', 141)->exists()) {
            $file = $request->file('repFile_reference');
            $refDir = rtrim($doRow->path, '/') . '/reference/';
            if (!is_dir($refDir)) {
                @mkdir($refDir, 0755, true);
            }
            $file->move($refDir, $file->getClientOriginalName());

            $refObjectId = DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('digital_object')->insert([
                'id' => $refObjectId,
                'object_id' => $doRow->object_id,
                'parent_id' => $id,
                'usage_id' => 141,
                'name' => $file->getClientOriginalName(),
                'path' => $refDir,
                'byte_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        }

        // Upload thumbnail image
        if ($request->hasFile('repFile_thumbnail') && !DB::table('digital_object')->where('parent_id', $id)->where('usage_id', 142)->exists()) {
            $file = $request->file('repFile_thumbnail');
            $thumbDir = rtrim($doRow->path, '/') . '/thumbnail/';
            if (!is_dir($thumbDir)) {
                @mkdir($thumbDir, 0755, true);
            }
            $file->move($thumbDir, $file->getClientOriginalName());

            $thumbObjectId = DB::table('object')->insertGetId(['class_name' => 'QubitDigitalObject', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('digital_object')->insert([
                'id' => $thumbObjectId,
                'object_id' => $doRow->object_id,
                'parent_id' => $id,
                'usage_id' => 142,
                'name' => $file->getClientOriginalName(),
                'path' => $thumbDir,
                'byte_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);
        }

        $ioSlug = DB::table('slug')->where('object_id', $doRow->object_id)->value('slug');

        if ($ioSlug) {
            return redirect()->route('informationobject.show', $ioSlug)->with('success', 'Digital object updated.');
        }
        return redirect()->route('io.digitalobject.show', $id)->with('success', 'Digital object updated.');
    }

    /**
     * Multi-file upload page — drag & drop batch uploader.
     * Migrated from AtoM informationobject/multiFileUpload.
     */
    public function multiFileUpload(Request $request, string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $culture = app()->getLocale();

        // Max upload sizes
        $maxFileSize = min(
            $this->phpSizeToBytes(ini_get('upload_max_filesize')),
            $this->phpSizeToBytes(ini_get('post_max_size'))
        );
        $maxPostSize = $this->phpSizeToBytes(ini_get('post_max_size'));

        // Upload response path (AJAX endpoint for Uppy)
        $uploadResponsePath = route('io.digitalobject.upload', $slug) . '?informationObjectId=' . $io->id;

        // Levels of description
        $levels = DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->where('term.taxonomy_id', 34)
            ->where('term_i18n.culture', $culture)
            ->orderBy('term_i18n.name')
            ->select('term.id', 'term_i18n.name')
            ->get();

        // Handle POST — process uploaded files and create child IOs
        if ($request->isMethod('post')) {
            $title = $request->input('title', 'image %dd%');
            $levelId = $request->input('levelOfDescription');
            $files = $request->input('files', []);

            $slugList = [];
            $i = 0;

            foreach ($files as $file) {
                if (empty($file['tmpName'])) continue;
                $i++;

                // Generate title from pattern
                $childTitle = str_replace('%dd%', str_pad($i, 2, '0', STR_PAD_LEFT), $title);
                if (!empty($file['infoObjectTitle'])) {
                    $childTitle = $file['infoObjectTitle'];
                }

                // Create child information object
                $objectId = DB::table('object')->insertGetId([
                    'class_name' => 'QubitInformationObject',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('information_object')->insert([
                    'id' => $objectId,
                    'parent_id' => $io->id,
                    'level_of_description_id' => $levelId ?: null,
                    'source_standard' => $io->source_standard ?? null,
                ]);

                DB::table('information_object_i18n')->insert([
                    'id' => $objectId,
                    'culture' => $culture,
                    'title' => $childTitle,
                ]);

                // Create slug
                $childSlug = \Illuminate\Support\Str::slug($childTitle) . '-' . $objectId;
                DB::table('slug')->insert([
                    'object_id' => $objectId,
                    'slug' => $childSlug,
                ]);

                // Set publication status
                DB::table('status')->insert([
                    'object_id' => $objectId,
                    'type_id' => 158,
                    'status_id' => 159, // draft
                ]);

                // Move temp file to permanent location and create digital object
                $tmpPath = storage_path('app/uploads/tmp/' . $file['tmpName']);
                if (file_exists($tmpPath)) {
                    $uploadsPath = config('heratio.uploads_path', storage_path('app/uploads'));
                    $doDir = rtrim($uploadsPath, '/') . '/r/' . $objectId . '/';
                    if (!is_dir($doDir)) {
                        @mkdir($doDir, 0755, true);
                    }
                    $filename = $file['name'] ?? basename($tmpPath);
                    rename($tmpPath, $doDir . $filename);

                    $doObjectId = DB::table('object')->insertGetId([
                        'class_name' => 'QubitDigitalObject',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('digital_object')->insert([
                        'id' => $doObjectId,
                        'object_id' => $objectId,
                        'usage_id' => 140, // master
                        'name' => $filename,
                        'path' => $doDir,
                        'byte_size' => filesize($doDir . $filename),
                        'mime_type' => mime_content_type($doDir . $filename),
                    ]);
                }

                $slugList[] = $childSlug;
            }

            if (!empty($slugList)) {
                return redirect()->route('informationobject.multiFileUpdate', [
                    'slug' => $slug,
                    'items' => implode(',', $slugList),
                ]);
            }

            return redirect()->route('informationobject.show', $slug)->with('success', 'Digital objects imported.');
        }

        return view('ahg-io-manage::digitalobject.multi-file-upload', [
            'io' => $io,
            'maxFileSize' => $maxFileSize,
            'maxPostSize' => $maxPostSize,
            'uploadResponsePath' => $uploadResponsePath,
            'levels' => $levels,
        ]);
    }

    /**
     * Convert PHP size string (e.g. 128M) to bytes.
     */
    protected function phpSizeToBytes(string $size): int
    {
        $unit = strtolower(substr(trim($size), -1));
        $value = (int) $size;
        return match ($unit) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
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
