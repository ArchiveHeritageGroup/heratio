<?php

/**
 * FacesScanCommand - Heratio
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

use AhgCore\Services\FaceDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * heratio#1764: run face detection on files already in the catalogue
 * (ingest only covers new files). Image and video masters; --do for named
 * objects, otherwise masters with no faces yet, up to --limit.
 */
class FacesScanCommand extends Command
{
    protected $signature = 'ahg:faces-scan {--do=* : digital object id(s)} {--limit=100 : masters to scan when --do is not given}';

    protected $description = 'Detect faces in existing image and video masters';

    public function handle(FaceDetectionService $svc): int
    {
        if (! $svc->isEnabled()) {
            $this->warn('Face processing is switched off (face_enabled).');

            return self::SUCCESS;
        }
        $ids = array_map('intval', (array) $this->option('do'));
        if ($ids === []) {
            $ids = DB::table('digital_object as d')
                ->whereNull('d.parent_id')
                ->where(fn ($q) => $q->where('d.mime_type', 'like', 'image/%')->orWhere('d.mime_type', 'like', 'video/%'))
                ->whereNotExists(fn ($q) => $q->from('digital_object_faces as f')->whereColumn('f.digital_object_id', 'd.id'))
                ->orderBy('d.id')->limit(max(1, (int) $this->option('limit')))->pluck('d.id')->map('intval')->all();
        }
        $total = 0;
        foreach ($ids as $id) {
            $n = $svc->detectAndStore($id);
            $total += $n;
            $this->line("digital object {$id}: {$n} face(s)");
        }
        $this->info(count($ids)." file(s) scanned, {$total} face(s) stored.");

        return self::SUCCESS;
    }
}
