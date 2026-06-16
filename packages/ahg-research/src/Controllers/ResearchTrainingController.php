<?php

/**
 * ResearchTrainingController - generic training-curriculum + LMS UI (#1099).
 *
 * Admin/builder: courses, modules (lecture-linked), assessment, enrolment.
 * Learner: work modules, take the assessment, earn a certificate. Institution-
 * neutral — roles/cohort/languages/pass-mark are all data. Auth'd /research group.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Controllers;

use App\Http\Controllers\Controller;
use AhgResearch\Services\ResearchTrainingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResearchTrainingController extends Controller
{
    public function __construct(private ResearchTrainingService $service)
    {
    }

    // ── Courses (builder) ────────────────────────────────────────────────

    public function index()
    {
        return view('research::training.index', ['courses' => $this->service->listCourses()]);
    }

    public function create()
    {
        return view('research::training.builder', ['course' => null]);
    }

    public function store(Request $request)
    {
        $d = $this->validateCourse($request);
        $d['researcher_id'] = $this->researcherId();
        $id = $this->service->createCourse($d);

        return redirect()->route('research.training.show', $id)->with('success', __('Course created.'));
    }

    public function show(int $id)
    {
        $course = $this->service->getCourse($id);
        abort_if(! $course, 404);
        $this->assertOwner($course);

        return view('research::training.show', [
            'course'      => $course,
            'modules'     => $this->service->listModules($id),
            'assessment'  => $this->service->getAssessment($id),
            'questions'   => $this->service->questions($id),
            'enrolments'  => $this->service->listEnrolments($id),
            'lectures'    => $this->service->curriculumLectures(),
        ]);
    }

    public function edit(int $id)
    {
        $course = $this->service->getCourse($id);
        abort_if(! $course, 404);
        $this->assertOwner($course);

        return view('research::training.builder', ['course' => $course]);
    }

    public function update(int $id, Request $request)
    {
        $course = $this->service->getCourse($id);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        $this->service->updateCourse($id, $this->validateCourse($request));

        return redirect()->route('research.training.show', $id)->with('success', __('Course updated.'));
    }

    public function destroy(int $id)
    {
        $course = $this->service->getCourse($id);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        $this->service->deleteCourse($id);

        return redirect()->route('research.training.index')->with('success', __('Course deleted.'));
    }

    public function setStatus(int $id, Request $request)
    {
        $course = $this->service->getCourse($id);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        $this->service->setCourseStatus($id, (string) $request->input('status', 'draft'));

        return back()->with('success', __('Status updated.'));
    }

    // ── Modules ──────────────────────────────────────────────────────────

    public function storeModule(int $courseId, Request $request)
    {
        $course = $this->service->getCourse($courseId);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        $this->service->createModule($courseId, $this->validateModule($request));

        return back()->with('success', __('Module added.'));
    }

    public function editModule(int $id)
    {
        $module = $this->service->getModule($id);
        abort_if(! $module, 404);
        $this->assertOwner($this->service->getCourse((int) $module['course_id']));

        return view('research::training.module-edit', [
            'module'   => $module,
            'course'   => $this->service->getCourse((int) $module['course_id']),
            'lectures' => $this->service->curriculumLectures(),
        ]);
    }

    public function updateModule(int $id, Request $request)
    {
        $module = $this->service->getModule($id);
        abort_if(! $module, 404);
        $this->assertOwner($this->service->getCourse((int) $module['course_id']));
        $this->service->updateModule($id, $this->validateModule($request));

        return redirect()->route('research.training.show', (int) $module['course_id'])->with('success', __('Module saved.'));
    }

    public function destroyModule(int $id)
    {
        $module = $this->service->getModule($id);
        abort_if(! $module, 404);
        $this->assertOwner($this->service->getCourse((int) $module['course_id']));
        $this->service->deleteModule($id);

        return back()->with('success', __('Module removed.'));
    }

    // ── Assessment ────────────────────────────────────────────────────────

    public function editAssessment(int $courseId)
    {
        $course = $this->service->getCourse($courseId);
        abort_if(! $course, 404);
        $this->assertOwner($course);

        return view('research::training.assessment-edit', [
            'course'     => $course,
            'assessment' => $this->service->getAssessment($courseId),
            'questions'  => $this->service->questions($courseId),
        ]);
    }

    public function saveAssessment(int $courseId, Request $request)
    {
        $course = $this->service->getCourse($courseId);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        // Assemble questions from parallel arrays; keep only rows with text.
        $questions = [];
        foreach ((array) $request->input('q', []) as $i => $qtext) {
            $qtext = trim((string) $qtext);
            if ($qtext === '') {
                continue;
            }
            $options = array_values(array_filter(array_map('trim', (array) ($request->input("options.$i", []))), fn ($o) => $o !== ''));
            if (count($options) < 2) {
                continue;
            }
            $answer = (int) $request->input("answer.$i", 0);
            $questions[] = ['q' => $qtext, 'options' => $options, 'answer' => min($answer, count($options) - 1)];
        }
        $this->service->saveAssessment($courseId, [
            'title'     => $request->input('title'),
            'pass_mark' => $request->input('pass_mark'),
            'questions' => $questions,
        ]);

        return redirect()->route('research.training.show', $courseId)->with('success', __('Assessment saved (:n questions).', ['n' => count($questions)]));
    }

    // ── Enrolment ───────────────────────────────────────────────────────────

    public function enrol(int $courseId, Request $request)
    {
        $course = $this->service->getCourse($courseId);
        abort_if(! $course, 404);
        $this->assertOwner($course);
        $this->service->enrol($courseId, $request->validate([
            'learner_name'  => 'required|string|max:255',
            'learner_email' => 'nullable|email|max:255',
            'user_id'       => 'nullable|integer',
        ]));

        return back()->with('success', __('Learner enrolled.'));
    }

    public function destroyEnrolment(int $id)
    {
        $enrol = $this->service->getEnrolment($id);
        abort_if(! $enrol, 404);
        $courseId = (int) $enrol['course_id'];
        $this->assertOwner($this->service->getCourse($courseId));
        $this->service->deleteEnrolment($id);

        return redirect()->route('research.training.show', $courseId)->with('success', __('Enrolment removed.'));
    }

    // ── Learner flow ──────────────────────────────────────────────────────

    public function learn(int $enrolmentId)
    {
        $enrol = $this->service->getEnrolment($enrolmentId);
        abort_if(! $enrol, 404);
        $courseId = (int) $enrol['course_id'];

        return view('research::training.learn', [
            'enrol'     => $enrol,
            'course'    => $this->service->getCourse($courseId),
            'modules'   => $this->service->listModules($courseId),
            'doneIds'   => $this->service->completedModuleIds($enrolmentId),
            'questions' => $this->service->questions($courseId),
            'allDone'   => $this->service->allModulesComplete($enrolmentId, $courseId),
            'certificate' => $this->service->getCertificate($enrolmentId),
        ]);
    }

    public function completeModule(int $enrolmentId, int $moduleId, Request $request)
    {
        abort_if(! $this->service->getEnrolment($enrolmentId), 404);
        $this->service->markModule($enrolmentId, $moduleId, (bool) $request->input('completed', 1));

        return back()->with('success', __('Progress saved.'));
    }

    public function takeAssessment(int $enrolmentId)
    {
        $enrol = $this->service->getEnrolment($enrolmentId);
        abort_if(! $enrol, 404);
        $courseId = (int) $enrol['course_id'];

        return view('research::training.assessment-take', [
            'enrol'     => $enrol,
            'course'    => $this->service->getCourse($courseId),
            'questions' => $this->service->questions($courseId),
            'allDone'   => $this->service->allModulesComplete($enrolmentId, $courseId),
        ]);
    }

    public function submitAssessment(int $enrolmentId, Request $request)
    {
        abort_if(! $this->service->getEnrolment($enrolmentId), 404);
        $answers = array_map('intval', (array) $request->input('answer', []));
        $result = $this->service->submitAssessment($enrolmentId, $answers);

        $msg = __('You scored :s%.', ['s' => $result['score']]);
        if ($result['passed'] && ! empty($result['certificate_no'])) {
            $msg .= ' '.__('Passed — certificate :c issued.', ['c' => $result['certificate_no']]);
        } elseif ($result['passed'] && empty($result['all_modules_done'])) {
            $msg .= ' '.__('Passed the assessment, but complete all modules to be certified.');
        } else {
            $msg .= ' '.__('Pass mark is :p%.', ['p' => $result['pass_mark']]);
        }

        return redirect()->route('research.training.learn', $enrolmentId)->with('success', $msg);
    }

    public function certificate(int $enrolmentId)
    {
        $enrol = $this->service->getEnrolment($enrolmentId);
        abort_if(! $enrol, 404);
        $cert = $this->service->getCertificate($enrolmentId);
        abort_if(! $cert, 404);

        return view('research::training.certificate', [
            'enrol'  => $enrol,
            'course' => $this->service->getCourse((int) $enrol['course_id']),
            'cert'   => $cert,
        ]);
    }

    // ── validation + helpers ────────────────────────────────────────────────

    private function validateCourse(Request $request): array
    {
        return $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'audience'    => 'nullable|string|max:255',
            'language'    => 'nullable|string|max:40',
            'pass_mark'   => 'nullable|integer|min:0|max:100',
            'status'      => 'nullable|in:draft,published,archived',
            'sort_order'  => 'nullable|integer',
        ]);
    }

    private function validateModule(Request $request): array
    {
        return $request->validate([
            'title'         => 'required|string|max:255',
            'lecture_id'    => 'nullable|integer',
            'body_markdown' => 'nullable|string',
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
     * SECURITY (#1308): a researcher may only manage their own course (builder
     * side); site admins may manage any. Fails closed (403). Note: the learner
     * flow (learn/takeAssessment/certificate) is enrolment-scoped, not course-
     * owner-scoped, and is intentionally not guarded here.
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
