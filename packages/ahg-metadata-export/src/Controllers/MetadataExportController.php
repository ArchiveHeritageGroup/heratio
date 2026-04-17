<?php

/**
 * MetadataExportController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgMetadataExport\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MetadataExportController extends Controller
{
    /**
     * Metadata export dashboard — list available formats.
     */
    public function index()
    {
        $formats = [
            'dc'       => ['name' => 'Dublin Core (RDF/XML)', 'icon' => 'bi-file-earmark-code'],
            'mods'     => ['name' => 'MODS (XML)', 'icon' => 'bi-file-earmark-code'],
            'ead'      => ['name' => 'EAD 2002 (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac'      => ['name' => 'EAC-CPF (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac2'     => ['name' => 'EAC-CPF 2.0 (XML)', 'icon' => 'bi-file-earmark-code'],
            'ead4'     => ['name' => 'EAD 4 (XML)', 'icon' => 'bi-file-earmark-code'],
            'eac-f'    => ['name' => 'EAC-F Functions (XML)', 'icon' => 'bi-file-earmark-code'],
            'eag'      => ['name' => 'EAG 3.0 (XML)', 'icon' => 'bi-file-earmark-code'],
            'json-ld'  => ['name' => 'JSON-LD', 'icon' => 'bi-braces'],
            'turtle'   => ['name' => 'Turtle (TTL)', 'icon' => 'bi-file-earmark-text'],
            'ntriples' => ['name' => 'N-Triples', 'icon' => 'bi-file-earmark-text'],
        ];

        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-metadata-export::index', compact('formats', 'repositories'));
    }

    /**
     * Preview a single record in the selected metadata format.
     */
    public function preview(Request $request)
    {
        $slug = $request->query('slug', '');
        $format = $request->query('format', 'dc');

        return view('ahg-metadata-export::preview', compact('slug', 'format'));
    }

    /**
     * Bulk export page.
     */
    public function bulk(Request $request)
    {
        $format = $request->query('format', 'dc');
        $formatInfo = ['name' => strtoupper($format)];

        $repositories = DB::table('repository')
            ->join('actor_i18n', function ($join) {
                $join->on('repository.id', '=', 'actor_i18n.id')
                     ->where('actor_i18n.culture', '=', 'en');
            })
            ->select('repository.id', 'actor_i18n.authorized_form_of_name as authorizedFormOfName', 'repository.id')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();

        return view('ahg-metadata-export::bulk', compact('format', 'formatInfo', 'repositories'));
    }
}
