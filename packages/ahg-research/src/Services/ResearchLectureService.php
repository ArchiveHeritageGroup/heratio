<?php

/**
 * ResearchLectureService - lecture builder for the research portal (#1105).
 *
 * One model, three uses (the `type` column):
 *   - curriculum: teaching content that feeds the #1099 training curriculum.
 *   - talk:       a public lecture/seminar record (speaker, schedule, recording).
 *   - standalone: a reusable authored lecture (ordered sections + media).
 *
 * A lecture has ordered content sections and a list of resources (readings,
 * slides, links, files).
 *
 * @author    Johan Pieterse
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgResearch\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class ResearchLectureService
{
    public const TYPES          = ['curriculum', 'talk', 'standalone'];
    public const STATUSES       = ['draft', 'scheduled', 'delivered', 'published', 'archived'];
    public const MEDIA_TYPES    = ['image', 'video', 'audio', 'embed'];
    public const RESOURCE_TYPES = ['reading', 'slides', 'video', 'link', 'file'];

    // ── Lectures ─────────────────────────────────────────────────────────

    public function listLectures(?string $type = null): array
    {
        $q = DB::table('research_lecture')->orderByDesc('scheduled_at')->orderByDesc('updated_at');
        if ($type !== null) {
            $q->where('type', $type);
        }

        return $q->get()->map(fn ($l) => (array) $l)->all();
    }

    public function getLecture(int $id): ?array
    {
        $row = DB::table('research_lecture')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createLecture(array $data): int
    {
        $p = $this->lecturePayload($data, true);

        return (int) DB::table('research_lecture')->insertGetId($p);
    }

    public function updateLecture(int $id, array $data): bool
    {
        return DB::table('research_lecture')->where('id', $id)->update($this->lecturePayload($data, false)) >= 0;
    }

    public function deleteLecture(int $id): void
    {
        DB::table('research_lecture_section')->where('lecture_id', $id)->delete();
        DB::table('research_lecture_resource')->where('lecture_id', $id)->delete();
        DB::table('research_lecture')->where('id', $id)->delete();
    }

    public function setStatus(int $id, string $status): bool
    {
        if (! in_array($status, self::STATUSES, true)) {
            return false;
        }

        return DB::table('research_lecture')->where('id', $id)
            ->update(['status' => $status, 'updated_at' => Carbon::now()]) > 0;
    }

    private function lecturePayload(array $d, bool $isNew): array
    {
        $p = [
            'type'                => in_array(($d['type'] ?? null), self::TYPES, true) ? $d['type'] : 'standalone',
            'title'               => trim((string) ($d['title'] ?? 'Untitled lecture')),
            'subtitle'            => $d['subtitle'] ?? null,
            'summary'             => $d['summary'] ?? null,
            'speaker_name'        => $d['speaker_name'] ?? null,
            'speaker_affiliation' => $d['speaker_affiliation'] ?? null,
            'scheduled_at'        => ! empty($d['scheduled_at']) ? $d['scheduled_at'] : null,
            'location'            => $d['location'] ?? null,
            'duration_minutes'    => isset($d['duration_minutes']) && $d['duration_minutes'] !== '' ? (int) $d['duration_minutes'] : null,
            'recording_url'       => $d['recording_url'] ?? null,
            'slides_url'          => $d['slides_url'] ?? null,
            'curriculum_ref'      => $d['curriculum_ref'] ?? null,
            'updated_at'          => Carbon::now(),
        ];
        if ($isNew) {
            $p['researcher_id'] = $d['researcher_id'] ?? null;
            $p['status']        = in_array(($d['status'] ?? null), self::STATUSES, true) ? $d['status'] : 'draft';
            $p['created_at']    = Carbon::now();
        } elseif (isset($d['status']) && in_array($d['status'], self::STATUSES, true)) {
            $p['status'] = $d['status'];
        }

        return $p;
    }

    // ── Sections ──────────────────────────────────────────────────────────

    public function listSections(int $lectureId): array
    {
        return DB::table('research_lecture_section')->where('lecture_id', $lectureId)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->map(fn ($s) => (array) $s)->all();
    }

    public function getSection(int $id): ?array
    {
        $row = DB::table('research_lecture_section')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    public function createSection(int $lectureId, array $data): int
    {
        $markdown = (string) ($data['body_markdown'] ?? '');

        return (int) DB::table('research_lecture_section')->insertGetId([
            'lecture_id'    => $lectureId,
            'heading'       => $data['heading'] ?? null,
            'body_markdown' => $markdown,
            'body_html'     => $this->renderMarkdown($markdown),
            'media_url'     => $data['media_url'] ?? null,
            'media_type'    => in_array(($data['media_type'] ?? null), self::MEDIA_TYPES, true) ? $data['media_type'] : null,
            'sort_order'    => (int) ($data['sort_order'] ?? $this->nextSectionOrder($lectureId)),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);
    }

    public function updateSection(int $id, array $data): bool
    {
        $markdown = (string) ($data['body_markdown'] ?? '');

        return DB::table('research_lecture_section')->where('id', $id)->update([
            'heading'       => $data['heading'] ?? null,
            'body_markdown' => $markdown,
            'body_html'     => $this->renderMarkdown($markdown),
            'media_url'     => $data['media_url'] ?? null,
            'media_type'    => in_array(($data['media_type'] ?? null), self::MEDIA_TYPES, true) ? $data['media_type'] : null,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'updated_at'    => Carbon::now(),
        ]) >= 0;
    }

    public function deleteSection(int $id): void
    {
        DB::table('research_lecture_section')->where('id', $id)->delete();
    }

    private function nextSectionOrder(int $lectureId): int
    {
        return (int) DB::table('research_lecture_section')->where('lecture_id', $lectureId)->max('sort_order') + 1;
    }

    // ── Resources ───────────────────────────────────────────────────────────

    public function listResources(int $lectureId): array
    {
        return DB::table('research_lecture_resource')->where('lecture_id', $lectureId)
            ->orderBy('sort_order')->orderBy('id')
            ->get()->map(fn ($r) => (array) $r)->all();
    }

    public function createResource(int $lectureId, array $data): int
    {
        return (int) DB::table('research_lecture_resource')->insertGetId([
            'lecture_id'    => $lectureId,
            'label'         => trim((string) ($data['label'] ?? 'Resource')),
            'url'           => $data['url'] ?? null,
            'resource_type' => in_array(($data['resource_type'] ?? null), self::RESOURCE_TYPES, true) ? $data['resource_type'] : 'link',
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
            'created_at'    => Carbon::now(),
            'updated_at'    => Carbon::now(),
        ]);
    }

    public function deleteResource(int $id): void
    {
        DB::table('research_lecture_resource')->where('id', $id)->delete();
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    public function renderMarkdown(string $markdown): string
    {
        if ($markdown === '') {
            return '';
        }
        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return (string) $converter->convert($markdown);
    }
}
