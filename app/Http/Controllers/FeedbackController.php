<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FeedbackController extends Controller
{
    public function general(Request $request, ?string $slug = null)
    {
        $culture = app()->getLocale();

        // Get feedback types from taxonomy
        $feedbackTypes = DB::table('term')
            ->join('term_i18n', function ($j) use ($culture) {
                $j->on('term.id', '=', 'term_i18n.id')->where('term_i18n.culture', '=', $culture);
            })
            ->where('term.taxonomy_id', function ($q) {
                $q->select('id')->from('taxonomy_i18n')
                    ->where('name', 'LIKE', '%feedback%')->where('culture', 'en')->limit(1);
            })
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();

        // If no feedback taxonomy, provide defaults
        if ($feedbackTypes->isEmpty()) {
            $feedbackTypes = collect([
                (object) ['id' => 0, 'name' => 'General feedback'],
                (object) ['id' => 1, 'name' => 'Bug report'],
                (object) ['id' => 2, 'name' => 'Feature request'],
                (object) ['id' => 3, 'name' => 'Content correction'],
                (object) ['id' => 4, 'name' => 'Compliment'],
            ]);
        }

        if ($request->isMethod('post')) {
            $request->validate([
                'subject' => 'required|string|max:1024',
                'remarks' => 'required|string',
                'feed_name' => 'required|string|max:50',
                'feed_surname' => 'required|string|max:50',
                'feed_email' => 'required|email|max:50',
            ]);

            // Create object record
            $objectId = DB::table('object')->insertGetId([
                'class_name' => 'QubitFeedback',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create feedback record
            DB::table('feedback')->insert([
                'id' => $objectId,
                'feed_name' => $request->input('feed_name'),
                'feed_surname' => $request->input('feed_surname'),
                'feed_phone' => $request->input('feed_phone', ''),
                'feed_email' => $request->input('feed_email'),
                'feed_relationship' => $request->input('feed_relationship', ''),
                'feed_type_id' => $request->input('feed_type_id', 0),
                'parent_id' => $slug,
                'lft' => 0,
                'rgt' => 1,
                'source_culture' => $culture,
            ]);

            // Create feedback_i18n record
            DB::table('feedback_i18n')->insert([
                'id' => $objectId,
                'name' => $request->input('subject'),
                'remarks' => $request->input('remarks'),
                'status_id' => 220,
                'status' => 'pending',
                'created_at' => now(),
                'culture' => $culture,
            ]);

            return redirect()->route('feedback.general')
                ->with('success', 'Thank you for your feedback. We will review it shortly.');
        }

        return view('feedback.general', [
            'feedbackTypes' => $feedbackTypes,
            'slug' => $slug,
        ]);
    }
}
