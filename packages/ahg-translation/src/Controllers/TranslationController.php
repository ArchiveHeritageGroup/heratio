<?php

namespace AhgTranslation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class TranslationController extends Controller
{
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
     * GET /admin/translation/health
     *
     * Health check for MT endpoint.
     */
    public function health()
    {
        $endpoint = $this->getSetting('mt.endpoint', 'http://192.168.0.112:5004/ai/v1/translate');
        $apiKey = $this->getSetting('mt.api_key', 'ahg_ai_demo_internal_2026');

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
        $cultures = DB::table('setting')
            ->where('name', 'i18n_languages')
            ->value('value');

        $defaultCulture = DB::table('setting')
            ->where('name', 'default_culture')
            ->value('value') ?? 'en';

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

        // Get current languages
        $row = DB::table('setting')->where('name', 'i18n_languages')->first();
        $cultures = $row ? json_decode($row->value, true) : ['en'];

        if (!in_array($code, $cultures)) {
            $cultures[] = $code;

            if ($row) {
                DB::table('setting')
                    ->where('name', 'i18n_languages')
                    ->update(['value' => json_encode($cultures)]);
            } else {
                DB::table('setting')->insert([
                    'name' => 'i18n_languages',
                    'value' => json_encode($cultures),
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
        $apiKey = $this->getSetting('mt.api_key', 'ahg_ai_demo_internal_2026');
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
}
