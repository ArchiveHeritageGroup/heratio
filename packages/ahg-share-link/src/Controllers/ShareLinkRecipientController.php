<?php

/**
 * ShareLinkRecipientController — anonymous landing for share-link recipients.
 *
 * Route: GET /share/{token} (registered WITHOUT auth middleware — the token
 * is the credential).
 *
 * @phase D
 */

namespace AhgShareLink\Controllers;

use AhgShareLink\Services\AccessService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ShareLinkRecipientController extends Controller
{
    public function show(string $token, Request $request, AccessService $accessService)
    {
        $result = $accessService->evaluate(
            token: $token,
            ip: $request->ip(),
            userAgent: $request->header('User-Agent'),
        );

        if (!$result->allowed) {
            return response()
                ->view('ahg-share-link::denied', [
                    'result' => $result,
                    'reason' => $result->reason,
                ], $result->httpStatus)
                ->header('Referrer-Policy', 'no-referrer');
        }

        $tokenRow = $result->tokenRow;
        $ioId = (int) $tokenRow->information_object_id;

        // Issuer name
        $issuer = DB::table('user')->where('id', $tokenRow->issued_by)->first();
        $issuerName = $issuer ? ($issuer->username ?: ('user #' . $tokenRow->issued_by)) : '(unknown)';

        // Title + scope
        $culture = app()->getLocale();
        $i18n = DB::table('information_object_i18n')->where('id', $ioId)->where('culture', $culture)->first()
            ?: DB::table('information_object_i18n')->where('id', $ioId)->orderBy('culture')->first();

        return response()
            ->view('ahg-share-link::recipient', [
                'tokenRow'            => $tokenRow,
                'informationObjectId' => $ioId,
                'expiresAt'           => $tokenRow->expires_at,
                'issuerName'          => $issuerName,
                'title'               => $i18n->title ?? ('#' . $ioId),
                'scopeAndContent'     => $i18n->scope_and_content ?? null,
                'identifier'          => DB::table('information_object')->where('id', $ioId)->value('identifier'),
            ], 200)
            ->header('Referrer-Policy', 'no-referrer');
    }
}
