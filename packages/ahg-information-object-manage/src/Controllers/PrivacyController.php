<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgPrivacyPlugin/
 */
class PrivacyController extends Controller
{
    /**
     * Scan an IO for PII.
     */
    public function scan(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::privacy.scan', [
            'io' => $io,
        ]);
    }

    /**
     * Visual redaction tool for digital objects.
     */
    public function redaction(string $slug)
    {
        $io = $this->getIO($slug);
        if (!$io) {
            abort(404);
        }

        $digitalObject = DB::table('digital_object')
            ->where('object_id', $io->id)
            ->where('usage_id', 141)
            ->first();

        return view('ahg-io-manage::privacy.redaction', [
            'io' => $io,
            'digitalObject' => $digitalObject,
        ]);
    }

    /**
     * Privacy dashboard.
     */
    public function dashboard()
    {
        return view('ahg-io-manage::privacy.dashboard');
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

    private function getIOById(int $id): ?object
    {
        $culture = app()->getLocale();

        return DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) use ($culture) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', $culture);
            })
            ->join('slug as s', 's.object_id', '=', 'io.id')
            ->where('io.id', $id)
            ->select('io.id', 'i18n.title', 's.slug')
            ->first();
    }
}
