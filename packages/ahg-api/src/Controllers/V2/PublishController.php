<?php

namespace AhgApi\Controllers\V2;

use AhgApi\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublishController extends BaseApiController
{
    public function __construct(protected WebhookService $webhooks)
    {
        parent::__construct();
    }

    /**
     * GET /api/v2/publish/readiness/{slug}
     */
    public function readiness(string $slug): JsonResponse
    {
        $id = $this->slugToId($slug);
        if (!$id) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $io = DB::table('information_object as io')
            ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
            ->where('io.id', $id)
            ->where('ioi.culture', $this->culture)
            ->select('io.id', 'io.identifier', 'io.level_of_description_id', 'io.repository_id',
                'ioi.title', 'ioi.scope_and_content')
            ->first();

        $blockers = [];

        if (empty($io->title)) {
            $blockers[] = ['field' => 'title', 'message' => 'Title is required.'];
        }
        if (empty($io->level_of_description_id)) {
            $blockers[] = ['field' => 'level_of_description_id', 'message' => 'Level of description is required.'];
        }
        if (empty($io->identifier)) {
            $blockers[] = ['field' => 'identifier', 'message' => 'Identifier (reference code) is recommended.'];
        }

        // Check current status
        $status = DB::table('status')->where('object_id', $id)->where('type_id', 158)->first();
        $isPublished = $status && $status->status_id == 160;

        return $this->success([
            'slug' => $slug,
            'is_published' => $isPublished,
            'ready' => empty($blockers),
            'blockers' => $blockers,
        ]);
    }

    /**
     * POST /api/v2/publish/execute/{slug}
     */
    public function execute(string $slug, Request $request): JsonResponse
    {
        $id = $this->slugToId($slug);
        if (!$id) {
            return $this->error('Not Found', "Description '{$slug}' not found.", 404);
        }

        $force = $request->boolean('force', false);

        // Check readiness (unless forced)
        if (!$force) {
            $io = DB::table('information_object as io')
                ->join('information_object_i18n as ioi', 'io.id', '=', 'ioi.id')
                ->where('io.id', $id)
                ->where('ioi.culture', $this->culture)
                ->select('ioi.title', 'io.level_of_description_id')
                ->first();

            if (empty($io->title) || empty($io->level_of_description_id)) {
                return $this->error('Precondition Failed', 'Description not ready for publication. Use force=true to override.', 412);
            }
        }

        // Block publish if workflow approval is required but not completed
        if (!$force) {
            try {
                $workflowService = app(\AhgWorkflow\Services\WorkflowService::class);
                if ($workflowService->isWorkflowRequiredForPublish()
                    && !$workflowService->isWorkflowApprovedForPublish($id)) {
                    return $this->error('Precondition Failed',
                        'Workflow approval required before publishing. Start or complete a workflow first.', 412);
                }
            } catch (\Exception $e) {
                // Workflow package not available — allow publish
            }
        }

        // Set publication status to published (160)
        DB::table('status')
            ->where('object_id', $id)
            ->where('type_id', 158)
            ->update(['status_id' => 160]);

        DB::table('object')->where('id', $id)->update(['updated_at' => now()]);

        $this->webhooks->trigger('item.published', 'informationobject', $id, ['slug' => $slug]);

        return $this->success([
            'published' => true,
            'object_id' => $id,
            'slug' => $slug,
        ]);
    }
}
