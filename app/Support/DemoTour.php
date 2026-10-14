<?php

/**
 * DemoTour - the guided tour of the heratio.org demo (H06). An unfamiliar
 * archivist follows the steps through browsing, records, 3D, authorities,
 * signing in with the published demo login, editing and creating records, and
 * an exhibition walk-through. A floating card on each page says what to try.
 *
 * The tour runs only on the demo hosts: its steps point at demo records, and
 * client installs have neither those records nor a published demo login.
 * Everything a visitor changes is wiped by the nightly demo reset (02:20 SAST).
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

namespace App\Support;

class DemoTour
{
    /** True on the demo hosts (or when an install opts in with homepage_mode=marketing). */
    public static function enabled(): bool
    {
        return MarketingSite::is();
    }

    /**
     * The steps, in order: [path, title, what to try, CSS selector to highlight or null].
     *
     * @return list<array{path: string, title: string, text: string, target: ?string}>
     */
    public static function steps(): array
    {
        $s = fn (string $path, string $title, string $text, ?string $target = null) => [
            'path' => $path, 'title' => __($title), 'text' => __($text), 'target' => $target,
        ];

        return [
            $s('/glam/browse', 'Browse every collection', 'Archive, museum, library, gallery and digital asset records sit in one catalogue. Use the filters on the left to narrow by type, subject or place, and switch between card, grid and table views.', '#sidebar'),
            $s('/blaauwbosch-farm-2026-excavation', 'Open an archival description', 'A record shows its place in the hierarchy (the tree) and its description in standard areas. Open the tree to move up and down the levels.', '#treeview-card'),
            $s('/the-rosetta-stone', 'Look at an object in 3D', 'Museum objects can carry 3D models. Rotate, zoom and go full screen. The record keeps the licence and credit line of the model.', '[id^="model-3d-"][id$="-container"]'),
            $s('/actor/browse', 'Find people and organisations', 'Authority records describe the people, families and organisations behind the material. Filter by type and open one to see what it is linked to.', '#entityTypeFilter'),
            $s('/login', 'Sign in with the demo login', 'Open the sign-in menu at the top of the page and use the demo login shown there. It has full rights, so you can try everything. All changes are wiped every night.', '#user-menu'),
            $s('/informationobject/blaauwbosch-farm-2026-excavation/edit', 'Edit a record', 'Change a field, add a subject or a place, and save. Every change is recorded in the audit trail.', '#main-column form'),
            $s('/informationobject/add', 'Create a new description', 'Add a description of your own. Give it a title and a level of description, then save and find it in browse.', '#main-column form'),
            $s('/exhibition-space/benson-collection/walkthrough', 'Walk through an exhibition', 'Exhibitions can be published as 3D spaces anyone can walk through in a browser. Start the guided walk and let it take you round the room.', '#wtTourWalkBtn'),
            $s('/solutions/capabilities', 'See what else it does', 'Every capability with its status: released, preview or planned, each with a link to proof. That is the end of the tour.', null),
        ];
    }
}
