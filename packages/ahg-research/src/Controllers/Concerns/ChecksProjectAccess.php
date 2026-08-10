<?php

/**
 * ChecksProjectAccess - shared project view/edit access resolution for research controllers.
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

namespace AhgResearch\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resolve {can_view, can_edit} for a researcher against a project (admin,
 * owner, or collaborator/editor row). Was byte-identical in
 * FieldAlertController, ImpactTrackingController and ResearchMemoryController.
 */
trait ChecksProjectAccess
{
    private function access(object $project, object $researcher): array
    {
        $isAdmin = Auth::check() && \AhgCore\Services\AclService::canAdmin(Auth::id());
        $isOwner = (int) ($project->owner_id ?? 0) === (int) ($researcher->id ?? 0);

        $isCollaborator = false;
        $isEditor       = false;
        try {
            if (Schema::hasTable('research_project_collaborator')) {
                $collab = DB::table('research_project_collaborator')
                    ->where('project_id', $project->id)
                    ->where('researcher_id', $researcher->id)
                    ->first();
                if ($collab) {
                    $isCollaborator = true;
                    $isEditor = in_array($collab->role ?? '', ['owner', 'editor', 'admin'], true);
                }
            }
        } catch (\Throwable $e) {
            // No collaborator access on error.
        }

        return [
            'can_view' => $isAdmin || $isOwner || $isCollaborator,
            'can_edit' => $isAdmin || $isOwner || $isEditor,
        ];
    }
}
