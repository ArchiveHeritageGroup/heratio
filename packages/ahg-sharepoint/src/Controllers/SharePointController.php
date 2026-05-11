<?php

namespace AhgSharePoint\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Phase 1 admin UI — index, tenants, drives, mapping.
 *
 * Mirror of atom-ahg-plugins/ahgSharePointPlugin/modules/sharepoint/actions/actions.class.php.
 */
class SharePointController extends Controller
{
    public function __construct()
    {
        // TODO: wire AHG admin auth middleware. Heratio admin gating goes here.
    }

    public function index()
    {
        // TODO: aggregate tenant/drive/sync_state counts for dashboard.
        return view('ahg-sharepoint::index');
    }

    public function tenants()
    {
        // TODO: list rows from SharePointTenantRepository::all().
        return view('ahg-sharepoint::tenants');
    }

    public function tenantEdit(Request $request, int $id)
    {
        // TODO: GET = render form; POST = validate + persist via repository.
        // client_secret field is write-only. Encrypt before persisting.
        return view('ahg-sharepoint::tenant-edit', ['id' => $id]);
    }

    public function tenantTest(Request $request, int $id)
    {
        // TODO: invoke same logic as `php artisan sharepoint:test-connection --tenant={id}`,
        // return JSON for AJAX consumer in tenant-edit page.
        return response()->json(['status' => 'not_implemented']);
    }

    public function drives()
    {
        return view('ahg-sharepoint::drives');
    }

    public function driveBrowse(Request $request)
    {
        // TODO: AJAX — given tenantId, GET /sites + drives via Graph; return JSON for picker.
        return response()->json(['status' => 'not_implemented']);
    }

    public function mapping(Request $request, int $id)
    {
        // TODO: GET = render mapping editor; POST = persist sharepoint_mapping rows.
        return view('ahg-sharepoint::mapping', ['driveId' => $id]);
    }

    // ---- Phase 2.A actions ----

    public function subscriptions(Request $request)
    {
        $rows = \Illuminate\Support\Facades\DB::table('sharepoint_subscription')
            ->orderBy('expires_at')
            ->get();
        return view('ahg-sharepoint::subscriptions', ['subscriptions' => $rows]);
    }

    public function events(Request $request)
    {
        $query = \Illuminate\Support\Facades\DB::table('sharepoint_event')
            ->orderByDesc('received_at')
            ->limit(200);
        $status = $request->query('status');
        if ($status) {
            $query->where('status', $status);
        }
        return view('ahg-sharepoint::events', [
            'events' => $query->get(),
            'statusFilter' => $status,
        ]);
    }

    public function eventDetail(Request $request, int $id)
    {
        $event = \Illuminate\Support\Facades\DB::table('sharepoint_event')->where('id', $id)->first();
        if ($event === null) {
            abort(404);
        }
        if ($request->isMethod('POST') && $request->input('form_action') === 'retry') {
            \AhgSharePoint\Jobs\IngestSharePointEventJob::dispatch($id)->onQueue('integrations');
            \Illuminate\Support\Facades\DB::table('sharepoint_event')
                ->where('id', $id)
                ->update(['status' => 'queued', 'last_error' => null]);
            return redirect()->route('sharepoint.events.detail', ['id' => $id]);
        }
        return view('ahg-sharepoint::event-detail', ['event' => $event]);
    }

    // ─── Phase 2 (v2 ingest plan) — rules + mapping templates admin ─────

    public function rules()
    {
        $rules = \Illuminate\Support\Facades\DB::table('sharepoint_ingest_rule')
            ->leftJoin('sharepoint_drive', 'sharepoint_ingest_rule.drive_id', '=', 'sharepoint_drive.id')
            ->select('sharepoint_ingest_rule.*', 'sharepoint_drive.drive_name', 'sharepoint_drive.site_title')
            ->orderBy('sharepoint_ingest_rule.name')
            ->get();
        $drives = \Illuminate\Support\Facades\DB::table('sharepoint_drive')
            ->orderBy('site_title')
            ->get();
        return view('ahg-sharepoint::rules', compact('rules', 'drives'));
    }

    public function ruleEdit(Request $request)
    {
        $id = (int) $request->query('id');
        $rule = $id > 0
            ? \Illuminate\Support\Facades\DB::table('sharepoint_ingest_rule')->where('id', $id)->first()
            : null;
        $drives = \Illuminate\Support\Facades\DB::table('sharepoint_drive')->orderBy('site_title')->get();
        $parentLabel = null;
        if ($rule && !empty($rule->parent_id)) {
            $culture = app()->getLocale();
            $parentLabel = \Illuminate\Support\Facades\DB::table('information_object')
                ->join('information_object_i18n', function ($j) use ($culture) {
                    $j->on('information_object.id', '=', 'information_object_i18n.id')
                      ->where('information_object_i18n.culture', '=', $culture);
                })
                ->where('information_object.id', (int) $rule->parent_id)
                ->select('information_object.id', 'information_object.identifier', 'information_object_i18n.title as name')
                ->first();
        }
        $templatesByDrive = \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')
            ->orderBy('drive_id')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->groupBy('drive_id');
        return view('ahg-sharepoint::rule-edit', compact('rule', 'drives', 'parentLabel', 'templatesByDrive'));
    }

    public function ruleSave(Request $request)
    {
        $id = (int) $request->input('id');
        $processFlags = [];
        foreach (['virus_scan', 'ocr', 'ner', 'summarize', 'spellcheck', 'translate', 'format_id', 'face_detect'] as $f) {
            $processFlags[$f] = $request->input('process_' . $f) ? 1 : 0;
        }
        $attrs = [
            'drive_id' => (int) $request->input('drive_id'),
            'template_id' => $request->input('template_id') ? (int) $request->input('template_id') : null,
            'name' => (string) $request->input('name'),
            'folder_path' => $request->input('folder_path') ?: null,
            'file_pattern' => $request->input('file_pattern') ?: null,
            'retention_label' => ($request->input('retention_mode') === 'on')
                ? ($request->input('retention_label') ?: null)
                : null,
            'sector' => $request->input('sector', 'archive'),
            'standard' => $request->input('standard', 'isadg'),
            'repository_id' => $request->input('repository_id') ? (int) $request->input('repository_id') : null,
            'parent_id' => $request->input('parent_id') ? (int) $request->input('parent_id') : null,
            'parent_placement' => $request->input('parent_placement', 'top_level'),
            'process_flags' => json_encode($processFlags),
            'schedule_cron' => $request->input('schedule_cron', '*/15 * * * *'),
            'is_enabled' => $request->input('is_enabled') ? 1 : 0,
        ];
        if ($id > 0) {
            \Illuminate\Support\Facades\DB::table('sharepoint_ingest_rule')->where('id', $id)->update($attrs);
        } else {
            \Illuminate\Support\Facades\DB::table('sharepoint_ingest_rule')->insert($attrs);
        }
        return redirect()->route('sharepoint.rules')->with('notice', __('Rule saved.'));
    }

    public function ruleDelete(int $id)
    {
        \Illuminate\Support\Facades\DB::table('sharepoint_ingest_rule')->where('id', $id)->delete();
        return redirect()->route('sharepoint.rules')->with('notice', __('Rule deleted.'));
    }

    public function ruleRun(int $id)
    {
        // Fire the artisan task in the background.
        $bin = base_path('artisan');
        $log = storage_path('logs/sp-autoingest.log');
        $cmd = "nohup php " . escapeshellarg($bin) . " sharepoint:auto-ingest --rule=" . (int) $id . " --force >> " . escapeshellarg($log) . " 2>&1 &";
        @exec($cmd);
        return redirect()->route('sharepoint.rules')->with('notice', __("Rule #{$id} scheduled to run in background."));
    }

    public function mappings(Request $request)
    {
        $driveId = (int) $request->query('drive_id');
        $templateRaw = (string) $request->query('template_id', '');
        $isNew = ($templateRaw === 'new');
        $templateId = $isNew ? 0 : (int) $templateRaw;
        $drives = \Illuminate\Support\Facades\DB::table('sharepoint_drive')->orderBy('site_title')->get();
        $templates = collect();
        $selectedTemplate = null;
        $mappings = collect();
        if ($driveId > 0) {
            $templates = \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')
                ->where('drive_id', $driveId)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
            if (!$isNew) {
                if ($templateId > 0) {
                    $selectedTemplate = $templates->firstWhere('id', $templateId);
                }
                if (!$selectedTemplate) {
                    $selectedTemplate = $templates->firstWhere('is_default', 1) ?: $templates->first();
                }
                if ($selectedTemplate) {
                    $mappings = \Illuminate\Support\Facades\DB::table('sharepoint_mapping')
                        ->where('template_id', $selectedTemplate->id)
                        ->orderBy('sort_order')
                        ->get();
                }
            }
        }
        $targetFieldsByStandard = self::buildTargetFieldsByStandard();
        return view('ahg-sharepoint::mappings', compact('drives', 'templates', 'selectedTemplate', 'mappings', 'driveId', 'targetFieldsByStandard'));
    }

    /**
     * AJAX: discover SharePoint columns for a registered drive.
     */
    public function columns(Request $request)
    {
        $driveId = (int) $request->query('drive_id');
        $drive = \Illuminate\Support\Facades\DB::table('sharepoint_drive')->where('id', $driveId)->first();
        if (!$drive) {
            return response()->json(['error' => 'drive not found'], 404);
        }
        try {
            $browser = app(\AhgSharePoint\Services\SharePointBrowserService::class);
            return response()->json(['columns' => $browser->listColumns((int) $drive->tenant_id, $drive->drive_id)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function mappingsSave(Request $request)
    {
        $driveId = (int) $request->input('drive_id');
        $templateId = (int) $request->input('template_id');
        $name = trim((string) $request->input('template_name', ''));
        $sector = (string) $request->input('sector', 'archive');
        $standard = (string) $request->input('standard', 'isadg');
        $isDefault = $request->input('is_default') ? 1 : 0;
        if ($driveId <= 0) {
            return back()->with('error', __('Drive id required.'));
        }
        if ($name === '') {
            return back()->with('error', __('Template name is required.'));
        }
        \Illuminate\Support\Facades\DB::transaction(function () use (&$templateId, $driveId, $name, $sector, $standard, $isDefault, $request) {
            if ($templateId > 0) {
                \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')
                    ->where('id', $templateId)
                    ->where('drive_id', $driveId)
                    ->update([
                        'name' => $name,
                        'sector' => $sector,
                        'standard' => $standard,
                        'is_default' => $isDefault,
                        'updated_at' => now(),
                    ]);
            } else {
                $templateId = (int) \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')->insertGetId([
                    'drive_id' => $driveId,
                    'name' => $name,
                    'sector' => $sector,
                    'standard' => $standard,
                    'is_default' => $isDefault,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($isDefault) {
                \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')
                    ->where('drive_id', $driveId)
                    ->where('id', '!=', $templateId)
                    ->update(['is_default' => 0, 'updated_at' => now()]);
            }
            \Illuminate\Support\Facades\DB::table('sharepoint_mapping')->where('template_id', $templateId)->delete();
            $sourceFields = (array) $request->input('source_field', []);
            $targetFields = (array) $request->input('target_field', []);
            $transforms = (array) $request->input('transform', []);
            $defaults = (array) $request->input('default_value', []);
            foreach ($sourceFields as $i => $src) {
                if (trim((string) $src) === '' || trim((string) ($targetFields[$i] ?? '')) === '') {
                    continue;
                }
                \Illuminate\Support\Facades\DB::table('sharepoint_mapping')->insert([
                    'drive_id' => $driveId,
                    'template_id' => $templateId,
                    'source_field' => $src,
                    'target_field' => $targetFields[$i],
                    'target_standard' => $standard,
                    'transform' => $transforms[$i] ?? null,
                    'default_value' => $defaults[$i] ?? null,
                    'sort_order' => $i,
                    'is_required' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
        return redirect()->route('sharepoint.mappings', ['drive_id' => $driveId, 'template_id' => $templateId])->with('notice', __('Mapping template saved.'));
    }

    /**
     * Target-field catalogue per descriptive standard. Mirrors
     * ahgIngestPlugin/lib/Services/IngestService.php::getTargetFields() on PSIS.
     */
    private static function buildTargetFieldsByStandard(): array
    {
        $common = [
            'legacyId', 'parentId', 'qubitParentSlug', 'identifier',
            'title', 'levelOfDescription', 'extentAndMedium',
            'repository', 'archivalHistory', 'acquisition',
            'scopeAndContent', 'appraisal', 'accruals',
            'arrangement', 'accessConditions', 'reproductionConditions',
            'physicalCharacteristics', 'findingAids', 'relatedUnitsOfDescription',
            'locationOfOriginals', 'locationOfCopies', 'rules',
            'descriptionIdentifier', 'descriptionStatus', 'publicationStatus',
            'levelOfDetail', 'revisionHistory', 'sources',
            'culture', 'alternateTitle',
            'digitalObjectPath', 'digitalObjectURI', 'digitalObjectChecksum',
            'subjectAccessPoints', 'placeAccessPoints', 'nameAccessPoints',
            'genreAccessPoints', 'creators', 'creatorDates',
            'creatorDatesStart', 'creatorDatesEnd', 'creatorDateNotes',
            'creationDates', 'creationDatesStart', 'creationDatesEnd',
            'eventActors', 'eventTypes', 'eventDates',
            'eventStartDates', 'eventEndDates', 'eventPlaces',
            'physicalObjectName', 'physicalObjectLocation', 'physicalObjectType',
            'accessionNumber', 'copyrightStatus', 'copyrightExpires', 'copyrightHolder',
        ];
        $extras = [
            'isadg' => [],
            'rad' => [
                'radOtherTitleInformation', 'radTitleStatementOfResponsibility',
                'radStatementOfProjection', 'radStatementOfCoordinates',
                'radEdition', 'radStatementOfScaleCartographic',
            ],
            'dacs' => ['unitDates', 'unitDateActuated'],
            'dc' => [
                'type', 'format', 'language', 'relation', 'coverage',
                'contributor', 'publisher', 'rights', 'date',
            ],
            'mods' => [
                'genre', 'typeOfResource', 'abstract', 'tableOfContents',
                'originInfoPublisher', 'originInfoPlace', 'originInfoDateIssued',
                'issuance', 'frequency', 'classification', 'note',
            ],
            'spectrum' => [
                'objectNumber', 'objectName', 'objectType',
                'materialComponent', 'technique', 'dimension',
                'inscription', 'condition', 'completeness',
            ],
            'cco' => [
                'workType', 'measurements', 'materialsTechniques',
                'stylePeriod', 'culturalContext',
            ],
        ];
        $out = [];
        foreach ($extras as $code => $extra) {
            $out[$code] = array_merge($common, $extra);
        }
        return $out;
    }

    public function mappingTemplateDelete(Request $request)
    {
        $driveId = (int) $request->input('drive_id');
        $templateId = (int) $request->input('template_id');
        if ($templateId > 0 && $driveId > 0) {
            \Illuminate\Support\Facades\DB::table('sharepoint_mapping_template')
                ->where('id', $templateId)
                ->where('drive_id', $driveId)
                ->delete();
        }
        return redirect()->route('sharepoint.mappings', ['drive_id' => $driveId])->with('notice', __('Template deleted.'));
    }
}
