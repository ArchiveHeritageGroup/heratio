<?php

namespace AhgInformationObjectManage\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Migrated from /usr/share/nginx/archive/atom-ahg-plugins/ahgAIPlugin/
 */
class AiController extends Controller
{
    /**
     * Extract named entities (NER) from an IO's description text.
     * AtoM route: /ai/ner/extract/:id
     */
    public function extract(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        // Check for existing extractions
        try {
            $entities = DB::table('ahg_ner_entity')
                ->join('ahg_ner_entity_link', 'ahg_ner_entity.id', '=', 'ahg_ner_entity_link.entity_id')
                ->where('ahg_ner_entity_link.object_id', $id)
                ->select('ahg_ner_entity.*', 'ahg_ner_entity_link.confidence')
                ->orderBy('ahg_ner_entity.entity_type')
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $entities = collect();
        }

        return view('ahg-io-manage::ai.extract', [
            'io' => $io,
            'entities' => $entities,
        ]);
    }

    /**
     * NER Review dashboard.
     * AtoM route: /ai/ner/review
     */
    public function review()
    {
        try {
            $pending = DB::table('ahg_ner_extraction')
                ->where('status', 'pending_review')
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();
        } catch (\Illuminate\Database\QueryException $e) {
            $pending = collect();
        }

        return view('ahg-io-manage::ai.review', [
            'pending' => $pending,
        ]);
    }

    /**
     * Generate summary for an IO.
     */
    public function summarize(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::ai.summarize', [
            'io' => $io,
        ]);
    }

    /**
     * Translate an IO's description.
     */
    public function translate(int $id)
    {
        $io = $this->getIOById($id);
        if (!$io) {
            abort(404);
        }

        return view('ahg-io-manage::ai.translate', [
            'io' => $io,
        ]);
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
            ->select('io.id', 'i18n.title', 'i18n.scope_and_content', 's.slug')
            ->first();
    }
}
