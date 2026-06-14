<?php

/**
 * ResearchRiCSubscriber - maps research lifecycle events to the RiC bridge.
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

namespace AhgResearch\Listeners;

use AhgResearch\Events\OutputPublished;
use AhgResearch\Events\ProjectClosed;
use AhgResearch\Events\ProjectCreated;
use AhgResearch\Events\ProjectUpdated;
use AhgResearch\Services\RiCBridgeService;
use Illuminate\Events\Dispatcher;

/**
 * Wires the four AhgResearch lifecycle events (#1254) onto RiCBridgeService so
 * research projects + outputs are mirrored into the Records in Contexts graph.
 * Each handler is a thin delegate; the bridge owns all guard / try-catch logic
 * so a graph-publish failure can never break the triggering request.
 */
class ResearchRiCSubscriber
{
    public function __construct(private RiCBridgeService $bridge) {}

    public function handleProjectCreated(ProjectCreated $event): void
    {
        $this->bridge->publishProjectCreated($event->projectId);
    }

    public function handleProjectUpdated(ProjectUpdated $event): void
    {
        $this->bridge->publishProjectUpdated($event->projectId);
    }

    public function handleProjectClosed(ProjectClosed $event): void
    {
        $this->bridge->publishProjectClosed($event->projectId);
    }

    public function handleOutputPublished(OutputPublished $event): void
    {
        $this->bridge->publishOutputPublished($event->outputId);
    }

    /**
     * Register the listeners.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ProjectCreated::class  => 'handleProjectCreated',
            ProjectUpdated::class  => 'handleProjectUpdated',
            ProjectClosed::class   => 'handleProjectClosed',
            OutputPublished::class => 'handleOutputPublished',
        ];
    }
}
