<?php

/**
 * ScholarshipController - "Discovered connections" report page (heratio#1210).
 *
 * Renders the generative-scholarship discovery for a single record: its real
 * cross-collection graph connections (grouped by domain) plus the AI-surfaced,
 * graph-grounded research leads from ScholarshipService::discover(). Admin-gated,
 * matching the rest of the semantic-search admin surface. Resolves the record by
 * numeric id or by slug.
 *
 * It ALSO surfaces CROSS-INSTITUTIONAL connections (heratio#1210, federation
 * increment): related records held by OTHER federation peers, found live via
 * ScholarshipService::discoverFederated() (which consumes the federated-search
 * primitive and explains each hit with a grounded AI one-liner). That section is
 * strictly additive and fully fail-soft - if the federation package is absent,
 * no peers are configured, or a peer/the AI gateway is unreachable, it simply
 * does not appear and the local discovery above is unaffected.
 *
 * The view carries a visible "AI-generated, grounded in catalogue links - verify
 * before citing" disclaimer; the controller never lets an AI failure 500 the
 * page (the service degrades to an empty insight list).
 *
 * @author     Johan Pieterse
 * @copyright  Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
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

namespace AhgSemanticSearch\Controllers;

use AhgSemanticSearch\Services\ScholarshipService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ScholarshipController extends Controller
{
    protected ScholarshipService $service;

    public function __construct()
    {
        $this->service = new ScholarshipService;
    }

    /**
     * Discovered-connections report for one record. Accepts a numeric id or a
     * slug. 404s when the value resolves to no information object.
     */
    public function show(string $objectId)
    {
        $resolvedId = $this->resolveObjectId($objectId);
        if ($resolvedId === null) {
            abort(404, 'Record not found');
        }

        $discovery = $this->service->discover($resolvedId);

        // Additive cross-institutional layer. Fully guarded: any failure here
        // must never affect the local discovery render above, so it falls back
        // to a null federated payload (the view then omits the section).
        $federated = null;
        try {
            $federated = $this->service->discoverFederated($resolvedId);
        } catch (\Throwable $e) {
            Log::info('[scholarship] federated discovery failed for '.$resolvedId.': '.$e->getMessage());
            $federated = null;
        }

        return view('ahg-semantic-search::scholarship', compact('discovery', 'federated'));
    }

    /**
     * Resolve a route value (numeric id or slug) to an information_object id.
     * Returns null when nothing matches.
     */
    protected function resolveObjectId(string $value): ?int
    {
        // Numeric id - verify it is an information object.
        if (ctype_digit($value)) {
            $id = (int) $value;
            if (Schema::hasTable('information_object')
                && DB::table('information_object')->where('id', $id)->exists()) {
                return $id;
            }

            return null;
        }

        // Slug -> object id, then verify it is an information object.
        if (Schema::hasTable('slug')) {
            $id = DB::table('slug')->where('slug', $value)->value('object_id');
            if ($id) {
                $id = (int) $id;
                if (! Schema::hasTable('information_object')
                    || DB::table('information_object')->where('id', $id)->exists()) {
                    return $id;
                }
            }
        }

        return null;
    }
}
