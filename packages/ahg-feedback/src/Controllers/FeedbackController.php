<?php

/**
 * FeedbackController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
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



namespace AhgFeedback\Controllers;

use AhgCore\Pagination\SimplePager;
use App\Http\Controllers\Controller;
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

            if ($slug) {
                return redirect('/' . $slug)
                    ->with('success', 'Thank you for your feedback. We will review it shortly.');
            }

            return redirect()->route('feedback.general')
                ->with('success', 'Thank you for your feedback. We will review it shortly.');
        }

        return view('ahg-feedback::general', [
            'feedbackTypes' => $feedbackTypes,
            'slug' => $slug,
        ]);
    }

    /**
     * Browse all feedback (admin).
     */
    public function browse(Request $request)
    {
        $culture = app()->getLocale();
        $status = $request->input('status', 'all');
        $sort = $request->input('sort', 'dateDown');
        $page = max(1, (int) $request->input('page', 1));
        $limit = 30;

        // Build base query
        $query = DB::table('feedback')
            ->join('feedback_i18n', function ($j) use ($culture) {
                $j->on('feedback.id', '=', 'feedback_i18n.id')
                    ->where('feedback_i18n.culture', '=', $culture);
            })
            ->leftJoin('object', 'feedback.id', '=', 'object.id');

        // Filter by status
        if ($status === 'pending') {
            $query->where('feedback_i18n.status', '=', 'pending');
        } elseif ($status === 'completed') {
            $query->where('feedback_i18n.status', '=', 'completed');
        }

        // Stats (counts for sidebar)
        $totalCount = DB::table('feedback')
            ->join('feedback_i18n', function ($j) use ($culture) {
                $j->on('feedback.id', '=', 'feedback_i18n.id')
                    ->where('feedback_i18n.culture', '=', $culture);
            })
            ->count();

        $pendingCount = DB::table('feedback')
            ->join('feedback_i18n', function ($j) use ($culture) {
                $j->on('feedback.id', '=', 'feedback_i18n.id')
                    ->where('feedback_i18n.culture', '=', $culture);
            })
            ->where('feedback_i18n.status', '=', 'pending')
            ->count();

        $completedCount = DB::table('feedback')
            ->join('feedback_i18n', function ($j) use ($culture) {
                $j->on('feedback.id', '=', 'feedback_i18n.id')
                    ->where('feedback_i18n.culture', '=', $culture);
            })
            ->where('feedback_i18n.status', '=', 'completed')
            ->count();

        // Sort
        switch ($sort) {
            case 'nameUp':
                $query->orderBy('feedback_i18n.name', 'asc');
                break;
            case 'nameDown':
                $query->orderBy('feedback_i18n.name', 'desc');
                break;
            case 'dateUp':
                $query->orderBy('feedback_i18n.created_at', 'asc');
                break;
            case 'dateDown':
            default:
                $query->orderBy('feedback_i18n.created_at', 'desc');
                break;
        }

        // Total for current filter
        $total = (clone $query)->count();

        // Paginate
        $offset = ($page - 1) * $limit;
        $rows = $query
            ->select(
                'feedback.id',
                'feedback.feed_name',
                'feedback.feed_surname',
                'feedback.feed_phone',
                'feedback.feed_email',
                'feedback.feed_relationship',
                'feedback.feed_type_id',
                'feedback.parent_id',
                'feedback_i18n.name',
                'feedback_i18n.remarks',
                'feedback_i18n.status',
                'feedback_i18n.status_id',
                'feedback_i18n.created_at',
                'feedback_i18n.completed_at',
                'feedback_i18n.object_id',
                'feedback_i18n.unique_identifier'
            )
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();

        $pager = new SimplePager([
            'hits' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

        return view('ahg-feedback::browse', [
            'pager' => $pager,
            'status' => $status,
            'sort' => $sort,
            'totalCount' => $totalCount,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
        ]);
    }

    /**
     * Edit a single feedback item (admin).
     */
    public function edit(int $id)
    {
        $culture = app()->getLocale();

        $feedback = DB::table('feedback')
            ->join('feedback_i18n', function ($j) use ($culture) {
                $j->on('feedback.id', '=', 'feedback_i18n.id')
                    ->where('feedback_i18n.culture', '=', $culture);
            })
            ->where('feedback.id', '=', $id)
            ->select(
                'feedback.id',
                'feedback.feed_name',
                'feedback.feed_surname',
                'feedback.feed_phone',
                'feedback.feed_email',
                'feedback.feed_relationship',
                'feedback.feed_type_id',
                'feedback.parent_id',
                'feedback_i18n.name',
                'feedback_i18n.remarks',
                'feedback_i18n.status',
                'feedback_i18n.status_id',
                'feedback_i18n.created_at',
                'feedback_i18n.completed_at',
                'feedback_i18n.object_id',
                'feedback_i18n.unique_identifier'
            )
            ->first();

        if (!$feedback) {
            abort(404, 'Feedback not found.');
        }

        return view('ahg-feedback::edit', [
            'feedback' => $feedback,
        ]);
    }

    /**
     * Update feedback status and admin notes (admin).
     */
    public function update(Request $request, int $id)
    {
        $culture = app()->getLocale();

        $request->validate([
            'status' => 'required|in:pending,completed',
            'admin_notes' => 'nullable|string',
        ]);

        $data = [
            'status' => $request->input('status'),
        ];

        // If status is completed and completed_at is not set, set it now
        if ($request->input('status') === 'completed') {
            $data['completed_at'] = $request->input('completed_at') ?: now();
            $data['status_id'] = 1031;
        } else {
            $data['completed_at'] = null;
            $data['status_id'] = 1030;
        }

        // Store admin notes in unique_identifier field (re-purpose available column)
        if ($request->has('admin_notes')) {
            $data['unique_identifier'] = $request->input('admin_notes');
        }

        DB::table('feedback_i18n')
            ->where('id', '=', $id)
            ->where('culture', '=', $culture)
            ->update($data);

        return redirect()->route('feedback.browse')
            ->with('success', 'Feedback updated successfully.');
    }

    /**
     * Delete a feedback item (admin).
     */
    public function destroy(int $id)
    {
        $culture = app()->getLocale();

        // Delete i18n records (all cultures)
        DB::table('feedback_i18n')->where('id', '=', $id)->delete();

        // Delete feedback record
        DB::table('feedback')->where('id', '=', $id)->delete();

        // Delete object record
        DB::table('object')->where('id', '=', $id)->delete();

        return redirect()->route('feedback.browse')
            ->with('success', 'Feedback deleted successfully.');
    }

    public function view(int $id)
    {
        $culture = app()->getLocale();
        $record = DB::table('feedback')->join('feedback_i18n', function($j) use ($culture) { $j->on('feedback.id','=','feedback_i18n.id')->where('feedback_i18n.culture','=',$culture); })->where('feedback.id', $id)->first();
        if (!$record) abort(404);
        return view('ahg-feedback::view', ['record' => $record]);
    }

    public function submitSuccess() { return view('ahg-feedback::submit'); }
}
