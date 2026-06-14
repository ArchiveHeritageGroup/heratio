<?php

namespace AhgApi\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonorApiController extends Controller
{
    protected string $culture = 'en';

    public function __construct()
    {
        $this->culture = app()->getLocale();
    }

    public function index(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $sort = $request->get('sort', 'alphabetic');
        $sortDir = strtolower($request->get('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = DB::table('donor')
            ->join('object', 'donor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('donor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'donor.id', '=', 'slug.object_id');

        $orderCol = match ($sort) {
            'alphabetic' => 'actor_i18n.authorized_form_of_name',
            default => 'object.updated_at',
        };

        $total = $query->count();
        $results = $query
            ->select(
                'donor.id',
                'actor_i18n.authorized_form_of_name',
                'slug.slug',
                'object.created_at', 'object.updated_at'
            )
            ->orderBy($orderCol, $sortDir)
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json(['total' => $total, 'page' => $page, 'limit' => $limit, 'results' => $results]);
    }

    public function show(string $slug): JsonResponse
    {
        $donor = DB::table('donor')
            ->join('object', 'donor.id', '=', 'object.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('donor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', $this->culture);
            })
            ->join('slug', 'donor.id', '=', 'slug.object_id')
            ->where('slug.slug', $slug)
            ->select('donor.id', 'actor_i18n.*', 'slug.slug', 'object.created_at', 'object.updated_at')
            ->first();

        if (! $donor) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $donor->contact_information = DB::table('contact_information')
            ->leftJoin('contact_information_i18n', function ($join) {
                $join->on('contact_information.id', '=', 'contact_information_i18n.id')
                    ->where('contact_information_i18n.culture', '=', $this->culture);
            })
            ->where('contact_information.actor_id', $donor->id)
            ->first();

        // #1261: decrypt the registered contact_details PII columns (email +
        // city) so the API returns plaintext. decrypt() is a pass-through for
        // plaintext rows, so legacy / encryption-off rows are unaffected.
        if ($donor->contact_information) {
            $enc = new \AhgCore\Services\EncryptionService;
            $ci = $donor->contact_information;
            if (! empty($ci->email)) {
                $ci->email = $enc->decrypt(\AhgCore\Services\EncryptionService::CATEGORY_CONTACT_DETAILS, (string) $ci->email, 'contact_information', 'email', $ci->id ?? null);
            }
            if (! empty($ci->city)) {
                $ci->city = $enc->decrypt(\AhgCore\Services\EncryptionService::CATEGORY_CONTACT_DETAILS, (string) $ci->city, 'contact_information_i18n', 'city', $ci->id ?? null);
            }
        }

        return response()->json($donor);
    }
}
