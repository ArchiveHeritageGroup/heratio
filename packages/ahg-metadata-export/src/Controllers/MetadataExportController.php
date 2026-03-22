<?php

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
