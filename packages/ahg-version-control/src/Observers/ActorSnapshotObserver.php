<?php

/**
 * ActorSnapshotObserver — Eloquent observer that writes a version row whenever
 * an Actor is saved.
 *
 * @phase D
 */

namespace AhgVersionControl\Observers;

use AhgCore\Models\Actor;
use AhgVersionControl\Services\SnapshotBuilder;
use AhgVersionControl\Services\VersionContext;
use AhgVersionControl\Services\VersionWriter;

class ActorSnapshotObserver
{
    public function __construct(
        private readonly SnapshotBuilder $builder,
        private readonly VersionWriter $writer,
    ) {
    }

    public function saved(Actor $actor): void
    {
        if (VersionContext::isSkipped()) {
            return;
        }
        if ($actor->id === null) {
            return;
        }

        try {
            $userId  = VersionContext::takeUserId() ?? auth()->id();
            $summary = VersionContext::takeSummary() ?? 'Saved via ' . request()->path();
            $this->writer->write(
                entityType: 'actor',
                entityId: (int) $actor->id,
                snapshot: $this->builder->buildForActor((int) $actor->id),
                changeSummary: $summary,
                userId: $userId !== null ? (int) $userId : null,
            );
        } catch (\Throwable $e) {
            \Log::warning('ahg-version-control snapshot failed for actor', [
                'id' => $actor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
