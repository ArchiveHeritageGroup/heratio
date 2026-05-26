<?php

/**
 * RegisterDoiEventsListener.
 *
 * Issue #654 Phase 3. Single listener wired to the three Heratio-side
 * domain events (DoiViewed / DoiDownload / DoiCitation), each handled by
 * its own named method so we get one register() call per logical surface
 * with the correct DataCite relation-type-id.
 *
 * Relation-type-id reference (https://api.eventdata.crossref.org/v1/types):
 *   - unique-dataset-investigations-regular - unique view per session
 *   - unique-dataset-requests-regular       - unique download per session
 *   - is-referenced-by / references         - citation pair
 *   - is-part-of / has-part                 - hierarchical relation
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Listeners;

use AhgDoiManage\Events\DoiCitation;
use AhgDoiManage\Events\DoiDownload;
use AhgDoiManage\Events\DoiViewed;
use AhgDoiManage\Services\DataciteEventsService;

class RegisterDoiEventsListener
{
    public function __construct(protected DataciteEventsService $svc)
    {
    }

    public function handleDoiViewed(DoiViewed $event): void
    {
        $this->svc->register(
            subjectDoi: $event->doi,
            relationTypeId: 'unique-dataset-investigations-regular',
            objectId: $event->url ?? $event->doi,
            objectIdType: $event->url ? 'url' : 'doi',
            source: 'heratio-counter',
            extra: ['total' => 1],
        );
    }

    public function handleDoiDownload(DoiDownload $event): void
    {
        $this->svc->register(
            subjectDoi: $event->doi,
            relationTypeId: 'unique-dataset-requests-regular',
            objectId: $event->doi,
            objectIdType: 'doi',
            source: 'heratio-counter',
            extra: ['total' => 1],
        );
    }

    public function handleDoiCitation(DoiCitation $event): void
    {
        $relType = $this->mapRelationType($event->relationType);
        $idType = strtolower($event->relatedIdentifierType) === 'doi' ? 'doi' : 'url';
        $this->svc->register(
            subjectDoi: $event->subjectDoi,
            relationTypeId: $relType,
            objectId: $event->relatedIdentifier,
            objectIdType: $idType,
            source: 'heratio-archive',
        );
    }

    /**
     * Map DataCite Kernel-4 relationType values (CamelCase) to Event Data
     * relation-type-id slugs (kebab-case).
     */
    protected function mapRelationType(string $relationType): string
    {
        $kebab = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $relationType));
        return $kebab !== '' ? $kebab : 'is-referenced-by';
    }
}
