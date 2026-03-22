<?php

namespace AhgTranslation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
    /**
     * Translation settings page.
     */
    public function settings()
    {
        $cultures = DB::table('setting')
            ->where('name', 'i18n_languages')
            ->value('value');

        $defaultCulture = DB::table('setting')
            ->where('name', 'default_culture')
            ->value('value') ?? 'en';

        return view('ahg-translation::settings', [
            'cultures' => $cultures ? json_decode($cultures, true) : ['en'],
            'defaultCulture' => $defaultCulture,
        ]);
    }
}
