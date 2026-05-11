<?php

/**
 * InformationObjectSnapshotObserver — Eloquent observer that writes a version
 * row whenever an InformationObject is saved.
 *
 * Respects VersionContext::isSkipped() for bulk-import paths that want to emit
 * one version per record after the loop instead of one per intermediate save.
 *
 * Registered in AhgVersionControlServiceProvider::boot() via
 *   InformationObject::observe(InformationObjectSnapshotObserver::class)
 *
 * @phase D
 */

namespace AhgVersionControl\Observers;

use AhgCore\Models\InformationObject;
use AhgVersionControl\Services\SnapshotBuilder;
use AhgVersionControl\Services\VersionContext;
use AhgVersionControl\Services\VersionWriter;

class InformationObjectSnapshotObserver
{
    public function __construct(
        private readonly SnapshotBuilder $builder,
        private readonly VersionWriter $writer,
    ) {
    }

    public function saved(InformationObject $io): void
    {
        if (VersionContext::isSkipped()) {
            return;
        }
        if ($io->id === null) {
            return;
        }

        try {
            $userId  = VersionContext::takeUserId() ?? auth()->id();
            $summary = VersionContext::takeSummary() ?? 'Saved via ' . request()->path();
            $this->writer->write(
                entityType: 'information_object',
                entityId: (int) $io->id,
                snapshot: $this->builder->buildForInformationObject((int) $io->id),
                changeSummary: $summary,
                userId: $userId !== null ? (int) $userId : null,
            );
        } catch (\Throwable $e) {
            // Never let a versioning failure break the save.
            \Log::warning('ahg-version-control snapshot failed for information_object', [
                'id' => $io->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
