<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgExtendedRightsPlugin/
 * and /usr/share/nginx/archive/atom-ahg-plugins/ahgRightsPlugin/
 */
class ExtendedRightsController extends Controller
{
    /**
     * Add extended rights for an IO.
     */
    public function add(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $existingRights = DB::table('rights')
            ->where('object_id', $io->id)
            ->get();

        return view('ahg-io-manage::rights.extended', [
            'io' => $io,
            'rights' => $existingRights,
        ]);
    }

    /**
     * Add embargo to an IO.
     */
    public function embargo(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::rights.embargo', [
            'io' => $io,
        ]);
    }

    /**
     * Export rights as JSON-LD.
     */
    public function exportJsonLd(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $rights = DB::table('rights')
            ->join('rights_i18n', function ($j) {
                $j->on('rights_i18n.id', '=', 'rights.id')
                    ->where('rights_i18n.culture', app()->getLocale());
            })
            ->where('rights.object_id', $io->id)
            ->get();

        $jsonLd = [
            '@context' => 'http://schema.org',
            '@type' => 'CreativeWork',
            'name' => $io->title,
            'identifier' => $io->slug,
            'rights' => $rights->map(function ($r) {
                return [
                    '@type' => 'PropertyValue',
                    'propertyID' => 'rights',
                    'value' => $r->rights_note ?? '',
                    'description' => $r->copyright_note ?? '',
                ];
            })->toArray(),
        ];

        return response()->json($jsonLd, 200, [
            'Content-Type' => 'application/ld+json',
        ]);
    }

    private function getIO(string $slug): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('s.slug', $slug)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
