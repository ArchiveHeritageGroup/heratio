<?php

/**
 * ResearchTrainingService - generic training-curriculum + LMS module (#1099).
 *
 * Institution-neutral: a course defines roles/audience, language and pass mark;
 * its modules sequence content (each may reuse a #1105 curriculum lecture);
 * learners enrol, work through modules (progress tracked), take an assessment,
 * and on passing are issued a certificate. Nothing about any customer is
 * hard-coded — cohort, languages, pass mark and roles are all data.
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class ResearchTrainingService
{
    public const STATUSES = ['draft', 'published', 'archived'];

    // ── Courses ──────────────────────────────────────────────────────────

    public function listCourses(?string $status = null): array
    {
        $q = DB::table('training_course')->orderBy('sort_order')->orderBy('title');
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get()->map(fn ($c) => (array) $c)->all();
    }

    public function getCourse(int $id): ?array
    {
        $row = DB::table('training_course')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createCourse(array $d): int
    {
        return (int) DB::table('training_course')->insertGetId($this->coursePayload($d, true));
    }

    public function updateCourse(int $id, array $d): bool
    {
        return DB::table('training_course')->where('id', $id)->update($this->coursePayload($d, false)) >= 0;
    }

    public function deleteCourse(int $id): void
    {
        $enrolIds = DB::table('training_enrolment')->where('course_id', $id)->pluck('id');
        DB::table('training_progress')->whereIn('enrolment_id', $enrolIds)->delete();
        DB::table('training_certificate')->whereIn('enrolment_id', $enrolIds)->delete();
        DB::table('training_enrolment')->where('course_id', $id)->delete();
        DB::table('training_assessment')->where('course_id', $id)->delete();
        DB::table('training_module')->where('course_id', $id)->delete();
        DB::table('training_course')->where('id', $id)->delete();
    }

    public function setCourseStatus(int $id, string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        return DB::table('training_course')->where('id', $id)->update(['status' => $status, 'updated_at' => Carbon::now()]) > 0;
    }

    private function coursePayload(array $d, bool $isNew): array
    {
        $p = [
            'title'       => trim((string) ($d['title'] ?? 'Untitled course')),
            'description' => $d['description'] ?? null,
            'audience'    => $d['audience'] ?? null,
            'language'    => $d['language'] ?? null,
            'pass_mark'   => isset($d['pass_mark']) && $d['pass_mark'] !== '' ? (int) $d['pass_mark'] : 80,
            'sort_order'  => (int) ($d['sort_order'] ?? 0),
            'updated_at'  => Carbon::now(),
        ];
        if ($isNew) {
            $p['researcher_id'] = $d['researcher_id'] ?? null;
            $p['status']        = $d['status'] ?? 'draft';
            $p['created_at']    = Carbon::now();
        } elseif (isset($d['status'])) {
            $p['status'] = $d['status'];
        }

        return $p;
    }

    // ── Modules ──────────────────────────────────────────────────────────

    public function listModules(int $courseId): array
    {
        return DB::table('training_module')->where('course_id', $courseId)
            ->orderBy('sort_order')->orderBy('id')->get()->map(fn ($m) => (array) $m)->all();
    }

    public function getModule(int $id): ?array
    {
        $row = DB::table('training_module')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createModule(int $courseId, array $d): int
    {
        $md = (string) ($d['body_markdown'] ?? '');

        return (int) DB::table('training_module')->insertGetId([
            'course_id'     => $courseId,
            'title'         => trim((string) ($d['title'] ?? 'Untitled module')),
            'lecture_id'    => $d['lecture_id'] ?: null,
            'body_markdown' => $md,
            'body_html'     => $this->render($md),
            'sort_order'    => (int) ($d['sort_order'] ?? $this->nextModuleOrder($courseId)),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);
    }

    public function updateModule(int $id, array $d): bool
    {
        $md = (string) ($d['body_markdown'] ?? '');

        return DB::table('training_module')->where('id', $id)->update([
            'title'         => trim((string) ($d['title'] ?? 'Untitled module')),
            'lecture_id'    => $d['lecture_id'] ?: null,
            'body_markdown' => $md,
            'body_html'     => $this->render($md),
            'sort_order'    => (int) ($d['sort_order'] ?? 0),
            'updated_at'    => Carbon::now(),
        ]) >= 0;
    }

    public function deleteModule(int $id): void
    {
        DB::table('training_progress')->where('module_id', $id)->delete();
        DB::table('training_module')->where('id', $id)->delete();
    }

    private function nextModuleOrder(int $courseId): int
    {
        return (int) DB::table('training_module')->where('course_id', $courseId)->max('sort_order') + 1;
    }

    /** Curriculum lectures (#1105) available to attach as module content. */
    public function curriculumLectures(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('research_lecture')) {
            return [];
        }

        return DB::table('research_lecture')->where('type', 'curriculum')
            ->orderBy('title')->get(['id', 'title'])->map(fn ($r) => (array) $r)->all();
    }

    // ── Assessment ────────────────────────────────────────────────────────

    public function getAssessment(int $courseId): ?array
    {
        $row = DB::table('training_assessment')->where('course_id', $courseId)->first();

        return $row ? (array) $row : null;
    }

    /** Decoded questions: [['q'=>..., 'options'=>[...], 'answer'=>idx], ...]. */
    public function questions(int $courseId): array
    {
        $a = $this->getAssessment($courseId);
        if (! $a || ! $a['questions_json']) {
            return [];
        }
        $q = json_decode($a['questions_json'], true);

        return is_array($q) ? $q : [];
    }

    public function saveAssessment(int $courseId, array $d): void
    {
        $questions = $d['questions'] ?? [];
        $payload = [
            'title'          => $d['title'] ?? 'Assessment',
            'pass_mark'      => isset($d['pass_mark']) && $d['pass_mark'] !== '' ? (int) $d['pass_mark'] : null,
            'questions_json' => json_encode(array_values($questions), JSON_UNESCAPED_SLASHES),
            'updated_at'     => Carbon::now(),
        ];
        $existing = $this->getAssessment($courseId);
        if ($existing) {
            DB::table('training_assessment')->where('id', $existing['id'])->update($payload);
        } else {
            $payload['course_id'] = $courseId;
            $payload['created_at'] = Carbon::now();
            DB::table('training_assessment')->insert($payload);
        }
    }

    // ── Enrolment + progress ────────────────────────────────────────────────

    public function listEnrolments(int $courseId): array
    {
        return DB::table('training_enrolment')->where('course_id', $courseId)
            ->orderByDesc('enrolled_at')->get()->map(fn ($e) => (array) $e)->all();
    }

    public function getEnrolment(int $id): ?array
    {
        $row = DB::table('training_enrolment')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function enrol(int $courseId, array $d): int
    {
        return (int) DB::table('training_enrolment')->insertGetId([
            'course_id'     => $courseId,
            'user_id'       => $d['user_id'] ?? null,
            'learner_name'  => $d['learner_name'] ?? null,
            'learner_email' => $d['learner_email'] ?? null,
            'status'        => 'enrolled',
            'enrolled_at'   => Carbon::now(),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);
    }

    public function deleteEnrolment(int $id): void
    {
        DB::table('training_progress')->where('enrolment_id', $id)->delete();
        DB::table('training_certificate')->where('enrolment_id', $id)->delete();
        DB::table('training_enrolment')->where('id', $id)->delete();
    }

    public function completedModuleIds(int $enrolmentId): array
    {
        return DB::table('training_progress')->where('enrolment_id', $enrolmentId)
            ->where('completed', 1)->pluck('module_id')->map(fn ($i) => (int) $i)->all();
    }

    public function markModule(int $enrolmentId, int $moduleId, bool $completed = true): void
    {
        DB::table('training_progress')->updateOrInsert(
            ['enrolment_id' => $enrolmentId, 'module_id' => $moduleId],
            ['completed' => $completed ? 1 : 0, 'completed_at' => $completed ? Carbon::now() : null, 'updated_at' => Carbon::now()]
        );
        $enrol = $this->getEnrolment($enrolmentId);
        if ($enrol && $enrol['status'] === 'enrolled') {
            DB::table('training_enrolment')->where('id', $enrolmentId)->update(['status' => 'in_progress', 'updated_at' => Carbon::now()]);
        }
    }

    /**
     * Score an assessment attempt, record the best score, and — when all modules
     * are complete AND the score meets the pass mark — mark the enrolment
     * completed and issue a certificate. Returns [score, passed, certificate_no?].
     */
    public function submitAssessment(int $enrolmentId, array $answers): array
    {
        $enrol = $this->getEnrolment($enrolmentId);
        if (! $enrol) {
            return ['score' => 0, 'passed' => false];
        }
        $courseId = (int) $enrol['course_id'];
        $questions = $this->questions($courseId);
        $total = count($questions);
        $correct = 0;
        foreach ($questions as $i => $q) {
            if (isset($answers[$i]) && (int) $answers[$i] === (int) ($q['answer'] ?? -1)) {
                $correct++;
            }
        }
        $score = $total > 0 ? (int) round($correct / $total * 100) : 0;

        $course = $this->getCourse($courseId);
        $assessment = $this->getAssessment($courseId);
        $passMark = $assessment && $assessment['pass_mark'] !== null ? (int) $assessment['pass_mark'] : (int) ($course['pass_mark'] ?? 80);

        $allModulesDone = $this->allModulesComplete($enrolmentId, $courseId);
        $passed = $total > 0 && $score >= $passMark;

        // Record best score.
        $best = max((int) ($enrol['score'] ?? 0), $score);
        $update = ['score' => $best, 'updated_at' => Carbon::now()];

        $certNo = null;
        if ($passed && $allModulesDone) {
            $update['status'] = 'completed';
            $update['completed_at'] = Carbon::now();
            $certNo = $this->issueCertificate($enrolmentId, $best);
        }
        DB::table('training_enrolment')->where('id', $enrolmentId)->update($update);

        return ['score' => $score, 'passed' => $passed, 'all_modules_done' => $allModulesDone, 'pass_mark' => $passMark, 'certificate_no' => $certNo];
    }

    public function allModulesComplete(int $enrolmentId, int $courseId): bool
    {
        $moduleIds = DB::table('training_module')->where('course_id', $courseId)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if (! $moduleIds) {
            return true; // a course with no modules is trivially "covered"
        }
        $done = $this->completedModuleIds($enrolmentId);

        return count(array_diff($moduleIds, $done)) === 0;
    }

    public function getCertificate(int $enrolmentId): ?array
    {
        $row = DB::table('training_certificate')->where('enrolment_id', $enrolmentId)->first();

        return $row ? (array) $row : null;
    }

    private function issueCertificate(int $enrolmentId, int $score): string
    {
        $existing = $this->getCertificate($enrolmentId);
        if ($existing) {
            return $existing['certificate_no'];
        }
        $no = 'CERT-'.str_pad((string) $enrolmentId, 6, '0', STR_PAD_LEFT).'-'.substr(md5($enrolmentId.'|'.$score), 0, 6);
        DB::table('training_certificate')->insert([
            'enrolment_id'   => $enrolmentId,
            'certificate_no' => strtoupper($no),
            'score'          => $score,
            'issued_at'      => Carbon::now(),
            'created_at'     => Carbon::now(),
        ]);

        return strtoupper($no);
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    public function render(string $md): string
    {
        if ($md === '') {
            return '';
        }

        return (string) (new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]))->convert($md);
    }
}
