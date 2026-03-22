<?php

namespace AhgRightsHolderManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RightsController extends Controller
{
    public function index(string $slug)
    {
        $culture = app()->getLocale();
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) abort(404);

        $resource = DB::table('information_object')
            ->join('information_object_i18n', 'information_object.id', '=', 'information_object_i18n.id')
            ->join('slug', 'information_object.id', '=', 'slug.object_id')
            ->where('information_object.id', $objectId)
            ->where('information_object_i18n.culture', $culture)
            ->select('information_object.id', 'information_object_i18n.title', 'slug.slug')
            ->first();
        if (!$resource) abort(404);

        $rights = DB::table('rights')
            ->leftJoin('rights_i18n', function ($j) use ($culture) {
                $j->on('rights.id', '=', 'rights_i18n.id')->where('rights_i18n.culture', '=', $culture);
            })
            ->where('rights.object_id', $objectId)
            ->select('rights.*', 'rights_i18n.rights_note', 'rights_i18n.copyright_note',
                     'rights_i18n.license_terms', 'rights_i18n.license_note',
                     'rights_i18n.statute_note')
            ->get()
            ->map(function ($r) use ($culture) {
                $row = (array) $r;
                if ($r->basis_id) {
                    $row['basis'] = DB::table('term_i18n')->where('id', $r->basis_id)->where('culture', $culture)->value('name');
                }
                $row['basis_label'] = $row['basis'] ?? 'Rights Record';
                $row['granted_rights'] = DB::table('granted_right')
                    ->leftJoin('granted_right_i18n', function ($j) use ($culture) {
                        $j->on('granted_right.id', '=', 'granted_right_i18n.id')->where('granted_right_i18n.culture', '=', $culture);
                    })
                    ->where('granted_right.rights_id', $r->id)
                    ->select('granted_right.*', 'granted_right_i18n.restriction as restriction_note', 'granted_right_i18n.notes')
                    ->get()
                    ->map(function ($gr) use ($culture) {
                        $arr = (array) $gr;
                        $arr['act'] = $gr->act_id ? DB::table('term_i18n')->where('id', $gr->act_id)->where('culture', $culture)->value('name') : '';
                        $arr['restriction'] = $gr->restriction_id ? DB::table('term_i18n')->where('id', $gr->restriction_id)->where('culture', $culture)->value('name') : '';
                        return $arr;
                    })->toArray();
                return $row;
            })->toArray();

        return view('ahg-rights-holder-manage::rights.index', compact('resource', 'rights'));
    }
}
