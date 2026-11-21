<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use AhgApi\Concerns\PaginatesApiResponse;
use AhgCore\Support\I18nFallback;

class RepositoryApiController extends Controller
{
    use PaginatesApiResponse;

    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    /**
     * GET /api/v1/repositories
     *
     * Paginated list of archival institutions.
     */
    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'alphabetic');
        $sortDir = $request->get('sort_direction', 'asc');
        $offset = ($page - 1) * $limit;

        $query = DB::table('repository')
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'repository.id', $this->culture, required: true))
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->where('object.class_name', 'QubitRepository');

        $total = $query->count();

        // Sort
        switch ($sort) {
            case 'updated':
                $query->orderBy('object.updated_at', $sortDir === 'asc' ? 'asc' : 'desc');
                break;
            case 'identifier':
                $query->orderBy('repository.identifier', $sortDir === 'desc' ? 'desc' : 'asc');
                break;
            default: // alphabetic
                $query->orderBy(I18nFallback::col('actor_i18n', 'authorized_form_of_name'), $sortDir === 'desc' ? 'desc' : 'asc');
                break;
        }

        $rows = $query
            ->select([
                'repository.id',
                'repository.identifier',
                I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'authorized_form_of_name'),
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->offset($offset)
            ->limit($limit)
            ->get();

        // Get holdings count for each repository
        $repoIds = $rows->pluck('id')->toArray();
        $holdingsCounts = [];
        if (! empty($repoIds)) {
            $holdingsCounts = DB::table('information_object')
                ->whereIn('repository_id', $repoIds)
                ->where('id', '!=', 1)
                ->select('repository_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('repository_id')
                ->pluck('cnt', 'repository_id')
                ->toArray();
        }

        $data = $rows->map(function ($row) use ($holdingsCounts) {
            return [
                'id' => $row->id,
                'slug' => $row->slug,
                'identifier' => $row->identifier,
                'authorized_form_of_name' => $row->authorized_form_of_name,
                'holdings_count' => $holdingsCounts[$row->id] ?? 0,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        });

        return $this->paginatedResponse($data, $total, $page, $limit, 'repositories');
    }

    /**
     * GET /api/v1/repositories/{slug}
     *
     * Full repository with all ISDIAH and ISAAR fields.
     */
    public function show(string $slug): JsonResponse
    {
        $objectId = DB::table('slug')->where('slug', $slug)->value('object_id');
        if (! $objectId) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Repository '{$slug}' not found.",
            ], 404);
        }

        $repo = DB::table('repository')
            ->join('actor', 'repository.id', '=', 'actor.id')
            ->join('object', 'repository.id', '=', 'object.id')
            ->join('slug', 'repository.id', '=', 'slug.object_id')
            ->tap(fn ($q) => I18nFallback::join($q, 'actor_i18n', 'repository.id', $this->culture))
            ->tap(fn ($q) => I18nFallback::join($q, 'repository_i18n', 'repository.id', $this->culture))
            ->where('repository.id', $objectId)
            ->where('object.class_name', 'QubitRepository')
            ->select([
                'repository.id',
                'repository.identifier',
                'repository.desc_status_id',
                'repository.desc_detail_id',
                'repository.desc_identifier',
                'repository.upload_limit',
                'repository.source_culture',
                'actor.entity_type_id',
                'actor.corporate_body_identifiers',
                'actor.source_standard',
                // Actor i18n (ISAAR)
                I18nFallback::col('actor_i18n', 'authorized_form_of_name', 'authorized_form_of_name'),
                I18nFallback::col('actor_i18n', 'dates_of_existence', 'dates_of_existence'),
                I18nFallback::col('actor_i18n', 'history', 'history'),
                I18nFallback::col('actor_i18n', 'places', 'places'),
                I18nFallback::col('actor_i18n', 'legal_status', 'legal_status'),
                I18nFallback::col('actor_i18n', 'functions', 'functions'),
                I18nFallback::col('actor_i18n', 'mandates', 'mandates'),
                I18nFallback::col('actor_i18n', 'internal_structures', 'internal_structures'),
                I18nFallback::col('actor_i18n', 'general_context', 'general_context'),
                I18nFallback::col('actor_i18n', 'institution_responsible_identifier', 'institution_responsible_identifier'),
                I18nFallback::col('actor_i18n', 'rules', 'rules'),
                I18nFallback::col('actor_i18n', 'sources', 'sources'),
                I18nFallback::col('actor_i18n', 'revision_history', 'revision_history'),
                // Repository i18n (ISDIAH)
                I18nFallback::col('repository_i18n', 'geocultural_context', 'geocultural_context'),
                I18nFallback::col('repository_i18n', 'collecting_policies', 'collecting_policies'),
                I18nFallback::col('repository_i18n', 'buildings', 'buildings'),
                I18nFallback::col('repository_i18n', 'holdings', 'holdings'),
                I18nFallback::col('repository_i18n', 'finding_aids', 'finding_aids'),
                I18nFallback::col('repository_i18n', 'opening_times', 'opening_times'),
                I18nFallback::col('repository_i18n', 'access_conditions', 'access_conditions'),
                I18nFallback::col('repository_i18n', 'disabled_access', 'disabled_access'),
                I18nFallback::col('repository_i18n', 'research_services', 'research_services'),
                I18nFallback::col('repository_i18n', 'reproduction_services', 'reproduction_services'),
                I18nFallback::col('repository_i18n', 'public_facilities', 'public_facilities'),
                I18nFallback::col('repository_i18n', 'desc_institution_identifier', 'desc_institution_identifier'),
                I18nFallback::col('repository_i18n', 'desc_rules', 'desc_rules'),
                I18nFallback::col('repository_i18n', 'desc_sources', 'desc_sources'),
                I18nFallback::col('repository_i18n', 'desc_revision_history', 'desc_revision_history'),
                // Object
                'object.created_at',
                'object.updated_at',
                'slug.slug',
            ])
            ->first();

        if (! $repo) {
            return response()->json([
                'error' => 'Not Found',
                'message' => "Repository '{$slug}' not found.",
            ], 404);
        }

        // Contact information
        $contacts = DB::table('contact_information')
            ->tap(fn ($q) => I18nFallback::join($q, 'contact_information_i18n', 'contact_information.id', $this->culture))
            ->where('contact_information.actor_id', $repo->id)
            ->select([
                'contact_information.primary_contact',
                'contact_information.contact_person',
                'contact_information.street_address',
                'contact_information.website',
                'contact_information.email',
                'contact_information.telephone',
                'contact_information.fax',
                'contact_information.postal_code',
                'contact_information.country_code',
                'contact_information.longitude',
                'contact_information.latitude',
                I18nFallback::col('contact_information_i18n', 'contact_type', 'contact_type'),
                I18nFallback::col('contact_information_i18n', 'city', 'city'),
                I18nFallback::col('contact_information_i18n', 'region', 'region'),
                I18nFallback::col('contact_information_i18n', 'note', 'note'),
            ])
            ->get();

        // Holdings count
        $holdingsCount = DB::table('information_object')
            ->where('repository_id', $repo->id)
            ->where('id', '!=', 1)
            ->count();

        // Description term names
        $descStatusName = $this->termName($repo->desc_status_id);
        $descDetailName = $this->termName($repo->desc_detail_id);

        $data = [
            'id' => $repo->id,
            'slug' => $repo->slug,
            'identifier' => $repo->identifier,
            'authorized_form_of_name' => $repo->authorized_form_of_name,
            'source_culture' => $repo->source_culture,
            'source_standard' => $repo->source_standard,
            'corporate_body_identifiers' => $repo->corporate_body_identifiers,
            'desc_status' => $descStatusName,
            'desc_detail' => $descDetailName,
            'desc_identifier' => $repo->desc_identifier,
            'upload_limit' => $repo->upload_limit,
            // ISAAR fields (inherited from actor)
            'dates_of_existence' => $repo->dates_of_existence,
            'history' => $repo->history,
            'places' => $repo->places,
            'legal_status' => $repo->legal_status,
            'functions' => $repo->functions,
            'mandates' => $repo->mandates,
            'internal_structures' => $repo->internal_structures,
            'general_context' => $repo->general_context,
            'institution_responsible_identifier' => $repo->institution_responsible_identifier,
            'rules' => $repo->rules,
            'sources' => $repo->sources,
            'revision_history' => $repo->revision_history,
            // ISDIAH fields
            'geocultural_context' => $repo->geocultural_context,
            'collecting_policies' => $repo->collecting_policies,
            'buildings' => $repo->buildings,
            'holdings' => $repo->holdings,
            'finding_aids' => $repo->finding_aids,
            'opening_times' => $repo->opening_times,
            'access_conditions' => $repo->access_conditions,
            'disabled_access' => $repo->disabled_access,
            'research_services' => $repo->research_services,
            'reproduction_services' => $repo->reproduction_services,
            'public_facilities' => $repo->public_facilities,
            'desc_institution_identifier' => $repo->desc_institution_identifier,
            'desc_rules' => $repo->desc_rules,
            'desc_sources' => $repo->desc_sources,
            'desc_revision_history' => $repo->desc_revision_history,
            // Related data
            'contacts' => $contacts->values(),
            'holdings_count' => $holdingsCount,
            'created_at' => $repo->created_at,
            'updated_at' => $repo->updated_at,
        ];

        return response()->json(['data' => $data]);
    }

    /**
     * Resolve a term name by ID.
     */
    protected function termName(?int $termId): ?string
    {
        if (! $termId) {
            return null;
        }

        return DB::table('term_i18n')
            ->where('id', $termId)
            ->where('culture', $this->culture)
            ->value('name');
    }

}
