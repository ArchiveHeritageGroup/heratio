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
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class ShareLinkIssueController extends Controller
{
    public function store(Request $request, IssueService $issueService): JsonResponse
    {
        $user = Auth::user();
        if (!$user) {
            return $this->error('not_authenticated', 'Authentication required', 401);
        }
        $userId = (int) ($user->id ?? 0);
        if ($userId <= 0) {
            return $this->error('not_authenticated', 'No user id on session', 401);
        }

        $ioId = (int) $request->input('information_object_id');
        if ($ioId <= 0) {
            return $this->error('invalid_request', 'information_object_id is required', 422);
        }

        $expiresAtParam = trim((string) $request->input('expires_at'));
        $expiresAt = null;
        if ($expiresAtParam !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtParam);
            } catch (\Throwable $e) {
                return $this->error('invalid_request', 'expires_at could not be parsed', 422);
            }
        }

        $recipientEmail = trim((string) $request->input('recipient_email')) ?: null;
        if ($recipientEmail !== null && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->error('invalid_request', 'recipient_email is not a valid email address', 422);
        }
        $recipientNote = trim((string) $request->input('recipient_note')) ?: null;
        $maxAccessRaw = $request->input('max_access');
        $maxAccess = null;
        if ($maxAccessRaw !== null && $maxAccessRaw !== '') {
            if (!is_numeric($maxAccessRaw) || (int) $maxAccessRaw < 1) {
                return $this->error('invalid_request', 'max_access must be a positive integer', 422);
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

            return response()->json([
                'ok'         => true,
                'token'      => $result['token'],
                'token_id'   => $result['token_id'],
                'expires_at' => $result['expires_at'],
                'public_url' => $absolute,
            ], 201);
        } catch (NotAuthenticatedException $e) {
            return $this->error('not_authenticated', $e->getMessage(), 401);
        } catch (PermissionDeniedException $e) {
            return $this->error('permission_denied', $e->getMessage(), 403);
        } catch (InsufficientClearanceException $e) {
            return $this->error('insufficient_clearance', $e->getMessage(), 403);
        } catch (ExpiryCapExceededException $e) {
            return $this->error('expiry_cap_exceeded', $e->getMessage(), 422);
        } catch (InvalidRequestException $e) {
            return $this->error('invalid_request', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            \Log::error('ahg-share-link issue unexpected: ' . $e->getMessage(), ['exception' => $e]);
            return $this->error('server_error', 'An unexpected error occurred', 500);
        }
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }
}
