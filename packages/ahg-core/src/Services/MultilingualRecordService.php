<?php

/**
 * MultilingualRecordService - Heratio ahg-core
 *
 * heratio#1211 north-star, first public slice: "every museum for everyone -
 * universal multilingual access". Lets any visitor read a catalogue record's
 * key metadata translated into their own language, ON DEMAND, for DISPLAY ONLY.
 *
 * Hard rules baked into this service:
 *   - Translation routes ONLY through the sanctioned AHG AI gateway client
 *     (AhgAiServices\Services\LlmService::translate), never a direct node port.
 *   - Translations are NEVER written back into the catalogue / dropdowns /
 *     information_object_i18n. We translate on the fly and cache the result in
 *     the application cache (keyed on a hash of the source text) so repeat
 *     views are free and the gateway is not hammered.
 *   - The original text is always returned alongside the translation and is
 *     always authoritative. Machine-translation output is clearly labelled by
 *     the caller; quality varies by language (some SA languages are weak on the
 *     current MT) so the original stays the source of truth.
 *   - Publication status is respected: unpublished records are never exposed or
 *     translated for anonymous visitors.
 *   - Degrades gracefully: on ANY failure (gateway unreachable, MT disabled,
 *     translation empty) the ORIGINAL text is returned with translated=false.
 *     The service never throws and never blocks the page.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * Licensed under the GNU Affero General Public License v3 or later.
 */

namespace AhgCore\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MultilingualRecordService
{
    /**
     * Publication-status sentinels (canonical Heratio values).
     * status.type_id = 158 is the publication-status row; status_id = 160 = Published.
     */
    private const PUBLICATION_STATUS_TYPE_ID = 158;
    private const PUBLICATION_STATUS_PUBLISHED = 160;

    /** Cache TTL for a translated field: 30 days (seconds). */
    private const CACHE_TTL = 60 * 60 * 24 * 30;

    /**
     * The key i18n metadata fields we expose + translate, in display order.
     * label => information_object_i18n column. Deliberately a SAFE subset of
     * ISAD(G) descriptive prose - no identifiers, no admin/control fields.
     *
     * @var array<string,string>
     */
    private const FIELDS = [
        'Title'                    => 'title',
        'Scope and content'        => 'scope_and_content',
        'Archival history'         => 'archival_history',
        'Extent and medium'        => 'extent_and_medium',
        'Arrangement'              => 'arrangement',
        'Access conditions'        => 'access_conditions',
        'Physical characteristics' => 'physical_characteristics',
    ];

    /**
     * Built-in display labels for the offered target languages. Mirrors the
     * theme culture-switcher map so the public picker reads naturally. Codes
     * must match the i18n_languages 'name' values / lang/*.json filenames.
     *
     * @var array<string,string>
     */
    private const LANGUAGE_LABELS = [
        'en'  => 'English',
        'af'  => 'Afrikaans',
        'fr'  => 'Français',
        'nl'  => 'Nederlands',
        'pt'  => 'Português',
        'es'  => 'Español',
        'de'  => 'Deutsch',
        'it'  => 'Italiano',
        'zu'  => 'isiZulu',
        'xh'  => 'isiXhosa',
        'st'  => 'Sesotho',
        'tn'  => 'Setswana',
        'nso' => 'Sepedi',
        'ts'  => 'Xitsonga',
        'ss'  => 'siSwati',
        've'  => 'Tshivenda',
        'nr'  => 'isiNdebele',
        'sn'  => 'chiShona',
        'sw'  => 'Kiswahili',
        'ar'  => 'العربية',
        'zh'  => '中文',
        'ja'  => '日本語',
        'ru'  => 'Русский',
    ];

    /** A sensible jurisdiction-neutral default offering when nothing is configured. */
    private const DEFAULT_LANGUAGES = ['en', 'fr', 'es', 'pt', 'de', 'nl', 'af', 'zu', 'sw', 'ar', 'zh'];

    /**
     * Resolve a record by numeric id or slug, returning its id + culture +
     * publication state, or null when it does not exist. Does NOT apply the
     * publication gate (callers decide, since an authenticated editor may
     * legitimately preview a draft).
     *
     * @return array{id:int,culture:string,published:bool}|null
     */
    public function resolve(string $idOrSlug): ?array
    {
        try {
            $id = null;
            if (ctype_digit($idOrSlug)) {
                $id = (int) $idOrSlug;
                if (! DB::table('information_object')->where('id', $id)->exists()) {
                    $id = null;
                }
            }
            if ($id === null) {
                $id = (int) (DB::table('slug')->where('slug', $idOrSlug)->value('object_id') ?? 0);
            }
            if ($id <= 0) {
                return null;
            }

            return [
                'id'        => $id,
                'culture'   => $this->sourceCulture($id),
                'published' => $this->isPublished($id),
            ];
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] multilingual resolve failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Read the record's key i18n metadata in its OWN culture. Returns the
     * ordered list of populated fields:
     *   [ ['key'=>'scope_and_content','label'=>'Scope and content','original'=>'...'], ... ]
     *
     * Empty fields are dropped. Never throws.
     *
     * @return array<int,array{key:string,label:string,original:string}>
     */
    public function fields(int $objectId): array
    {
        try {
            $culture = $this->sourceCulture($objectId);
            $row = DB::table('information_object_i18n')
                ->where('id', $objectId)
                ->where('culture', $culture)
                ->first();

            // Fall back to any available culture row if the resolved one is absent.
            if ($row === null) {
                $row = DB::table('information_object_i18n')->where('id', $objectId)->first();
            }
            if ($row === null) {
                return [];
            }

            $out = [];
            foreach (self::FIELDS as $label => $col) {
                $val = isset($row->{$col}) ? trim((string) $row->{$col}) : '';
                if ($val === '') {
                    continue;
                }
                $out[] = ['key' => $col, 'label' => $label, 'original' => $val];
            }

            return $out;
        } catch (\Throwable $e) {
            Log::warning('[ahg-core] multilingual fields read failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Translate the record's key metadata into $lang for DISPLAY ONLY.
     *
     * Returns a well-formed array ALWAYS (never throws):
     *   [
     *     'lang'          => 'fr',
     *     'language'      => 'Français',
     *     'source'        => 'machine-translation',
     *     'provider'      => 'ahg-gateway',       // or 'original' on full fallback
     *     'authoritative' => 'original',
     *     'fields'        => [
     *        ['key'=>..., 'label'=>..., 'original'=>..., 'translated'=>..., 'is_translated'=>bool],
     *        ...
     *     ],
     *   ]
     *
     * Each field is translated via the sanctioned gateway client and cached on
     * (object, lang, source-text-hash). On any per-field failure the ORIGINAL
     * text is returned for that field with is_translated=false. If the whole
     * record is unpublished and the viewer is anonymous, an empty field set is
     * returned (no leak).
     */
    public function translate(int $objectId, string $lang): array
    {
        $lang = $this->normaliseLang($lang);
        $label = self::LANGUAGE_LABELS[$lang] ?? strtoupper($lang);

        $base = [
            'lang'          => $lang,
            'language'      => $label,
            'source'        => 'machine-translation',
            'provider'      => 'ahg-gateway',
            'authoritative' => 'original',
            'fields'        => [],
        ];

        // Publication gate - never translate/expose a draft to the public.
        if (! $this->isPublished($objectId) && ! Auth::check()) {
            return $base;
        }

        $fields = $this->fields($objectId);
        if (empty($fields)) {
            return $base;
        }

        $sourceCulture = $this->sourceCulture($objectId);
        $anyTranslated = false;
        $out = [];
        foreach ($fields as $f) {
            $original = $f['original'];

            // Same-language content: show original, no gateway call.
            if ($lang === $sourceCulture || mb_strlen($original) === 0) {
                $out[] = $this->fieldRow($f, $original, false);
                continue;
            }

            $translated = $this->translateField($objectId, $lang, $original);
            $isTranslated = ($translated !== null && trim($translated) !== '' && trim($translated) !== trim($original));
            if ($isTranslated) {
                $anyTranslated = true;
            }
            $out[] = $this->fieldRow($f, $isTranslated ? $translated : $original, $isTranslated);
        }

        $base['fields'] = $out;
        if (! $anyTranslated) {
            // Full graceful degradation: nothing came back translated (gateway
            // unreachable, MT disabled, same language). The original is served.
            $base['provider'] = 'original';
        }

        return $base;
    }

    /**
     * Translate ONE field through the sanctioned AHG AI gateway client, cached
     * on (object, lang, source-text-hash). Returns null on any failure so the
     * caller falls back to the original. Never throws.
     */
    private function translateField(int $objectId, string $lang, string $text): ?string
    {
        $hash = substr(sha1($text), 0, 16);
        $cacheKey = "ahgcore:mlr:tr:{$objectId}:{$lang}:{$hash}";

        try {
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($lang, $text) {
                // SANCTIONED PATH: AhgAiServices\Services\LlmService::translate()
                // routes through the AHG AI gateway (https://ai.theahg.co.za/ai/v1)
                // - its cloud-mode + mt_endpoint dispatch both target the gateway,
                // never a GPU node port. We resolve it from the container so the
                // hard dependency stays soft (returns original if the package is
                // absent on a slim install).
                if (! class_exists(\AhgAiServices\Services\LlmService::class)) {
                    return null;
                }
                /** @var \AhgAiServices\Services\LlmService $llm */
                $llm = app(\AhgAiServices\Services\LlmService::class);
                $result = $llm->translate($text, $lang);

                return is_string($result) && trim($result) !== '' ? $result : null;
            });
        } catch (\Throwable $e) {
            // Quota exceeded, gateway down, package not registered, cache hiccup -
            // all degrade to the original. Forget any poisoned cache entry.
            Log::info('[ahg-core] multilingual translate field fell back to original: ' . $e->getMessage());
            try {
                Cache::forget($cacheKey);
            } catch (\Throwable) {
                // ignore
            }

            return null;
        }
    }

    /**
     * The offered target languages: [ ['code'=>'fr','label'=>'Français'], ... ].
     *
     * Derived (in priority order) from:
     *   1. enabled `setting` rows scope=i18n_languages (the operator's own list,
     *      same source the theme culture-switcher + SetLocale middleware use), then
     *   2. lang/*.json files on disk, then
     *   3. a sensible jurisdiction-neutral default set.
     *
     * The record's own source culture is always included so the visitor can
     * toggle back to the authoritative original.
     *
     * @return array<int,array{code:string,label:string}>
     */
    public function languages(?string $sourceCulture = null): array
    {
        $codes = [];

        // 1. Operator-enabled UI languages.
        try {
            if (Schema::hasTable('setting')) {
                $codes = DB::table('setting')
                    ->where('scope', 'i18n_languages')
                    ->where('editable', 1)
                    ->pluck('name')
                    ->map(fn ($c) => $this->normaliseLang((string) $c))
                    ->filter()
                    ->all();
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // 2. lang/*.json fallback.
        if (empty($codes)) {
            foreach (glob(base_path('lang/*.json')) ?: [] as $path) {
                $name = pathinfo($path, PATHINFO_FILENAME);
                if ($name !== '' && $name[0] !== '_' && $name[0] !== '.') {
                    $codes[] = $this->normaliseLang($name);
                }
            }
        }

        // 3. Hard default.
        if (empty($codes)) {
            $codes = self::DEFAULT_LANGUAGES;
        }

        // Always offer the authoritative source culture too.
        if ($sourceCulture) {
            $codes[] = $this->normaliseLang($sourceCulture);
        }

        $codes = array_values(array_unique(array_filter($codes)));

        $out = [];
        foreach ($codes as $code) {
            $out[] = ['code' => $code, 'label' => self::LANGUAGE_LABELS[$code] ?? strtoupper($code)];
        }

        return $out;
    }

    /**
     * Is this record published? (status.type_id=158, status_id=160). Errors
     * resolve to "not published" so a DB hiccup never leaks a draft.
     */
    public function isPublished(int $objectId): bool
    {
        try {
            return DB::table('status')
                ->where('object_id', $objectId)
                ->where('type_id', self::PUBLICATION_STATUS_TYPE_ID)
                ->where('status_id', self::PUBLICATION_STATUS_PUBLISHED)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * The record's own primary culture (the language the catalogue text is in).
     * Prefers the source_culture column on information_object, falling back to
     * the app default locale, then 'en'.
     */
    public function sourceCulture(int $objectId): string
    {
        try {
            $sc = DB::table('information_object')->where('id', $objectId)->value('source_culture');
            if (is_string($sc) && trim($sc) !== '') {
                return $this->normaliseLang($sc);
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return $this->normaliseLang((string) config('app.locale', 'en'));
    }

    /** Build one field row for the translate() contract. */
    private function fieldRow(array $f, string $display, bool $isTranslated): array
    {
        return [
            'key'           => $f['key'],
            'label'         => $f['label'],
            'original'      => $f['original'],
            'translated'    => $display,
            'is_translated' => $isTranslated,
        ];
    }

    /**
     * Normalise a culture/locale code to its lower-cased base subtag
     * (e.g. "fr_CA" / "fr-CA" -> "fr"), which is what the MT/LLM client and the
     * label map both key on. Leaves multi-letter SA codes (nso, khi) intact.
     */
    private function normaliseLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $base = preg_split('/[-_]/', $lang)[0] ?? $lang;

        return $base !== '' ? $base : $lang;
    }
}
