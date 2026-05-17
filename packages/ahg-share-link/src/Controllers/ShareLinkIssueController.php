<?php

/**
 * ShareLinkIssueController — authenticated issuance endpoint.
 *
 * Route: POST /share-link/issue (registered behind the `web` + `auth` stack).
 * Mirrors the AtoM POST /shareLink/issue contract.
 *
 * Body fields:
 *   information_object_id (int, required)
 *   expires_at            (string, optional — date or datetime)
 *   recipient_email       (string, optional)
 *   recipient_note        (string, optional)
 *   max_access            (int, optional)
 *
 * Returns JSON. On success (201):
 *   {ok: true, token, token_id, expires_at, public_url}
 * On failure:
 *   {ok: false, error: {code, message}}
 *
 * @phase E
 */

namespace AhgShareLink\Controllers;

use AhgShareLink\Services\ExpiryCapExceededException;
use AhgShareLink\Services\InsufficientClearanceException;
use AhgShareLink\Services\InvalidRequestException;
use AhgShareLink\Services\IssueService;
use AhgShareLink\Services\NotAuthenticatedException;
use AhgShareLink\Services\PermissionDeniedException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShareLinkIssueController extends Controller
{
    public function store(Request $request, IssueService $issueService)
    {
        $wantsJson = $this->wantsJson($request);

        $user = Auth::user();
        if (!$user) {
            return $this->error('not_authenticated', 'Authentication required', 401, $wantsJson, $request);
        }
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            return $this->error('not_authenticated', 'No user id on session', 401, $wantsJson, $request);
        }

        $ioId = (int) $request->input('information_object_id');
        if ($ioId <= 0) {
            return $this->error('invalid_request', 'information_object_id is required', 422, $wantsJson, $request);
        }

        $expiresAtParam = trim((string) $request->input('expires_at'));
        $expiresAt = null;
        if ($expiresAtParam !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtParam);
            } catch (\Throwable $e) {
                return $this->error('invalid_request', 'expires_at could not be parsed', 422, $wantsJson, $request);
            }
        }

        $recipientEmail = trim((string) $request->input('recipient_email')) ?: null;
        if ($recipientEmail !== null && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->error('invalid_request', 'recipient_email is not a valid email address', 422, $wantsJson, $request);
        }
        $recipientNote = trim((string) $request->input('recipient_note')) ?: null;
        $maxAccessRaw = $request->input('max_access');
        $maxAccess = null;
        if ($maxAccessRaw !== null && $maxAccessRaw !== '') {
            if (!is_numeric($maxAccessRaw) || (int) $maxAccessRaw < 1) {
                return $this->error('invalid_request', 'max_access must be a positive integer', 422, $wantsJson, $request);
            }
            $maxAccess = (int) $maxAccessRaw;
        }

        try {
            $result = $issueService->issue(
                userId: $userId,
                informationObjectId: $ioId,
                expiresAt: $expiresAt,
                recipientEmail: $recipientEmail,
                recipientNote: $recipientNote,
                maxAccess: $maxAccess,
            );

            $absolute = $result['public_url'] ?? null;
            if ($absolute === null || !preg_match('#^https?://#i', (string) $absolute)) {
                $absolute = $request->getSchemeAndHttpHost() . '/share/' . $result['token'];
            }

            if ($wantsJson) {
                return response()->json([
                    'ok'         => true,
                    'token'      => $result['token'],
                    'token_id'   => $result['token_id'],
                    'expires_at' => $result['expires_at'],
                    'public_url' => $absolute,
                ], 201);
            }

            return redirect()
                ->route('share-link.issued', ['tokenId' => $result['token_id']])
                ->with('share_link_just_issued', true);
        } catch (NotAuthenticatedException $e) {
            return $this->error('not_authenticated', $e->getMessage(), 401, $wantsJson, $request);
        } catch (PermissionDeniedException $e) {
            return $this->error('permission_denied', $e->getMessage(), 403, $wantsJson, $request);
        } catch (InsufficientClearanceException $e) {
            return $this->error('insufficient_clearance', $e->getMessage(), 403, $wantsJson, $request);
        } catch (ExpiryCapExceededException $e) {
            return $this->error('expiry_cap_exceeded', $e->getMessage(), 422, $wantsJson, $request);
        } catch (InvalidRequestException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422, $wantsJson, $request);
        } catch (\Throwable $e) {
            \Log::error('ahg-share-link issue unexpected: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('server_error', 'An unexpected error occurred', 500, $wantsJson, $request);
        }
    }

    public function newForm(Request $request): View|RedirectResponse
    {
        $ioId = (int) $request->query('information_object_id', 0);
        if ($ioId <= 0) {
            abort(404);
        }

        $record = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('io.id', '=', 'ioi.id')
                  ->where('ioi.culture', '=', app()->getLocale() ?: 'en');
            })
            ->where('io.id', $ioId)
            ->select('io.id', 'io.slug', 'ioi.title', 'io.security_classification_id')
            ->first();
        if (!$record) {
            abort(404);
        }

        $defaultExpiryDays = (int) $this->readSetting('share_link.default_expiry_days', '14');
        $maxExpiryDays     = (int) $this->readSetting('share_link.max_expiry_days', '90');

        return view('ahg-share-link::new', [
            'informationObjectId' => $ioId,
            'recordTitle'         => $record->title ?: ('IO #' . $ioId),
            'recordSlug'          => $record->slug,
            'defaultExpiryDays'   => $defaultExpiryDays,
            'maxExpiryDays'       => $maxExpiryDays,
            'classificationLevel' => $record->security_classification_id !== null ? (int) $record->security_classification_id : null,
            'errorMessage'        => session('error'),
        ]);
    }

    public function issued(int $tokenId, Request $request): View
    {
        $token = DB::table('information_object_share_token as t')
            ->leftJoin('information_object as io', 'io.id', '=', 't.information_object_id')
            ->leftJoin('information_object_i18n as ioi', function ($j) {
                $j->on('ioi.id', '=', 'io.id')
                  ->where('ioi.culture', '=', app()->getLocale() ?: 'en');
            })
            ->where('t.id', $tokenId)
            ->select(
                't.id as token_id',
                't.token',
                't.information_object_id',
                't.expires_at',
                't.recipient_email',
                't.max_access',
                'io.slug as record_slug',
                'ioi.title as record_title'
            )
            ->first();
        if (!$token) {
            abort(404);
        }

        $publicUrl = $request->getSchemeAndHttpHost() . '/share/' . $token->token;

        return view('ahg-share-link::issue', [
            'tokenId'             => (int) $token->token_id,
            'token'               => $token->token,
            'publicUrl'           => $publicUrl,
            'expiresAt'           => (string) $token->expires_at,
            'informationObjectId' => (int) $token->information_object_id,
            'recordTitle'         => $token->record_title ?: ('IO #' . $token->information_object_id),
            'recordSlug'          => $token->record_slug,
            'recipientEmail'      => $token->recipient_email,
            'maxAccess'           => $token->max_access !== null ? (int) $token->max_access : null,
        ]);
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->header('Accept', ''));
        if (str_contains($accept, 'application/json')) {
            return true;
        }
        return $request->isJson() || $request->ajax();
    }

    private function readSetting(string $key, string $default): string
    {
        try {
            $value = DB::table('ahg_settings')->where('setting_key', $key)->value('setting_value');
            return $value !== null ? (string) $value : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function error(string $code, string $message, int $status, bool $wantsJson = true, ?Request $request = null)
    {
        if ($wantsJson) {
            return response()->json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
        }
        $back = $request?->input('information_object_id');
        if ($back !== null && (int) $back > 0) {
            return redirect()
                ->route('share-link.new', ['information_object_id' => (int) $back])
                ->withInput()
                ->with('error', $message);
        }
        return redirect()->back()->withInput()->with('error', $message);
    }
}
