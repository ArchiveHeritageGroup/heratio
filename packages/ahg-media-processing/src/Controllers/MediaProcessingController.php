<?php

/**
 * MediaProcessingController - Controller for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems.co.za
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



namespace AhgMediaProcessing\Controllers;

use AhgMediaProcessing\Services\DerivativeService;
use AhgMediaProcessing\Services\WatermarkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MediaProcessingController extends Controller
{
    private DerivativeService $derivativeService;
    private WatermarkService $watermarkService;

    public function __construct(DerivativeService $derivativeService, WatermarkService $watermarkService)
    {
        $this->derivativeService = $derivativeService;
        $this->watermarkService = $watermarkService;
    }

    /**
     * Admin dashboard showing derivative statistics, media processing settings,
     * system tools, and recent activity.
     */
    public function index()
    {
        $stats = $this->derivativeService->getStats();
        $recentDerivatives = $this->derivativeService->getRecentDerivatives(25);
        $missingDerivatives = $this->derivativeService->getMastersWithMissingDerivatives(50);

        // Usage labels for display
        $usageLabels = [
            DerivativeService::USAGE_THUMBNAIL => 'Thumbnail',
            DerivativeService::USAGE_REFERENCE => 'Reference',
        ];

        // System tools availability
        $tools = [
            'ffmpeg'    => $this->checkTool('/usr/bin/ffmpeg'),
            'ffprobe'   => $this->checkTool('/usr/bin/ffprobe'),
            'mediainfo' => $this->checkTool('/usr/bin/mediainfo'),
            'exiftool'  => $this->checkTool('/usr/bin/exiftool'),
            'whisper'   => $this->checkTool('/usr/local/bin/whisper') || $this->checkTool('/usr/bin/whisper'),
            'convert'   => $this->checkTool('/usr/bin/convert'),
            'pdfinfo'   => $this->checkTool('/usr/bin/pdfinfo'),
        ];

        // Media processing settings grouped
        $settings = $this->loadMediaProcessorSettings();
        $grouped = $this->groupSettings($settings);

        // Digital object derivative settings from setting table
        $derivativeSettings = $this->loadDerivativeSettings();

        // Queue stats
        $queueStats = $this->loadQueueStats();

        // Group labels for display
        $groupLabels = [
            'thumbnail'     => 'Thumbnail Generation',
            'preview'       => 'Preview Clips',
            'waveform'      => 'Audio Waveform',
            'poster'        => 'Video Posters',
            'audio'         => 'Audio Processing',
            'transcription' => 'Speech Transcription',
        ];

        return view('ahg-media-processing::index', compact(
            'stats',
            'recentDerivatives',
            'missingDerivatives',
            'usageLabels',
            'tools',
            'grouped',
            'groupLabels',
            'derivativeSettings',
            'queueStats'
        ));
    }

    /**
     * Regenerate derivatives for a single digital object.
     */
    public function regenerate(int $id)
    {
        $result = $this->derivativeService->regenerateDerivatives($id);

        $messages = [];
        if ($result['thumbnail']) {
            $messages[] = 'Thumbnail generated successfully.';
        }
        if ($result['reference']) {
            $messages[] = 'Reference image generated successfully.';
        }
        if (!empty($result['errors'])) {
            return redirect()->route('media-processing.index')
                ->with('error', 'Derivative generation errors: ' . implode(' ', $result['errors']));
        }

        return redirect()->route('media-processing.index')
            ->with('success', implode(' ', $messages));
    }

    /**
     * Queue batch regeneration for all masters missing derivatives.
     */
    public function batchRegenerate(Request $request)
    {
        $type = $request->input('type', 'all'); // 'all', 'thumbnail', 'reference'
        $limit = (int)$request->input('limit', 100);

        $masters = $this->derivativeService->getMastersWithMissingDerivatives($limit);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($masters as $master) {
            $needsThumb = !$master->has_thumbnail && in_array($type, ['all', 'thumbnail']);
            $needsRef = !$master->has_reference && in_array($type, ['all', 'reference']);

            if (!$needsThumb && !$needsRef) {
                continue;
            }

            $result = $this->derivativeService->regenerateDerivatives($master->id);

            if ($result['thumbnail'] || $result['reference']) {
                $successCount++;
            }
            if (!empty($result['errors'])) {
                $errorCount++;
                $errors = array_merge($errors, array_map(
                    fn($e) => "DO #{$master->id}: {$e}",
                    $result['errors']
                ));
            }
        }

        $message = "Batch regeneration complete: {$successCount} objects processed successfully.";
        if ($errorCount > 0) {
            $message .= " {$errorCount} objects had errors.";
            return redirect()->route('media-processing.index')
                ->with('warning', $message)
                ->with('batch_errors', array_slice($errors, 0, 20));
        }

        return redirect()->route('media-processing.index')
            ->with('success', $message);
    }

    /**
     * Watermark configuration page (GET and POST).
     */
    public function watermarkSettings(Request $request)
    {
        if ($request->isMethod('post')) {
            return $this->saveWatermarkSettings($request);
        }

        $settings = $this->watermarkService->getSettings();
        $watermarkTypes = $this->watermarkService->getWatermarkTypes();
        $customWatermarks = $this->watermarkService->getCustomWatermarks();

        $positions = WatermarkService::POSITION_LABELS;

        return view('ahg-media-processing::watermark-settings', compact(
            'settings',
            'watermarkTypes',
            'customWatermarks',
            'positions'
        ));
    }

    /**
     * Save media processing settings (POST from index form).
     */
    public function saveSettings(Request $request)
    {
        $settings = $request->input('settings', []);

        // Boolean keys that need explicit zero when unchecked
        $booleanKeys = [
            'thumbnail_enabled', 'preview_enabled', 'waveform_enabled',
            'poster_enabled', 'audio_preview_enabled', 'transcription_enabled',
            'auto_detect_language',
        ];

        foreach ($settings as $key => $value) {
            $row = DB::table('media_processor_settings')
                ->where('setting_key', $key)
                ->first(['setting_type']);

            $type = $row ? $row->setting_type : 'string';

            if ($type === 'boolean') {
                $value = $value ? '1' : '0';
            } elseif ($type === 'json' && is_array($value)) {
                $value = json_encode($value);
            }

            DB::table('media_processor_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['setting_value' => $value, 'updated_at' => now()]
            );
        }

        // Handle unchecked checkboxes
        foreach ($booleanKeys as $key) {
            if (!isset($settings[$key])) {
                DB::table('media_processor_settings')
                    ->where('setting_key', $key)
                    ->update(['setting_value' => '0', 'updated_at' => now()]);
            }
        }

        return redirect()->route('media-processing.index')
            ->with('success', 'Media processing settings saved successfully.');
    }

    /**
     * Save digital object derivative settings (POST from index form).
     */
    public function saveDerivativeSettings(Request $request)
    {
        $request->validate([
            'pdf_page_number' => 'nullable|integer|min:1',
            'reference_image_maxwidth' => 'nullable|integer|min:100|max:2000',
        ]);

        $pdfPage = $request->input('pdf_page_number');
        $maxWidth = $request->input('reference_image_maxwidth');

        if ($pdfPage !== null) {
            DB::table('setting_i18n')->updateOrInsert(
                ['id' => 158, 'culture' => 'en'],
                ['value' => (string) $pdfPage]
            );
        }

        if ($maxWidth !== null) {
            DB::table('setting_i18n')->updateOrInsert(
                ['id' => 5, 'culture' => 'en'],
                ['value' => (string) $maxWidth]
            );
        }

        return redirect()->route('media-processing.index')
            ->with('success', 'Digital object derivative settings saved.');
    }

    /**
     * Queue management page.
     */
    public function queue()
    {
        $stats = DB::table('media_processing_queue')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $items = DB::table('media_processing_queue as q')
            ->leftJoin('digital_object as d', 'q.digital_object_id', '=', 'd.id')
            ->select('q.*', 'd.name as filename')
            ->orderBy('q.created_at', 'desc')
            ->limit(50)
            ->get();

        return view('ahg-media-processing::queue', compact('stats', 'items'));
    }

    /**
     * Clear completed/failed queue items.
     */
    public function clearQueue()
    {
        DB::table('media_processing_queue')
            ->whereIn('status', ['completed', 'failed'])
            ->delete();

        return redirect()->route('media-processing.queue')
            ->with('success', 'Queue cleared.');
    }

    /**
     * Check if a system tool is available.
     */
    private function checkTool(string $path): bool
    {
        return file_exists($path) && is_executable($path);
    }

    /**
     * Load media processor settings from database.
     */
    private function loadMediaProcessorSettings(): array
    {
        $settings = [];

        try {
            $rows = DB::table('media_processor_settings')
                ->orderBy('setting_group')
                ->orderBy('setting_key')
                ->get();

            foreach ($rows as $row) {
                $value = $row->setting_value;

                switch ($row->setting_type) {
                    case 'boolean':
                        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                    case 'integer':
                        $value = (int) $value;
                        break;
                    case 'float':
                        $value = (float) $value;
                        break;
                    case 'json':
                        $value = json_decode($value, true);
                        break;
                }

                $settings[$row->setting_key] = [
                    'value'       => $value,
                    'type'        => $row->setting_type,
                    'group'       => $row->setting_group,
                    'description' => $row->description,
                ];
            }
        } catch (\Exception $e) {
            // Table might not exist
        }

        return $settings;
    }

    /**
     * Group settings by group name.
     */
    private function groupSettings(array $settings): array
    {
        $grouped = [];

        foreach ($settings as $key => $setting) {
            $group = $setting['group'] ?? 'general';
            $grouped[$group][$key] = $setting;
        }

        return $grouped;
    }

    /**
     * Load derivative settings from the setting/setting_i18n tables.
     */
    private function loadDerivativeSettings(): array
    {
        $pdfPage = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'digital_object_derivatives_pdf_page_number')
            ->where('setting_i18n.culture', 'en')
            ->value('setting_i18n.value');

        $maxWidth = DB::table('setting')
            ->join('setting_i18n', 'setting.id', '=', 'setting_i18n.id')
            ->where('setting.name', 'reference_image_maxwidth')
            ->where('setting_i18n.culture', 'en')
            ->value('setting_i18n.value');

        return [
            'pdf_page_number'        => $pdfPage ?: '1',
            'reference_image_maxwidth' => $maxWidth ?: '480',
            'pdfinfo_available'      => $this->checkTool('/usr/bin/pdfinfo'),
        ];
    }

    /**
     * Load queue statistics.
     */
    private function loadQueueStats(): array
    {
        try {
            return DB::table('media_processing_queue')
                ->select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Handle watermark settings form submission.
     */
    protected function saveWatermarkSettings(Request $request)
    {
        // Handle custom watermark upload
        if ($request->hasFile('custom_watermark_file') && $request->file('custom_watermark_file')->isValid()) {
            $request->validate([
                'custom_watermark_file' => 'required|image|mimes:png,jpg,jpeg,gif|max:5120',
                'custom_watermark_name' => 'required|string|max:100',
                'custom_watermark_position' => 'nullable|string|max:50',
                'custom_watermark_opacity' => 'nullable|numeric|min:0|max:1',
            ]);

            $result = $this->watermarkService->uploadCustomWatermark(
                $request->file('custom_watermark_file'),
                $request->input('custom_watermark_name', 'Custom Watermark'),
                $request->input('custom_watermark_position', 'center'),
                (float)$request->input('custom_watermark_opacity', 0.40),
                Auth::id()
            );

            if ($result === false) {
                return redirect()->route('media-processing.watermark-settings')
                    ->with('error', 'Failed to upload custom watermark. Only PNG, JPEG, and GIF files are allowed.');
            }

            return redirect()->route('media-processing.watermark-settings')
                ->with('success', 'Custom watermark uploaded successfully.');
        }

        // Handle delete custom watermark
        if ($request->filled('delete_custom_watermark')) {
            $this->watermarkService->deleteCustomWatermark((int)$request->input('delete_custom_watermark'));
            return redirect()->route('media-processing.watermark-settings')
                ->with('success', 'Custom watermark deleted.');
        }

        // Save global settings
        $request->validate([
            'default_watermark_enabled' => 'nullable|in:0,1',
            'default_watermark_type' => 'nullable|string|max:50',
            'default_custom_watermark_id' => 'nullable|integer',
            'apply_watermark_on_view' => 'nullable|in:0,1',
            'apply_watermark_on_download' => 'nullable|in:0,1',
            'security_watermark_override' => 'nullable|in:0,1',
            'watermark_min_size' => 'nullable|integer|min:50|max:2000',
        ]);

        $this->watermarkService->saveSettings([
            'default_watermark_enabled' => $request->input('default_watermark_enabled', '0'),
            'default_watermark_type' => $request->input('default_watermark_type', 'COPYRIGHT'),
            'default_custom_watermark_id' => $request->input('default_custom_watermark_id', ''),
            'apply_watermark_on_view' => $request->input('apply_watermark_on_view', '0'),
            'apply_watermark_on_download' => $request->input('apply_watermark_on_download', '0'),
            'security_watermark_override' => $request->input('security_watermark_override', '0'),
            'watermark_min_size' => $request->input('watermark_min_size', '200'),
        ]);

        // Update Cantaloupe cache
        $this->watermarkService->updateCantaloupeCache();

        return redirect()->route('media-processing.watermark-settings')
            ->with('success', 'Watermark settings saved successfully.');
    }
}
