<?php

/**
 * FaceRecognitionController - Heratio
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

namespace AhgCore\Controllers;

use AhgCore\Services\FaceRecognitionService;
use AhgCore\Services\MediaProtection;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Face recognition screens (heratio#1764). Everything under /admin/faces is
 * staff-only (route middleware acl:update). The person page /faces/person/{id}
 * is staff-only too unless face_search_public is on; the public then sees
 * confirmed appearances on records DisclosureGate and FileScreeningGate
 * allow, never the unconfirmed "possible matches".
 */
class FaceRecognitionController extends Controller
{
    public function __construct(private FaceRecognitionService $faces) {}

    public function index()
    {
        $enabled = $this->faces->enabled();
        $people = $enabled ? $this->faces->people() : [];
        $unnamed = $enabled ? DB::table('digital_object_faces as f')
            ->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->whereNull('f.person_id')->orderByDesc('f.id')->limit(48)
            ->get(['f.id', 'f.frame_time_seconds', 'd.id as do_id', 'd.object_id'])->all() : [];

        return view('ahg-core::faces.index', compact('enabled', 'people', 'unnamed'));
    }

    public function storePerson(Request $request)
    {
        $this->needEnabled();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'actor' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);
        $id = $this->faces->createPerson($data['name'], $this->actorId($data['actor'] ?? null), $data['notes'] ?? null);

        return redirect()->route('faces.person', $id)->with('notice', __('Person added.'));
    }

    public function updatePerson(Request $request, int $id)
    {
        $this->needEnabled();
        abort_unless(DB::table('face_person')->where('id', $id)->exists(), 404);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'actor' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:5000',
        ]);
        $this->faces->updatePerson($id, $data['name'], $this->actorId($data['actor'] ?? null), $data['notes'] ?? null);

        return redirect()->route('faces.person', $id)->with('notice', __('Person saved.'));
    }

    /** The faces found in one digital object, to name. */
    public function object(int $id)
    {
        $this->needEnabled();
        $do = DB::table('digital_object')->where('id', $id)->first();
        abort_unless($do, 404);
        $faces = DB::table('digital_object_faces as f')->leftJoin('face_person as p', 'p.id', '=', 'f.person_id')
            ->where('f.digital_object_id', $id)->orderBy('f.frame_time_seconds')->orderBy('f.face_index')
            ->get(['f.*', 'p.name as person_name'])->all();
        $people = $this->faces->people();
        $slug = DB::table('slug')->where('object_id', $do->object_id)->value('slug');

        return view('ahg-core::faces.object', compact('do', 'faces', 'people', 'slug'));
    }

    public function name(Request $request, int $id)
    {
        $this->needEnabled();
        $data = $request->validate([
            'person_id' => 'nullable|integer|exists:face_person,id|required_without:new_person',
            'new_person' => 'nullable|string|max:255|required_without:person_id',
        ]);
        $personId = ! empty($data['person_id']) ? (int) $data['person_id'] : $this->faces->createPerson($data['new_person']);
        abort_unless($this->faces->nameFace($id, $personId), 404);

        return back()->with('notice', __('Face named.'));
    }

    public function confirm(Request $request, int $id)
    {
        $this->needEnabled();
        $data = $request->validate([
            'person_id' => 'required|integer|exists:face_person,id',
            'similarity' => 'nullable|numeric|between:-1,1',
        ]);
        abort_unless($this->faces->confirm($id, (int) $data['person_id'], isset($data['similarity']) ? (float) $data['similarity'] : null), 404);

        return back()->with('notice', __('Match confirmed.'));
    }

    public function reject(Request $request, int $id)
    {
        $this->needEnabled();
        $data = $request->validate(['person_id' => 'required|integer|exists:face_person,id']);
        abort_unless(DB::table('digital_object_faces')->where('id', $id)->exists(), 404);
        $this->faces->reject($id, (int) $data['person_id']);

        return back()->with('notice', __('Suggestion rejected.'));
    }

    public function unassign(int $id)
    {
        $this->needEnabled();
        $this->faces->unassign($id);

        return back()->with('notice', __('Face taken off the person.'));
    }

    public function searchPhoto(Request $request)
    {
        $this->needEnabled();
        $request->validate(['photo' => 'required|file|image|mimes:jpeg,jpg,png|max:10240']);
        $result = $this->faces->searchByPhoto($request->file('photo')->getRealPath());
        $hits = $this->faces->describeHits($result['matches']);

        return view('ahg-core::faces.photo-results', ['detected' => $result['detected'], 'hits' => $hits, 'threshold' => $this->faces->threshold()]);
    }

    /** "Find all items featuring this person". */
    public function person(int $id)
    {
        $staff = MediaProtection::isStaff();
        abort_unless($this->faces->enabled() && ($staff || $this->faces->publicSearch()), 404);
        $person = $this->faces->person($id);
        abort_unless($person, 404);
        $items = $this->faces->itemsForPerson($id, ! $staff);
        $possible = $staff ? $this->faces->describeHits($this->faces->suggestions($id)) : [];

        return view('ahg-core::faces.person', [
            'person' => $person, 'items' => $items, 'possible' => $possible, 'staff' => $staff,
            'threshold' => $this->faces->threshold(),
        ]);
    }

    /** A crop of one face (staff): from the image, or from the video frame at its time. */
    public function image(int $id)
    {
        $face = DB::table('digital_object_faces as f')->join('digital_object as d', 'd.id', '=', 'f.digital_object_id')
            ->where('f.id', $id)->first(['f.bounding_box', 'f.frame_time_seconds', 'd.path', 'd.name']);
        abort_unless($face && class_exists(\Imagick::class), 404);
        $base = rtrim((string) config('heratio.uploads_path'), '/');
        $file = $base.'/'.ltrim((string) preg_replace('#^/uploads/#', '', (string) $face->path), '/').$face->name;
        abort_unless(is_file($file), 404);

        $tmp = null;
        try {
            if ($face->frame_time_seconds !== null) {
                $tmp = tempnam(sys_get_temp_dir(), 'face-crop-').'.jpg';
                @shell_exec(sprintf('ffmpeg -v error -y -ss %s -i %s -frames:v 1 -q:v 2 %s 2>/dev/null',
                    escapeshellarg((string) $face->frame_time_seconds), escapeshellarg($file), escapeshellarg($tmp)));
                $file = $tmp;
            }
            $img = new \Imagick($file.'[0]');
            $b = json_decode((string) $face->bounding_box, true) ?: [];
            $pad = (int) round(max((int) ($b['w'] ?? 0), (int) ($b['h'] ?? 0)) * 0.2);
            $x = max(0, (int) ($b['x'] ?? 0) - $pad);
            $y = max(0, (int) ($b['y'] ?? 0) - $pad);
            $w = min($img->getImageWidth() - $x, (int) ($b['w'] ?? 0) + 2 * $pad);
            $h = min($img->getImageHeight() - $y, (int) ($b['h'] ?? 0) + 2 * $pad);
            if ($w > 0 && $h > 0) {
                $img->cropImage($w, $h, $x, $y);
            }
            $img->thumbnailImage(160, 160, true);
            $img->setImageFormat('jpeg');
            $blob = $img->getImageBlob();
        } catch (\Throwable) {
            abort(404);
        } finally {
            if ($tmp) {
                @unlink($tmp);
            }
        }

        return response($blob, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store']);
    }

    private function needEnabled(): void
    {
        if (! $this->faces->enabled()) {
            abort(404, __('Face recognition is switched off.'));
        }
    }

    /** An authority record given by id or slug; null when empty, a validation error when it is not an actor. */
    private function actorId(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $id = ctype_digit($value) ? (int) $value : (int) DB::table('slug')->where('slug', $value)->value('object_id');

        if ($id <= 0 || ! DB::table('actor')->where('id', $id)->exists()) {
            throw ValidationException::withMessages(['actor' => __('No authority record with that id or slug.')]);
        }

        return $id;
    }
}
