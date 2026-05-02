<?php

/**
 * TranslationController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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



namespace AhgTranslation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
    /**
     * class_name → [i18n_table, allowed columns] map for the save endpoint.
     *
     * The save flow looks up object.class_name to decide which *_i18n table
     * to write to, and only the listed columns are accepted as targets.
     */
    private const I18N_TABLE_BY_CLASS = [
        'QubitInformationObject' => [
            'table'   => 'information_object_i18n',
            'columns' => [
                'title', 'alternate_title', 'edition', 'extent_and_medium',
                'archival_history', 'acquisition', 'scope_and_content',
                'appraisal', 'accruals', 'arrangement', 'access_conditions',
                'reproduction_conditions', 'physical_characteristics',
                'finding_aids', 'location_of_originals', 'location_of_copies',
                'related_units_of_description', 'institution_responsible_identifier',
                'rules', 'sources', 'revision_history',
            ],
        ],
        'QubitActor' => [
            'table'   => 'actor_i18n',
            'columns' => [
                'authorized_form_of_name', 'dates_of_existence', 'history',
                'places', 'legal_status', 'functions', 'mandates',
                'internal_structures', 'general_context',
                'institution_responsible_identifier', 'rules', 'sources',
                'revision_history',
            ],
        ],
        'QubitRepository' => [
            'table'   => 'actor_i18n',
            'columns' => [
                'authorized_form_of_name', 'dates_of_existence', 'history',
                'places', 'legal_status', 'functions', 'mandates',
                'internal_structures', 'general_context',
                'institution_responsible_identifier', 'rules', 'sources',
                'revision_history',
            ],
        ],
    ];

    /**
     * Map UI field keys to DB columns in information_object_i18n.
     * Mirrors AhgTranslationRepository::allowedFields() from AtoM plugin.
     */
    private const ALLOWED_FIELDS = [
        'title' => 'title',
        'alternate_title' => 'alternate_title',
        'edition' => 'edition',
        'extent_and_medium' => 'extent_and_medium',
        'archival_history' => 'archival_history',
        'acquisition' => 'acquisition',
        'scope_and_content' => 'scope_and_content',
        'appraisal' => 'appraisal',
        'accruals' => 'accruals',
        'arrangement' => 'arrangement',
        'access_conditions' => 'access_conditions',
        'reproduction_conditions' => 'reproduction_conditions',
        'physical_characteristics' => 'physical_characteristics',
        'finding_aids' => 'finding_aids',
        'location_of_originals' => 'location_of_originals',
        'location_of_copies' => 'location_of_copies',
        'related_units_of_description' => 'related_units_of_description',
        'institution_responsible_identifier' => 'institution_responsible_identifier',
        'rules' => 'rules',
        'sources' => 'sources',
        'revision_history' => 'revision_history',
    ];

    /**
     * Max field lengths for translation output.
     */
    private const FIELD_MAX_LENGTHS = [
        'title' => 1024,
        'alternate_title' => 1024,
        'edition' => 255,
        'institution_responsible_identifier' => 1024,
    ];

    /**
     * Target languages with culture codes (all 11 SA official + international).
     */
    private const TARGET_LANGUAGES = [
        'en'  => 'English',
        'af'  => 'Afrikaans',
        'zu'  => 'isiZulu',
        'xh'  => 'isiXhosa',
        'st'  => 'Sesotho',
        'tn'  => 'Setswana',
        'nso' => 'Sepedi (Northern Sotho)',
        'ts'  => 'Xitsonga',
        'ss'  => 'SiSwati',
        've'  => 'Tshivenda',
        'nr'  => 'isiNdebele',
        'nl'  => 'Dutch',
        'fr'  => 'French',
        'de'  => 'German',
        'es'  => 'Spanish',
        'pt'  => 'Portuguese',
        'sw'  => 'Swahili',
        'ar'  => 'Arabic',
    ];

    /**
     * Translation settings page (MT endpoint, timeout, health check).
     */
    public function settings(Request $request)
    {
        if ($request->isMethod('POST')) {
            $endpoint = trim((string) $request->input('endpoint'));
            $timeout = trim((string) $request->input('timeout'));
            $apiKey = trim((string) $request->input('api_key'));

            if ($endpoint !== '') {
                $this->setSetting('mt.endpoint', $endpoint);
            }
            if ($timeout !== '' && ctype_digit($timeout)) {
                $this->setSetting('mt.timeout_seconds', $timeout);
            }
            if ($apiKey !== '') {
                $this->setSetting('mt.api_key', $apiKey);
            }

            return redirect()->route('ahgtranslation.settings')
                ->with('notice', 'Settings updated');
        }

        $endpoint = $this->getSetting('mt.endpoint', 'http://192.168.0.112:5004/ai/v1/translate');
        $timeout = $this->getSetting('mt.timeout_seconds', '60');
        $apiKey = $this->getSetting('mt.api_key', '');

        return view('ahg-translation::settings', [
            'endpoint' => $endpoint,
            'timeout' => $timeout,
            'apiKey' => $apiKey,
        ]);
    }

    /**
     * Show translation form for an information object in a specific culture.
     *
     * GET /admin/translation/translate/{slug}
     */
    public function translate(Request $request, string $slug)
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            abort(404, 'Record not found');
        }

        $culture = app()->getLocale();

        // Get the IO title
        $io = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->first();

        // Which cultures actually exist for this record
        $availableCultures = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->pluck('culture')
            ->toArray();

        // Translation settings from ahg_ner_settings / ahg_translation_settings
        $selectedFields = json_decode(
            $this->getSetting('translation_fields', '["title","scope_and_content"]'),
            true
        ) ?: ['title', 'scope_and_content'];
        $defaultTarget = $this->getSetting('translation_target_lang', 'af');
        $saveCultureDefault = $this->getSetting('translation_save_culture', '1') === '1';
        $overwriteDefault = $this->getSetting('translation_overwrite', '0') === '1';

        // All translatable fields
        $allFields = [
            'title' => 'Title',
            'alternate_title' => 'Alternate Title',
            'scope_and_content' => 'Scope and Content',
            'archival_history' => 'Archival History',
            'acquisition' => 'Acquisition',
            'arrangement' => 'Arrangement',
            'access_conditions' => 'Access Conditions',
            'reproduction_conditions' => 'Reproduction Conditions',
            'finding_aids' => 'Finding Aids',
            'related_units_of_description' => 'Related Units',
            'appraisal' => 'Appraisal',
            'accruals' => 'Accruals',
            'physical_characteristics' => 'Physical Characteristics',
            'location_of_originals' => 'Location of Originals',
            'location_of_copies' => 'Location of Copies',
            'extent_and_medium' => 'Extent and Medium',
            'sources' => 'Sources',
            'rules' => 'Rules',
            'revision_history' => 'Revision History',
        ];

        return view('ahg-translation::translate', [
            'objectId' => $objectId,
            'slug' => $slug,
            'title' => $io->title ?? 'Untitled',
            'culture' => $culture,
            'availableCultures' => $availableCultures,
            'targetLanguages' => self::TARGET_LANGUAGES,
            'allFields' => $allFields,
            'selectedFields' => $selectedFields,
            'defaultTarget' => $defaultTarget,
            'saveCultureDefault' => $saveCultureDefault,
            'overwriteDefault' => $overwriteDefault,
        ]);
    }

    /**
     * POST /admin/translation/translate/{slug}
     *
     * AJAX endpoint: translate a single field for an IO.
     * Returns JSON with draft_id, translation, source_text.
     */
    public function store(Request $request, string $slug)
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (!$objectId) {
            return response()->json(['ok' => false, 'error' => 'Record not found'], 404);
        }

        $fieldKey = (string) $request->input('field');
        $targetFieldKey = (string) $request->input('targetField', $fieldKey);
        $source = (string) $request->input('source', app()->getLocale());
        $target = (string) $request->input('target', $this->getSetting('mt.target_culture', 'en'));
        $readCulture = (string) $request->input('readCulture', '');
        $apply = (int) $request->input('apply', 0) === 1;
        $overwrite = (int) $request->input('overwrite', 0) === 1;
        $saveCulture = (int) $request->input('saveCulture', 1) === 1;

        $dbCulture = !empty($readCulture) ? $readCulture : $source;

        if (!isset(self::ALLOWED_FIELDS[$fieldKey])) {
            return response()->json(['ok' => false, 'error' => 'Unsupported source field: ' . $fieldKey]);
        }
        if (!isset(self::ALLOWED_FIELDS[$targetFieldKey])) {
            return response()->json(['ok' => false, 'error' => 'Unsupported target field: ' . $targetFieldKey]);
        }

        $sourceColumn = self::ALLOWED_FIELDS[$fieldKey];
        $targetColumn = self::ALLOWED_FIELDS[$targetFieldKey];

        // Read source text
        $sourceText = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $dbCulture)
            ->value($sourceColumn);

        if ($sourceText === null || trim($sourceText) === '') {
            return response()->json(['ok' => false, 'error' => 'No source text for this field/language']);
        }

        // Max length for target field
        $maxLength = self::FIELD_MAX_LENGTHS[$targetColumn] ?? 65535;

        // Call the MT service
        $result = $this->translateText($sourceText, $source, $target, $maxLength);

        // Log attempt
        $this->logAttempt($objectId, $fieldKey, $source, $target, $result);

        if (empty($result['ok'])) {
            return response()->json([
                'ok' => false,
                'error' => $result['error'] ?? 'Translation failed',
                'http_status' => $result['http_status'] ?? null,
            ]);
        }

        $translated = $result['translation'];
        $userId = auth()->id();

        // Create draft
        $draft = $this->createDraft($objectId, $targetFieldKey, $source, $target, $sourceText, $translated, $userId);
        if (empty($draft['ok'])) {
            return response()->json(['ok' => false, 'error' => $draft['error'] ?? 'Failed to create draft']);
        }

        $resp = [
            'ok' => true,
            'draft_id' => $draft['draft_id'],
            'deduped' => $draft['deduped'] ?? false,
            'translation' => $translated,
            'source_text' => $sourceText,
            'source_field' => $fieldKey,
            'target_field' => $targetFieldKey,
        ];

        if ($apply) {
            $targetCulture = $saveCulture ? $target : $source;
            $applied = $this->applyDraft((int) $draft['draft_id'], $overwrite, $targetCulture);
            $resp['apply_ok'] = !empty($applied['ok']);
            $resp['saved_culture'] = $targetCulture;
            if (empty($applied['ok'])) {
                $resp['apply_error'] = $applied['error'] ?? 'Apply failed';
            }
        }

        return response()->json($resp);
    }

    /**
     * POST /admin/translation/apply
     *
     * Apply a translation draft (with optional edited text).
     */
    public function apply(Request $request)
    {
        $draftId = (int) $request->input('draftId');
        $overwrite = (int) $request->input('overwrite', 0) === 1;
        $saveCulture = (int) $request->input('saveCulture', 1) === 1;
        $targetCulture = (string) $request->input('targetCulture', '');
        $editedText = $request->input('editedText');

        if ($editedText !== null && $editedText !== '') {
            DB::table('ahg_translation_draft')
                ->where('id', $draftId)
                ->where('status', 'draft')
                ->update(['translated_text' => $editedText]);
        }

        if ($saveCulture && $targetCulture !== '') {
            $result = $this->applyDraft($draftId, $overwrite, $targetCulture);
        } else {
            $result = $this->applyDraft($draftId, $overwrite);
        }

        return response()->json($result);
    }

    /**
     * POST /admin/translation/save
     *
     * Persist a single field translation directly into the entity's *_i18n
     * table. This is the simple "I edited this and want it saved" endpoint
     * that the per-field Save button on the Translate modal posts to.
     *
     * Inputs:
     *   - object_id: int (required)        — IO/Actor/Repository ID
     *   - culture:   string (required)     — target culture code (e.g. 'af')
     *   - field:     string (required)     — i18n column name
     *   - value:     string (required)     — translated text
     *   - confirmed: bool (optional)       — true if a human reviewed it
     *
     * Provenance is recorded in ahg_translation_log with source='ai' (when
     * confirmed=false) or 'human' (when confirmed=true), plus created_by_user_id.
     */
    public function save(Request $request)
    {
        $objectId  = (int) $request->input('object_id');
        $culture   = trim((string) $request->input('culture'));
        $field     = trim((string) $request->input('field'));
        $value     = (string) $request->input('value', '');
        $confirmed = (bool) $request->input('confirmed', false);
        $reqReview = (bool) $request->input('review', false);

        if ($objectId <= 0 || $culture === '' || $field === '') {
            return response()->json([
                'ok' => false,
                'error' => 'object_id, culture, and field are required',
            ], 422);
        }

        // Look up the class_name to know which *_i18n table to write to.
        $className = DB::table('object')->where('id', $objectId)->value('class_name');
        if (!$className) {
            return response()->json(['ok' => false, 'error' => 'Object not found'], 404);
        }

        if (!isset(self::I18N_TABLE_BY_CLASS[$className])) {
            return response()->json([
                'ok' => false,
                'error' => "Unsupported entity class: {$className}",
            ], 422);
        }

        $cfg = self::I18N_TABLE_BY_CLASS[$className];
        $i18nTable = $cfg['table'];
        $allowed   = $cfg['columns'];

        if (!in_array($field, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'error' => "Field '{$field}' is not translatable for {$className}",
            ], 422);
        }

        // ── Workflow split (issue #54-style, applied to per-record translation) ──
        // Admin (default)         → write directly to *_i18n
        // Admin with ?review=1    → queue as ahg_translation_draft for second reviewer
        // Editor / translator     → always queue as ahg_translation_draft
        // Anyone else             → 403 (the acl:translate middleware should already
        //                            block; this is defence-in-depth)
        $isAdmin   = \AhgCore\Services\AclService::isAdministrator();
        $canTranslate = \AhgCore\Services\AclService::check(null, 'translate')
            || $isAdmin
            || \AhgCore\Services\AclService::isEditor()
            || \AhgCore\Services\AclService::isTranslator();
        if (!$canTranslate) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $autoApprove = $isAdmin && !$reqReview;

        if (!$autoApprove) {
            // Read source for the draft row context (best-effort — old value goes
            // in source_text so the reviewer can compare).
            $oldValue = DB::table($i18nTable)
                ->where('id', $objectId)
                ->where('culture', $culture)
                ->value($field);
            $draftId = DB::table('ahg_translation_draft')->insertGetId([
                'object_id'           => $objectId,
                'field_name'          => $field,
                'source_culture'      => $culture,
                'target_culture'      => $culture,
                'source_text'         => (string) ($oldValue ?? ''),
                'translated_text'     => $value,
                'status'              => 'draft',
                'created_by_user_id'  => auth()->id(),
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
            return response()->json([
                'ok'         => true,
                'state'      => 'pending',
                'draft_id'   => $draftId,
                'class'      => $className,
                'object_id'  => $objectId,
                'culture'    => $culture,
                'field'      => $field,
                'message'    => 'Submitted for review.',
            ]);
        }

        // Upsert the i18n row for the requested culture.
        $exists = DB::table($i18nTable)
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->exists();

        if ($exists) {
            DB::table($i18nTable)
                ->where('id', $objectId)
                ->where('culture', $culture)
                ->update([$field => $value]);
        } else {
            DB::table($i18nTable)->insert([
                'id'      => $objectId,
                'culture' => $culture,
                $field    => $value,
            ]);
        }

        // Log provenance: who saved it, machine vs human, the saved value.
        try {
            DB::table('ahg_translation_log')->insert([
                'object_id'           => $objectId,
                'field_name'          => $field,
                'source_culture'      => null,
                'target_culture'      => $culture,
                'endpoint'            => null,
                'http_status'         => null,
                'ok'                  => 1,
                'error'               => null,
                'value'               => $value,
                'source'              => $confirmed ? 'human' : 'ai',
                'created_by_user_id'  => auth()->id(),
                'confirmed'           => $confirmed ? 1 : 0,
                'elapsed_ms'          => null,
                'created_at'          => now(),
            ]);
        } catch (\Throwable $e) {
            // Provenance logging is best-effort; never block the save.
        }

        return response()->json([
            'ok'        => true,
            'state'     => 'approved',
            'object_id' => $objectId,
            'culture'   => $culture,
            'field'     => $field,
            'source'    => $confirmed ? 'human' : 'ai',
            'class'     => $className,
            'table'     => $i18nTable,
        ]);
    }

    /**
     * GET /admin/translation/health
     *
     * Health check for MT endpoint.
     */
    public function health()
    {
        $endpoint = $this->getSetting('mt.endpoint', 'http://192.168.0.112:5004/ai/v1/translate');
        $apiKey = $this->getSetting('mt.api_key', '<set in ahg_settings>');

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode(['source' => 'af', 'target' => 'en', 'text' => 'toets']),
            CURLOPT_TIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = ($errno === 0) && ($status >= 200 && $status < 500);

        return response()->json([
            'ok' => $ok,
            'endpoint' => $endpoint,
            'http_status' => $status,
            'curl_error' => $errno ? ($errno . ': ' . $err) : null,
        ]);
    }

    /**
     * GET /admin/translation/languages
     *
     * List available languages from setting table.
     */
    public function languages()
    {
        // AtoM stores setting values on `setting_i18n`, keyed by setting.id + culture.
        $culture = app()->getLocale();
        $cultures = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                $j->on('si.id', '=', 's.id')->where('si.culture', '=', $culture);
            })
            ->where('s.name', 'i18n_languages')
            ->value('si.value');

        $defaultCulture = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                $j->on('si.id', '=', 's.id')->where('si.culture', '=', $culture);
            })
            ->where('s.name', 'default_culture')
            ->value('si.value') ?? 'en';

        $enabledCultures = $cultures ? json_decode($cultures, true) : ['en'];

        // Build language list with names
        $languageList = [];
        foreach (self::TARGET_LANGUAGES as $code => $name) {
            $languageList[] = [
                'code' => $code,
                'name' => $name,
                'enabled' => in_array($code, $enabledCultures),
                'default' => $code === $defaultCulture,
            ];
        }

        return view('ahg-translation::languages', [
            'languages' => $languageList,
            'enabledCultures' => $enabledCultures,
            'defaultCulture' => $defaultCulture,
        ]);
    }

    /**
     * POST /admin/translation/languages
     *
     * Add/update a language in the system i18n_languages setting.
     */
    public function addLanguage(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:8',
        ]);

        $code = trim($request->input('code'));

        // AtoM setting values live on `setting_i18n`, keyed by setting.id + culture.
        $culture = app()->getLocale();
        $row = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                $j->on('si.id', '=', 's.id')->where('si.culture', '=', $culture);
            })
            ->where('s.name', 'i18n_languages')
            ->select('s.id', 'si.value')
            ->first();
        $cultures = ($row && $row->value) ? json_decode($row->value, true) : ['en'];

        if (!in_array($code, $cultures)) {
            $cultures[] = $code;
            $json = json_encode($cultures);

            if ($row) {
                DB::table('setting_i18n')->updateOrInsert(
                    ['id' => $row->id, 'culture' => $culture],
                    ['value' => $json]
                );
            } else {
                $settingId = DB::table('setting')->insertGetId([
                    'name'           => 'i18n_languages',
                    'scope'          => 'global',
                    'source_culture' => $culture,
                ]);
                DB::table('setting_i18n')->insert([
                    'id'      => $settingId,
                    'culture' => $culture,
                    'value'   => $json,
                ]);
            }
        }

        return redirect()->route('ahgtranslation.languages')
            ->with('notice', 'Language "' . e($code) . '" added.');
    }

    // ── Private helpers (migrated from AhgTranslationService) ────────

    /**
     * Call the NLLB-200 translation API.
     */
    private function translateText(string $text, string $sourceCulture, string $targetCulture, ?int $maxLength = null): array
    {
        $endpoint = $this->getSetting('mt.endpoint', 'http://192.168.0.112:5004/ai/v1/translate');
        $apiKey = $this->getSetting('mt.api_key', '<set in ahg_settings>');
        $timeout = (int) $this->getSetting('mt.timeout_seconds', '60');

        $payloadData = [
            'text' => $text,
            'source' => $sourceCulture,
            'target' => $targetCulture,
        ];
        if ($maxLength !== null) {
            $payloadData['max_length'] = $maxLength;
        }
        $payload = json_encode($payloadData);

        $t0 = microtime(true);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-API-Key: ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $errstr = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);

        if ($errno !== 0) {
            return [
                'ok' => false,
                'translation' => null,
                'http_status' => $status ?: null,
                'error' => 'cURL error ' . $errno . ': ' . $errstr,
                'elapsed_ms' => $elapsedMs,
                'endpoint' => $endpoint,
            ];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return [
                'ok' => false,
                'translation' => null,
                'http_status' => $status,
                'error' => 'Invalid JSON from MT endpoint',
                'elapsed_ms' => $elapsedMs,
                'endpoint' => $endpoint,
            ];
        }

        $translation = $data['translated'] ?? $data['translatedText'] ?? $data['translation'] ?? null;

        if ($status < 200 || $status >= 300 || !is_string($translation)) {
            return [
                'ok' => false,
                'translation' => null,
                'http_status' => $status,
                'error' => $data['detail'] ?? $data['error'] ?? 'MT endpoint returned non-2xx or missing translation',
                'elapsed_ms' => $elapsedMs,
                'endpoint' => $endpoint,
            ];
        }

        return [
            'ok' => true,
            'translation' => $translation,
            'http_status' => $status,
            'error' => null,
            'elapsed_ms' => $elapsedMs,
            'endpoint' => $endpoint,
            'model' => $data['model'] ?? 'nllb-200',
        ];
    }

    /**
     * Create a translation draft record.
     */
    private function createDraft(int $objectId, string $fieldName, string $sourceCulture, string $targetCulture, string $sourceText, string $translatedText, ?int $userId = null): array
    {
        $sourceHash = hash('sha256', $sourceText);

        try {
            DB::table('ahg_translation_draft')->insert([
                'object_id' => $objectId,
                'entity_type' => 'information_object',
                'field_name' => $fieldName,
                'source_culture' => $sourceCulture,
                'target_culture' => $targetCulture,
                'source_hash' => $sourceHash,
                'source_text' => $sourceText,
                'translated_text' => $translatedText,
                'status' => 'draft',
                'created_by_user_id' => $userId,
                'created_at' => now(),
            ]);
            return ['ok' => true, 'draft_id' => (int) DB::getPdo()->lastInsertId()];
        } catch (\Exception $e) {
            // Find existing (dedupe)
            $row = DB::table('ahg_translation_draft')
                ->where('object_id', $objectId)
                ->where('field_name', $fieldName)
                ->where('source_culture', $sourceCulture)
                ->where('target_culture', $targetCulture)
                ->where('source_hash', $sourceHash)
                ->first();

            if ($row) {
                return ['ok' => true, 'draft_id' => (int) $row->id, 'deduped' => true];
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply a translation draft to information_object_i18n.
     */
    private function applyDraft(int $draftId, bool $overwrite = false, ?string $targetCulture = null): array
    {
        $draft = DB::table('ahg_translation_draft')->where('id', $draftId)->first();
        if (!$draft) {
            return ['ok' => false, 'error' => 'Draft not found'];
        }
        if ($draft->status !== 'draft') {
            return ['ok' => false, 'error' => 'Draft not in draft state'];
        }

        if (!isset(self::ALLOWED_FIELDS[$draft->field_name])) {
            return ['ok' => false, 'error' => 'Field not allowed'];
        }

        $column = self::ALLOWED_FIELDS[$draft->field_name];
        $objectId = (int) $draft->object_id;
        $culture = $targetCulture !== null ? $targetCulture : $draft->target_culture;
        $text = $draft->translated_text;

        // Ensure i18n row exists
        $exists = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->exists();

        if (!$exists) {
            DB::table('information_object_i18n')->insert([
                'id' => $objectId,
                'culture' => $culture,
            ]);
        }

        // Check existing value
        $current = DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->value($column);

        if (!$overwrite && $current !== null && trim($current) !== '') {
            return ['ok' => false, 'error' => 'Target field not empty; use overwrite=1 to replace'];
        }

        DB::table('information_object_i18n')
            ->where('id', $objectId)
            ->where('culture', $culture)
            ->update([$column => $text]);

        DB::table('ahg_translation_draft')
            ->where('id', $draftId)
            ->update(['status' => 'applied', 'applied_at' => now()]);

        return ['ok' => true, 'culture' => $culture];
    }

    /**
     * Log a translation attempt.
     */
    private function logAttempt(?int $objectId, ?string $field, ?string $src, ?string $tgt, array $result): void
    {
        try {
            DB::table('ahg_translation_log')->insert([
                'object_id' => $objectId,
                'field_name' => $field,
                'source_culture' => $src,
                'target_culture' => $tgt,
                'endpoint' => $result['endpoint'] ?? null,
                'http_status' => $result['http_status'] ?? null,
                'ok' => !empty($result['ok']) ? 1 : 0,
                'error' => $result['error'] ?? null,
                'elapsed_ms' => $result['elapsed_ms'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Ignore log errors
        }
    }

    /**
     * Get a translation setting.
     */
    private function getSetting(string $key, $default = null)
    {
        try {
            $row = DB::table('ahg_translation_settings')
                ->where('setting_key', $key)
                ->value('setting_value');
            return $row !== null ? $row : $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Set a translation setting (upsert).
     */
    private function setSetting(string $key, string $value): void
    {
        try {
            DB::statement(
                "INSERT INTO ahg_translation_settings (setting_key, setting_value)
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key, $value]
            );
        } catch (\Exception $e) {
            // Table may not exist yet
        }
    }

    /**
     * GET /admin/translation/drafts — list pending MT drafts with filters.
     */
    public function drafts(Request $request)
    {
        $status = (string) $request->query('status', 'draft');
        $cultureFilter = (string) $request->query('target_culture', '');
        $objectFilter = (int) $request->query('object_id', 0);
        $perPage = max(10, min(200, (int) $request->query('per_page', 50)));

        $q = DB::table('ahg_translation_draft as d')
            ->leftJoin('object as o', 'o.id', '=', 'd.object_id')
            ->leftJoin('slug as s', 's.object_id', '=', 'd.object_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.created_by_user_id')
            ->select([
                'd.*',
                's.slug',
                'o.class_name',
                'u.email as created_by_email',
            ])
            ->orderByDesc('d.created_at');

        if ($status !== 'all' && in_array($status, ['draft', 'applied', 'rejected'], true)) {
            $q->where('d.status', $status);
        }
        if ($cultureFilter !== '') {
            $q->where('d.target_culture', $cultureFilter);
        }
        if ($objectFilter > 0) {
            $q->where('d.object_id', $objectFilter);
        }

        $total = (clone $q)->count();
        $drafts = $q->limit($perPage)->get();

        $cultures = DB::table('ahg_translation_draft')
            ->select('target_culture')
            ->groupBy('target_culture')
            ->pluck('target_culture')
            ->toArray();

        return view('ahg-translation::drafts', [
            'drafts' => $drafts,
            'total' => $total,
            'status' => $status,
            'cultureFilter' => $cultureFilter,
            'objectFilter' => $objectFilter,
            'perPage' => $perPage,
            'cultures' => $cultures,
        ]);
    }

    /**
     * POST /admin/translation/drafts/{id}/approve — apply a single draft to its target *_i18n row.
     */
    public function draftApprove(Request $request, int $id)
    {
        $draft = DB::table('ahg_translation_draft')->where('id', $id)->first();
        if (!$draft) {
            return back()->with('error', 'Draft not found');
        }
        if ($draft->status === 'applied') {
            return back()->with('notice', 'Draft already applied');
        }

        // Orphan check — the underlying object may have been deleted between
        // draft submission and approval. Saving would fail with "Object not
        // found" (or trip the FK on information_object_i18n.id). Mark the
        // draft as rejected with a clear note so it stops cluttering the queue.
        $stillExists = DB::table('object')->where('id', $draft->object_id)->exists();
        if (!$stillExists) {
            DB::table('ahg_translation_draft')
                ->where('id', $id)
                ->update([
                    'status'     => 'rejected',
                    'updated_at' => now(),
                ]);
            return back()->with('notice',
                "Draft #{$id} discarded — original record (object #{$draft->object_id}) was deleted before approval.");
        }

        // Re-use save() logic by invoking it with the draft's payload.
        $req = new Request([
            'object_id' => $draft->object_id,
            'culture'   => $draft->target_culture,
            'field'     => $draft->field_name,
            'value'     => $draft->translated_text,
            'confirmed' => 1,
        ]);
        $resp = $this->save($req);
        $payload = $resp instanceof \Illuminate\Http\JsonResponse ? $resp->getData(true) : [];

        if (($payload['ok'] ?? false) === true) {
            DB::table('ahg_translation_draft')
                ->where('id', $id)
                ->update(['status' => 'applied', 'applied_at' => now()]);
            return back()->with('success', "Draft #{$id} applied to {$draft->target_culture}.");
        }
        return back()->with('error', 'Apply failed: ' . ($payload['error'] ?? 'unknown'));
    }

    /**
     * POST /admin/translation/drafts/cleanup-orphans — bulk-mark every pending
     * draft whose underlying object has been deleted as 'rejected'.
     */
    public function draftCleanupOrphans(Request $request)
    {
        if (!\AhgCore\Services\AclService::isAdministrator()) {
            return back()->with('error', 'Admin only');
        }
        $orphanIds = DB::table('ahg_translation_draft as d')
            ->leftJoin('object as o', 'o.id', '=', 'd.object_id')
            ->whereNull('o.id')
            ->where('d.status', 'draft')
            ->pluck('d.id')
            ->all();
        if (empty($orphanIds)) {
            return back()->with('notice', 'No orphaned drafts to clean up.');
        }
        DB::table('ahg_translation_draft')->whereIn('id', $orphanIds)
            ->update(['status' => 'rejected', 'updated_at' => now()]);
        return back()->with('success', count($orphanIds) . ' orphaned draft(s) discarded.');
    }

    /**
     * POST /admin/translation/drafts/{id}/reject — mark draft as rejected (does not apply).
     */
    public function draftReject(Request $request, int $id)
    {
        $updated = DB::table('ahg_translation_draft')
            ->where('id', $id)
            ->whereIn('status', ['draft', 'applied'])
            ->update(['status' => 'rejected']);
        return back()->with($updated ? 'success' : 'error',
            $updated ? "Draft #{$id} rejected." : 'Draft not found or already rejected.');
    }

    /**
     * POST /admin/translation/drafts/batch — bulk approve/reject by ID list.
     */
    public function draftBatch(Request $request)
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $action = (string) $request->input('action');
        if (empty($ids) || ! in_array($action, ['approve', 'reject'], true)) {
            return back()->with('error', 'No drafts selected or invalid action.');
        }

        $ok = 0;
        $fail = 0;
        foreach ($ids as $id) {
            $sub = $action === 'approve'
                ? $this->draftApprove($request, $id)
                : $this->draftReject($request, $id);
            // The sub-call returns redirect responses; treat anything non-error as success.
            // Use session flash to count outcomes.
            if (session()->has('error')) {
                $fail++;
                session()->forget('error');
            } else {
                $ok++;
                session()->forget('success');
                session()->forget('notice');
            }
        }
        return back()->with('success', "Batch {$action}: {$ok} ok, {$fail} failed.");
    }

    // ─── UI string editor (issue #54 MVP) ──────────────────────────────────

    /**
     * GET /admin/translation/strings — list every key in lang/en.json with
     * a column per enabled locale, plus search / missing-locale filters.
     */
    public function stringsIndex(Request $request)
    {
        // Editor or Administrator only — translators / contributors don't get
        // to edit UI strings even via the workflow.
        if (!\AhgCore\Services\AclService::isAdministrator() && !\AhgCore\Services\AclService::isEditor()) {
            abort(403, 'Insufficient permissions');
        }

        $svc = app(\AhgTranslation\Services\UiStringService::class);

        $missing  = $request->input('missing');
        $contains = $request->input('contains');
        $page     = max(1, (int) $request->input('page', 1));
        $limit    = 100;
        $offset   = ($page - 1) * $limit;

        $allLocales = $svc->enabledLocales();

        // Default the column to the current request culture so the table is
        // always en + one target (no "all enabled" firehose). If the request
        // culture is en, pick the first non-en enabled locale as the default.
        $locale = $request->input('locale');
        if (!$locale || !in_array($locale, $allLocales, true) || $locale === 'en') {
            $current = app()->getLocale();
            if ($current !== 'en' && in_array($current, $allLocales, true)) {
                $locale = $current;
            } else {
                foreach ($allLocales as $code) {
                    if ($code !== 'en') { $locale = $code; break; }
                }
            }
        }

        $matrix = $svc->matrix($locale ?: null, $missing ?: null, $contains ?: null, $limit, $offset);

        // Pending count for the badge in the header link to the review queue.
        $pendingCount = (int) DB::table('ui_string_change')->where('status', 'pending')->count();

        return view('ahg-translation::strings', [
            'matrix'        => $matrix,
            'allLocales'    => $allLocales,
            'locale'        => $locale,
            'missing'       => $missing,
            'contains'      => $contains,
            'page'          => $page,
            'limit'         => $limit,
            'totalPages'    => (int) ceil(($matrix['total'] ?? 0) / max(1, $limit)),
            'isAdmin'       => \AhgCore\Services\AclService::isAdministrator(),
            'pendingCount'  => $pendingCount,
        ]);
    }

    /**
     * POST /admin/translation/strings/save — save a single key+value.
     *
     * Workflow:
     *  - Admin (default)            → applyApproved (writes JSON immediately, audit row)
     *  - Admin with ?review=1       → submitChange (queues for second reviewer)
     *  - Editor                     → submitChange (queues; needs admin approval)
     *  - Anyone else                → 403
     */
    public function stringsSave(Request $request)
    {
        $request->validate([
            'locale' => 'required|string|max:16',
            'key'    => 'required|string',
            'value'  => 'nullable|string',
            'review' => 'nullable|boolean',
        ]);

        $userId   = (int) (auth()->id() ?? 0);
        $isAdmin  = \AhgCore\Services\AclService::isAdministrator();
        $isEditor = \AhgCore\Services\AclService::isEditor();
        if (!$userId || (!$isAdmin && !$isEditor)) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $svc       = app(\AhgTranslation\Services\UiStringService::class);
        $reqReview = (bool) $request->input('review');
        $autoApprove = $isAdmin && !$reqReview;

        try {
            if ($autoApprove) {
                $svc->applyApproved($userId, $request->input('locale'), $request->input('key'), $request->input('value'));
                return response()->json(['ok' => true, 'state' => 'approved']);
            }
            $pendingId = $svc->submitChange($userId, $request->input('locale'), $request->input('key'), $request->input('value'));
            return response()->json(['ok' => true, 'state' => 'pending', 'pending_id' => $pendingId]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /admin/translation/strings/pending — review queue for translation
     * changes submitted by editors (or by admins who opted in to review).
     * Admin-only.
     */
    public function stringsPending(Request $request)
    {
        $svc = app(\AhgTranslation\Services\UiStringService::class);
        $locale  = $request->input('locale');
        $changes = $svc->pendingChanges($locale ?: null);
        return view('ahg-translation::strings-pending', [
            'changes'    => $changes,
            'locale'     => $locale,
            'allLocales' => $svc->enabledLocales(),
        ]);
    }

    /**
     * POST /admin/translation/strings/{id}/approve — admin approves a pending
     * change. Applies it to lang/{locale}.json and stamps the audit row.
     */
    public function stringsApprove(Request $request, int $id)
    {
        if (!\AhgCore\Services\AclService::isAdministrator()) {
            return response()->json(['ok' => false, 'error' => 'admin required'], 403);
        }
        $row = \DB::table('ui_string_change')->where('id', $id)->where('status', 'pending')->first();
        if (!$row) {
            return response()->json(['ok' => false, 'error' => 'not found or already reviewed'], 404);
        }
        $svc = app(\AhgTranslation\Services\UiStringService::class);
        try {
            $svc->applyApproved((int) auth()->id(), $row->locale, $row->key_text, $row->new_value, $id, $request->input('note'));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 400);
        }
        return response()->json(['ok' => true]);
    }

    /**
     * POST /admin/translation/strings/{id}/reject — admin rejects a pending change.
     */
    public function stringsReject(Request $request, int $id)
    {
        if (!\AhgCore\Services\AclService::isAdministrator()) {
            return response()->json(['ok' => false, 'error' => 'admin required'], 403);
        }
        $svc = app(\AhgTranslation\Services\UiStringService::class);
        $ok = $svc->rejectPending((int) auth()->id(), $id, $request->input('note'));
        return response()->json(['ok' => $ok]);
    }

    /**
     * GET /admin/translation/strings/mt-suggest — returns a machine-translated
     * suggestion for a single key. Reuses the existing translateText() backend
     * (Ollama / Server 78 GPU per #45).
     */
    public function stringsMtSuggest(Request $request)
    {
        $request->validate([
            'locale' => 'required|string|max:16',
            'text'   => 'required|string',
        ]);
        try {
            $r = $this->translateText($request->input('text'), 'en', $request->input('locale'));
            if (!empty($r['ok']) && !empty($r['translated'])) {
                return response()->json(['ok' => true, 'translated' => $r['translated']]);
            }
            return response()->json(['ok' => false, 'error' => $r['error'] ?? 'MT backend returned empty'], 502);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }
}
