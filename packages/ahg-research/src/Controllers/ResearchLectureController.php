<?php

/**
 * ResearchLectureController - lecture builder UI + CRUD for the research portal (#1105).
 *
 * Curriculum content (feeds #1099), public talk/lecture records, and standalone
 * authored lectures (ordered sections + media + resources). All routes are under
 * the auth'd /research group.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchLectureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearchLectureController extends Controller
{
    public function __construct(private ResearchLectureService $service)
    {
    }

    // ── Lectures ─────────────────────────────────────────────────────────

    public function index()
    {
        return view('research::lectures.index', [
            'curriculum' => $this->service->listLectures('curriculum'),
            'talks'      => $this->service->listLectures('talk'),
            'standalone' => $this->service->listLectures('standalone'),
        ]);
    }

    public function create(Request $request)
    {
        $type = in_array($request->query('type'), ResearchLectureService::TYPES, true)
            ? $request->query('type') : 'standalone';

        return view('research::lectures.builder', ['lecture' => null, 'type' => $type]);
    }

    public function store(Request $request)
    {
        $data = $this->validateLecture($request);
        $data['researcher_id'] = $this->researcherId();
        $id = $this->service->createLecture($data);

        return redirect()->route('research.lecture-builder.show', $id)->with('success', __('Lecture created.'));
    }

    public function show(int $id)
    {
        $lecture = $this->service->getLecture($id);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);

        return view('research::lectures.show', [
            'lecture'   => $lecture,
            'sections'  => $this->service->listSections($id),
            'resources' => $this->service->listResources($id),
        ]);
    }

    public function edit(int $id)
    {
        $lecture = $this->service->getLecture($id);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);

        return view('research::lectures.builder', ['lecture' => $lecture, 'type' => $lecture['type']]);
    }

    public function update(int $id, Request $request)
    {
        $lecture = $this->service->getLecture($id);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);
        $this->service->updateLecture($id, $this->validateLecture($request));

        return redirect()->route('research.lecture-builder.show', $id)->with('success', __('Lecture updated.'));
    }

    public function destroy(int $id)
    {
        $lecture = $this->service->getLecture($id);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);
        $this->service->deleteLecture($id);

        return redirect()->route('research.lecture-builder.index')->with('success', __('Lecture deleted.'));
    }

    public function setStatus(int $id, Request $request)
    {
        $lecture = $this->service->getLecture($id);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);
        $this->service->setStatus($id, (string) $request->input('status', 'draft'));

        return back()->with('success', __('Status updated.'));
    }

    // ── Sections ──────────────────────────────────────────────────────────

    public function storeSection(int $lectureId, Request $request)
    {
        $lecture = $this->service->getLecture($lectureId);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);
        $this->service->createSection($lectureId, $this->validateSection($request));

        return back()->with('success', __('Section added.'));
    }

    public function editSection(int $id)
    {
        $section = $this->service->getSection($id);
        abort_if(! $section, 404);
        $this->assertOwner($this->service->getLecture((int) $section['lecture_id']));

        return view('research::lectures.section-edit', [
            'section' => $section,
            'lecture' => $this->service->getLecture((int) $section['lecture_id']),
        ]);
    }

    public function updateSection(int $id, Request $request)
    {
        $section = $this->service->getSection($id);
        abort_if(! $section, 404);
        $this->assertOwner($this->service->getLecture((int) $section['lecture_id']));
        $this->service->updateSection($id, $this->validateSection($request));

        return redirect()->route('research.lecture-builder.show', (int) $section['lecture_id'])
            ->with('success', __('Section saved.'));
    }

    public function destroySection(int $id)
    {
        $section = $this->service->getSection($id);
        abort_if(! $section, 404);
        $this->assertOwner($this->service->getLecture((int) $section['lecture_id']));
        $this->service->deleteSection($id);

        return back()->with('success', __('Section removed.'));
    }

    // ── Resources ───────────────────────────────────────────────────────────

    public function storeResource(int $lectureId, Request $request)
    {
        $lecture = $this->service->getLecture($lectureId);
        abort_if(! $lecture, 404);
        $this->assertOwner($lecture);
        $this->service->createResource($lectureId, $request->validate([
            'label'         => 'required|string|max:255',
            'url'           => 'nullable|string|max:1000',
            'resource_type' => 'nullable|string|max:20',
            'sort_order'    => 'nullable|integer',
        ]));

        return back()->with('success', __('Resource added.'));
    }

    public function destroyResource(int $id)
    {
        $this->service->deleteResource($id);

        return back()->with('success', __('Resource removed.'));
    }

    // ── validation + helpers ────────────────────────────────────────────────

    private function validateLecture(Request $request): array
    {
        return $request->validate([
            'type'                => 'nullable|in:curriculum,talk,standalone',
            'title'               => 'required|string|max:255',
            'subtitle'            => 'nullable|string|max:255',
            'summary'             => 'nullable|string',
            'speaker_name'        => 'nullable|string|max:255',
            'speaker_affiliation' => 'nullable|string|max:255',
            'scheduled_at'        => 'nullable|date',
            'location'            => 'nullable|string|max:255',
            'duration_minutes'    => 'nullable|integer|min:0',
            'recording_url'       => 'nullable|string|max:1000',
            'slides_url'          => 'nullable|string|max:1000',
            'curriculum_ref'      => 'nullable|string|max:255',
            'status'              => 'nullable|in:draft,scheduled,delivered,published,archived',
        ]);
    }

    private function validateSection(Request $request): array
    {
        return $request->validate([
            'heading'       => 'nullable|string|max:255',
            'body_markdown' => 'nullable|string',
            'media_url'     => 'nullable|string|max:1000',
            'media_type'    => 'nullable|in:image,video,audio,embed',
            'sort_order'    => 'nullable|integer',
        ]);
    }

    private function researcherId(): ?int
    {
        // FIX (#1308): the canonical table is research_researcher; the old
        // 'researcher' table never existed, so ownership was never recorded.
        if (! Auth::check() || ! Schema::hasTable('research_researcher')) {
            return null;
        }
        $r = DB::table('research_researcher')->where('user_id', Auth::id())->first();

        return $r ? (int) $r->id : null;
    }

    /**
     * SECURITY (#1308): a researcher may only act on their own lecture; site
     * admins may act on any. Fails closed (403) for unowned/foreign rows.
     */
    private function assertOwner(?array $row): void
    {
        if (\AhgCore\Services\AclService::isAdministrator(Auth::user())) {
            return;
        }
        $mine = $this->researcherId();
        abort_unless(
            $row !== null && $mine !== null && (int) ($row['researcher_id'] ?? 0) === $mine,
            403,
            'You do not have access to this item.'
        );
    }
}
