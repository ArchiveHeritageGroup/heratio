<?php

/**
 * ComplianceControlController - the Compliance Control Catalog surface.
 *
 *   GET /admin/privacy/control-catalog          admin index (controls + regime filter + search)
 *   GET /admin/privacy/control-catalog.json     the catalogue as a queryable JSON artefact
 *
 * Read-only governance reference. The catalogue is vendor- and jurisdiction-
 * agnostic; it maps regulatory obligations to controls + recommended config so an
 * implementer or procurement team can answer "for this regime, what applies?".
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

namespace AhgPrivacy\Controllers;

use AhgPrivacy\Services\ComplianceControlService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ComplianceControlController extends Controller
{
    protected ComplianceControlService $service;

    public function __construct()
    {
        $this->service = new ComplianceControlService;
    }

    /** Admin index: controls, the regime filter, free-text search. */
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $regime = trim((string) $request->query('regime', ''));

        return view('privacy::control-catalog.index', [
            'controls' => $q !== '' ? $this->service->search($q) : $this->service->controls(),
            'regimes' => $this->service->regimes(),
            'regime' => $regime,
            'regimeMappings' => $regime !== '' ? $this->service->forRegime($regime) : [],
            'q' => $q,
        ]);
    }

    /**
     * Queryable JSON artefact. Optional filters:
     *   ?regime=...   obligations + controls for one regime
     *   ?control=...  a single control + its regime mappings
     *   ?q=...        free-text control search
     * Default: the whole catalogue (controls each with their mappings).
     */
    public function json(Request $request)
    {
        $regime = trim((string) $request->query('regime', ''));
        $control = trim((string) $request->query('control', ''));
        $q = trim((string) $request->query('q', ''));

        if ($control !== '') {
            $row = $this->service->control($control);

            return response()->json(['ok' => $row !== null, 'control' => $row]);
        }
        if ($regime !== '') {
            return response()->json(['ok' => true, 'regime' => $regime, 'mappings' => $this->service->forRegime($regime)]);
        }
        if ($q !== '') {
            return response()->json(['ok' => true, 'query' => $q, 'controls' => $this->service->search($q)]);
        }

        return response()->json([
            'ok' => true,
            'regimes' => $this->service->regimes(),
            'catalog' => $this->service->export(),
        ]);
    }
}
