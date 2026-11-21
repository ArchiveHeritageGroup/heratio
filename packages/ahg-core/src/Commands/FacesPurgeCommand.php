<?php

/**
 * FacesPurgeCommand - Heratio
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

namespace AhgCore\Commands;

use AhgCore\Services\FaceRecognitionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1764: delete one person's face data for a data-subject request -
 * every face assigned to them (box and embedding), their rejected
 * suggestions and the person row. Audited. Name access points stay.
 */
class FacesPurgeCommand extends Command
{
    protected $signature = 'ahg:faces-purge {person : face_person id} {--force : do not ask for confirmation}';

    protected $description = 'Delete one person\'s face data (data-subject request)';

    public function handle(FaceRecognitionService $faces): int
    {
        $id = (int) $this->argument('person');
        $person = DB::table('face_person')->where('id', $id)->first();
        if (! $person) {
            $this->error("No person with id {$id}.");

            return self::FAILURE;
        }
        $n = DB::table('digital_object_faces')->where('person_id', $id)->count();
        if (! $this->option('force') && ! $this->confirm("Delete {$n} face(s) and the person record for \"{$person->name}\"?")) {
            return self::FAILURE;
        }
        $r = $faces->purgePerson($id);
        $this->info("Purged person {$id}: {$r['faces']} face(s), {$r['rejections']} rejected suggestion(s).");

        return self::SUCCESS;
    }
}
