<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgRdm\Services;

use AhgIngest\Services\IngestService;
use AhgInformationObjectManage\Services\InformationObjectService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Dataset orchestration (#1338) - the only net-new logic in ahg-rdm; everything
 * else is wiring into existing services.
 *
 * A Dataset is backed by a container information_object (io_parent_id). Files
 * are deposited as child IOs under it via IngestService (the same path the
 * scanner uses), so digital_object stays the single storage source of truth -
 * no bespoke file handling here.
 */
class DatasetService
{
    /** AtoM root information_object id (datasets hang as top-level containers). */
    private const ROOT_IO = 1;

    /**
     * Create a Dataset: a container information_object + the rdm_dataset row.
     *
     * @return int new rdm_dataset.id
     */
    public function create(string $title, ?string $description, ?int $projectId, ?int $userId): int
    {
        // Container IO via the canonical create (handles object/io/i18n/slug/
        // nested-set). source_standard tags it as RDM-owned.
        $ioId = InformationObjectService::create([
            'title'             => $title,
            'scope_and_content' => $description,
            'parent_id'         => self::ROOT_IO,
            'source_standard'   => 'rdm',
        ], 'en');

        return (int) DB::table('rdm_dataset')->insertGetId([
            'project_id'   => $projectId,
            'io_parent_id' => $ioId,
            'title'        => $title,
            'description'  => $description,
            'status'       => 'draft',
            'created_by'   => $userId,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    /**
     * Deposit uploaded files into a Dataset. Each file is streamed through
     * IngestService::ingestFile() as a child IO+master digital_object under the
     * dataset's container IO. Records the (io_id, do_id) link per file.
     *
     * @param  UploadedFile[]  $files
     * @return array{stored:int, skipped:int}
     */
    public function deposit(int $datasetId, array $files, int $userId): array
    {
        $dataset = DB::table('rdm_dataset')->where('id', $datasetId)->first();
        if (! $dataset) {
            throw new \RuntimeException("Dataset {$datasetId} not found.");
        }

        $ingest = app(IngestService::class);
        $sessionId = $ingest->createSession($userId, [
            'parent_id'    => (int) $dataset->io_parent_id,
            'session_kind' => 'rdm_deposit',
        ]);

        $stored = 0;
        $skipped = 0;
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                $skipped++;
                continue;
            }

            $original = $file->getClientOriginalName();
            // Stage to a stable path; IngestService moves it into the store.
            // Resolve the absolute path via Storage::path (the 'local' disk root
            // is storage/app/private on Laravel 11/12, so never hand-build it).
            $stagedRel = $file->store('rdm-staging');
            $stagedPath = \Illuminate\Support\Facades\Storage::path($stagedRel);

            $result = $ingest->ingestFile($sessionId, $stagedPath, [
                'parent_id' => (int) $dataset->io_parent_id,
                'culture'   => 'en',
                'title'     => pathinfo($original, PATHINFO_FILENAME),
            ], $original);

            DB::table('rdm_dataset_file')->insert([
                'dataset_id'    => $datasetId,
                'io_id'         => (int) ($result['io_id'] ?? 0),
                'do_id'         => isset($result['do_id']) ? (int) $result['do_id'] : null,
                'original_name' => $original,
                'created_at'    => now(),
            ]);
            $stored++;
        }

        DB::table('rdm_dataset')->where('id', $datasetId)->update(['updated_at' => now()]);

        return ['stored' => $stored, 'skipped' => $skipped];
    }

    /** A dataset row + its project title + file count. */
    public function get(int $datasetId): ?object
    {
        return DB::table('rdm_dataset as d')
            ->leftJoin('research_project as p', 'p.id', '=', 'd.project_id')
            ->where('d.id', $datasetId)
            ->select('d.*', 'p.title as project_title')
            ->first();
    }

    /** Files deposited into a dataset, with the served thumbnail path if any. */
    public function files(int $datasetId)
    {
        return DB::table('rdm_dataset_file as f')
            ->where('f.dataset_id', $datasetId)
            ->orderBy('f.id')
            ->get();
    }

    /** All datasets (most recent first) for the index, with project + file count. */
    public function list()
    {
        return DB::table('rdm_dataset as d')
            ->leftJoin('research_project as p', 'p.id', '=', 'd.project_id')
            ->leftJoin('rdm_dataset_file as f', 'f.dataset_id', '=', 'd.id')
            ->groupBy('d.id', 'd.title', 'd.status', 'd.created_at', 'p.title')
            ->orderByDesc('d.id')
            ->select('d.id', 'd.title', 'd.status', 'd.created_at', 'p.title as project_title', DB::raw('COUNT(f.id) as file_count'))
            ->get();
    }
}
