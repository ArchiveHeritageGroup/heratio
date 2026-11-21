<?php

/**
 * UiStringReviewController - native-speaker review of machine-translated UI
 * strings (heratio#1445): workbook export, workbook import with a preview,
 * and a one-string-at-a-time review screen. Translators and administrators
 * only; the routes carry acl:translate and each action checks the role.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgCore\Controllers;

use AhgCore\Services\AclService;
use AhgCore\Services\UiStringReviewService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UiStringReviewController extends Controller
{
    /** The machine-translated southern African locales of heratio#1445, listed first. */
    private const SA_LOCALES = ['zu', 'xh', 'ss', 'nso', 'tn', 'ts', 'st'];

    public function __construct(private UiStringReviewService $review)
    {
    }

    public function index(Request $request)
    {
        $this->guard();
        $locales = $this->locales();
        $locale = $this->locale($request->query('locale', $locales[0] ?? ''));

        return view('ahg-core::translation-review.index', [
            'locales' => $locales,
            'locale' => $locale,
            'counts' => $this->review->counts($locale),
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function export(Request $request)
    {
        $this->guard();
        $locale = $this->locale($request->query('locale'));
        $rows = $this->review->exportRows($locale, $request->boolean('all'));
        $path = tempnam(sys_get_temp_dir(), 'uisr').'.xlsx';
        $this->review->writeWorkbook($locale, $rows, $path);

        return response()->download($path, "heratio-{$locale}-review-".date('Y-m-d').'.xlsx')->deleteFileAfterSend();
    }

    /** Upload a workbook: a corrected one goes to the preview, a held one is staged. */
    public function upload(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'locale' => 'required|string|max:16',
            'workbook' => 'required|file|mimes:xlsx,csv,txt|max:20480',
            'mode' => 'required|in:review,stage',
            'reviewer' => 'nullable|string|max:255',
            'accept_blank' => 'nullable|boolean',
        ]);
        $locale = $this->locale($data['locale']);
        $token = (string) Str::uuid();
        $ext = strtolower($request->file('workbook')->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
        $name = $request->file('workbook')->getClientOriginalName();
        $file = $request->file('workbook')->move(storage_path('app/translation-review'), "{$token}.{$ext}")->getPathname();

        try {
            if ($data['mode'] === 'stage') {
                $n = $this->review->stageHeld($locale, $file, $name);
                @unlink($file);

                return redirect()->route('translation-review.index', ['locale' => $locale])->with('success',
                    __(':staged held string(s) staged for review; :skipped skipped; :unknown unknown id(s).', $n));
            }
            $preview = $this->review->preview($locale, $file, $request->boolean('accept_blank', true));
        } catch (\InvalidArgumentException $e) {
            @unlink($file);

            return back()->with('error', $e->getMessage());
        }

        return view('ahg-core::translation-review.preview', [
            'locale' => $locale,
            'token' => $token.'.'.$ext,
            'preview' => $preview,
            'reviewer' => $data['reviewer'] ?: $this->userName(),
            'acceptBlank' => $request->boolean('accept_blank', true),
        ]);
    }

    public function apply(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'locale' => 'required|string|max:16',
            'token' => ['required', 'string', 'regex:/^[0-9a-f-]{36}\.(xlsx|csv)$/'],
            'reviewer' => 'required|string|max:255',
            'accept_blank' => 'nullable|boolean',
        ]);
        $locale = $this->locale($data['locale']);
        $file = storage_path('app/translation-review/'.$data['token']);
        if (! is_file($file)) {
            return redirect()->route('translation-review.index', ['locale' => $locale])->with('error', __('The uploaded workbook has expired. Upload it again.'));
        }
        $preview = $this->review->preview($locale, $file, $request->boolean('accept_blank', true));
        $done = $this->review->apply($locale, $preview['items'], $data['reviewer'], (int) Auth::id(), true, $this->writesLangFile());
        @unlink($file);

        return redirect()->route('translation-review.index', ['locale' => $locale])->with('success',
            __(':corrected corrected and :accepted accepted; :refused refused (placeholders do not match the English).', $done));
    }

    public function step(Request $request)
    {
        $this->guard();
        $locale = $this->locale($request->query('locale'));
        $queue = $this->review->queue($locale, $request->boolean('held'));
        $pos = max(0, min((int) $request->query('pos', 0), max(0, count($queue) - 1)));

        return view('ahg-core::translation-review.step', [
            'locale' => $locale,
            'item' => $queue[$pos] ?? null,
            'pos' => $pos,
            'total' => count($queue),
            'held' => $request->boolean('held'),
            'context' => isset($queue[$pos]) ? ($this->review->contextMap()[$queue[$pos]['key']] ?? '') : '',
            'keep' => isset($queue[$pos]) ? implode(' ', \AhgCore\Commands\TranslationLlmFillCommand::placeholders($queue[$pos]['key'])) : '',
        ]);
    }

    public function decide(Request $request)
    {
        $this->guard();
        $data = $request->validate([
            'locale' => 'required|string|max:16',
            'key' => 'required|string|max:5000',
            'decision' => 'required|in:accept,correct',
            'correction' => 'nullable|string|max:5000',
            'pos' => 'nullable|integer|min:0',
            'held' => 'nullable|boolean',
        ]);
        $locale = $this->locale($data['locale']);
        $correction = $data['decision'] === 'correct' ? (string) ($data['correction'] ?? '') : null;
        $back = ['locale' => $locale, 'pos' => (int) ($data['pos'] ?? 0), 'held' => $request->boolean('held') ? 1 : null];

        try {
            $done = $this->review->decide($locale, $data['key'], $correction, $this->userName(), (int) Auth::id(), $this->writesLangFile());
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('translation-review.step', $back)->with('error', $e->getMessage());
        }
        if ($done['refused'] > 0) {
            return redirect()->route('translation-review.step', $back)->withInput()->with('error',
                __('Not saved: the placeholders must match the English (:problem).', ['problem' => UiStringReviewService::placeholderProblem($data['key'], (string) ($correction ?: ''))]));
        }

        // The decided string leaves the queue, so the same position is the next string.
        return redirect()->route('translation-review.step', $back)->with('success', __('Saved.'));
    }

    /**
     * Production is a git checkout updated by pull, so a web request there
     * writes ui_string only; a dirty lang file would block the next pull. The
     * reviewed strings reach git by exporting with --all on production and
     * importing that workbook on dev with --target=file.
     */
    private function writesLangFile(): bool
    {
        return ! app()->isProduction();
    }

    private function guard(): void
    {
        $user = Auth::user();
        abort_unless(AclService::isAdministrator($user) || AclService::isTranslator($user), 403);
    }

    private function locales(): array
    {
        $all = $this->review->locales();
        $sa = array_values(array_intersect(self::SA_LOCALES, $all));

        return array_merge($sa, array_values(array_diff($all, $sa)));
    }

    private function locale(?string $locale): string
    {
        $locale = (string) $locale;
        abort_unless(in_array($locale, $this->review->locales(), true), 404);

        return $locale;
    }

    private function userName(): string
    {
        $u = Auth::user();

        return (string) ($u->username ?? $u->email ?? 'reviewer');
    }

    /** @return array<string, string> status code => label, from the Dropdown Manager */
    private function statusLabels(): array
    {
        try {
            return DB::table('ahg_dropdown')->where('taxonomy', 'ui_string_review_status')->where('is_active', 1)
                ->orderBy('sort_order')->pluck('label', 'code')->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
