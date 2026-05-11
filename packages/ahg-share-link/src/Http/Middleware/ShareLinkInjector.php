<?php

/**
 * ShareLinkInjector — server-side HTML response filter that injects a
 * "Share record" button + Bootstrap 5 modal into IO show pages.
 *
 * Mirrors the AtoM-side ViewLinkInjector listener exactly. Avoids touching
 * locked show blades: the button + modal are injected post-render via a
 * regex pass on the response body. Silent on failure (no anchor matched,
 * non-HTML response, entity unknown, user not authenticated or no ACL).
 *
 * Activation conditions:
 *   - GET request, not XHR
 *   - Response Content-Type starts with text/html
 *   - URL resolves to an IO by slug (catch-all)
 *   - User is authenticated AND has share_link.create ACL
 *
 * Copyright (C) 2026 The Archive and Heritage Group (Pty) Ltd
 * License: AGPL-3.0-or-later
 *
 * @phase E
 */

namespace AhgShareLink\Http\Middleware;

use AhgShareLink\Services\AclCheck;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ShareLinkInjector
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (!$this->isInjectionCandidate($request, $response)) {
                return $response;
            }

            $userId = (int) (Auth::id() ?? 0);
            if ($userId <= 0) {
                return $response;
            }

            if (!$this->userCanCreate($userId)) {
                return $response;
            }

            $entityId = $this->resolveInformationObjectId($request);
            if ($entityId === null) {
                return $response;
            }

            $body = (string) $response->getContent();
            $issueUrl = url('/share-link/issue');
            $csrfToken = (string) csrf_token();
            $nonce = $this->cspNonceAttr($response);
            $modal = $this->buildModal($entityId, $issueUrl, $csrfToken, $nonce);

            $modified = $this->inject($body, $modal);
            if ($modified !== null) {
                $response->setContent($modified);
            }
        } catch (\Throwable $e) {
            // Never break a show-page render on injection failure.
            \Log::warning('ahg-share-link ShareLinkInjector error: ' . $e->getMessage());
        }

        return $response;
    }

    private function isInjectionCandidate(Request $request, Response $response): bool
    {
        if ($request->method() !== 'GET' || $request->ajax() || $request->wantsJson()) {
            return false;
        }
        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($contentType !== '' && !str_starts_with($contentType, 'text/html')) {
            return false;
        }
        $status = $response->getStatusCode();
        return $status >= 200 && $status < 300;
    }

    /**
     * Heratio IO show is `/{slug}` (catch-all). Try slug-based lookup.
     */
    private function resolveInformationObjectId(Request $request): ?int
    {
        $path = trim($request->path(), '/');
        if ($path === '' || str_contains($path, '/')) {
            return null;
        }
        if (str_starts_with($path, 'admin') || str_starts_with($path, 'api')) {
            return null;
        }
        if (strlen($path) > 255) {
            return null;
        }

        try {
            $row = DB::table('slug')->where('slug', $path)->select('object_id')->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row || empty($row->object_id)) {
            return null;
        }
        $objectId = (int) $row->object_id;

        try {
            $isIo = DB::table('information_object')->where('id', $objectId)->exists();
        } catch (\Throwable $e) {
            return null;
        }
        return $isIo ? $objectId : null;
    }

    private function userCanCreate(int $userId): bool
    {
        try {
            return (new AclCheck())->canUserDo($userId, AclCheck::ACTION_CREATE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function buildModal(int $entityId, string $issueUrl, string $csrfToken, string $nonceAttr): string
    {
        $defaultExpiry = (new \DateTimeImmutable('+14 days'))->format('Y-m-d');
        $maxExpiry = (new \DateTimeImmutable('+90 days'))->format('Y-m-d');
        $issueUrlEsc = htmlspecialchars($issueUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $csrfEsc = htmlspecialchars($csrfToken, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $tShare  = $this->esc(__('Share this record'));
        $tShareModal = $this->esc(__('Share record'));
        $tClose  = $this->esc(__('Close'));
        $tCancel = $this->esc(__('Cancel'));
        $tCreate = $this->esc(__('Create share link'));
        $tExpires = $this->esc(__('Expires on'));
        $tEmail   = $this->esc(__('Recipient email'));
        $tNote    = $this->esc(__('Note for recipient'));
        $tMax     = $this->esc(__('Max visits'));
        $tCopy    = $this->esc(__('Copy link'));
        $tCopied  = $this->esc(__('Copied'));
        $tHelp    = $this->esc(__('Anyone with this link can view the record until it expires.'));
        $tMaxNote = $this->esc(__('Maximum expiry is 90 days unless your account has an extended-expiry permission.'));

        $banner = '<div class="ahg-share-link-banner mb-2">'
            . '<button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#ahgShareLinkModal">'
            . '<i class="fas fa-share-alt me-1"></i>' . $tShare
            . '</button></div>';

        $modal = <<<HTML
<div class="modal fade" id="ahgShareLinkModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fas fa-share-alt me-1"></i>{$tShareModal}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{$tClose}"></button>
      </div>
      <div class="modal-body">
        <form id="ahgShareLinkForm" data-issue-url="{$issueUrlEsc}" data-io-id="{$entityId}">
          <input type="hidden" name="_token" value="{$csrfEsc}">
          <div class="mb-3">
            <label for="ahgShareExpiresAt" class="form-label">{$tExpires}</label>
            <input type="date" class="form-control" id="ahgShareExpiresAt" name="expires_at" value="{$defaultExpiry}" max="{$maxExpiry}" required>
            <div class="form-text">{$tMaxNote}</div>
          </div>
          <div class="mb-3">
            <label for="ahgShareEmail" class="form-label">{$tEmail}</label>
            <input type="email" class="form-control" id="ahgShareEmail" name="recipient_email" placeholder="name@example.com">
          </div>
          <div class="mb-3">
            <label for="ahgShareNote" class="form-label">{$tNote}</label>
            <textarea class="form-control" id="ahgShareNote" name="recipient_note" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label for="ahgShareMax" class="form-label">{$tMax}</label>
            <input type="number" min="1" class="form-control" id="ahgShareMax" name="max_access">
          </div>
          <div id="ahgShareLinkAlert" class="alert d-none" role="alert"></div>
          <div id="ahgShareLinkResult" class="d-none">
            <div class="input-group">
              <input type="text" class="form-control" id="ahgShareLinkUrl" readonly>
              <button class="btn btn-outline-secondary" type="button" id="ahgShareLinkCopy">
                <i class="fas fa-copy me-1"></i><span>{$tCopy}</span>
              </button>
            </div>
            <div class="form-text mt-2">{$tHelp}</div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{$tCancel}</button>
        <button type="button" class="btn btn-primary" id="ahgShareLinkSubmit">{$tCreate}</button>
      </div>
    </div>
  </div>
</div>
<script {$nonceAttr}>
(function () {
  if (window.ahgShareLinkInit) { return; } window.ahgShareLinkInit = true;
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('ahgShareLinkForm');
    if (!form) return;
    var submit = document.getElementById('ahgShareLinkSubmit');
    var alertEl = document.getElementById('ahgShareLinkAlert');
    var resultEl = document.getElementById('ahgShareLinkResult');
    var urlEl = document.getElementById('ahgShareLinkUrl');
    var copyBtn = document.getElementById('ahgShareLinkCopy');
    var ioId = form.getAttribute('data-io-id');
    var issueUrl = form.getAttribute('data-issue-url');

    function showError(msg) {
      alertEl.className = 'alert alert-danger';
      alertEl.textContent = msg;
      alertEl.classList.remove('d-none');
    }
    function reset() {
      alertEl.classList.add('d-none');
      alertEl.textContent = '';
      resultEl.classList.add('d-none');
      urlEl.value = '';
    }

    submit.addEventListener('click', function () {
      reset();
      var data = new FormData(form);
      data.append('information_object_id', ioId);
      submit.disabled = true;
      fetch(issueUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: data
      })
      .then(function (r) { return r.json().then(function (j) { return { status: r.status, body: j }; }); })
      .then(function (resp) {
        submit.disabled = false;
        if (!resp.body || resp.body.ok !== true) {
          var msg = resp.body && resp.body.error && resp.body.error.message
            ? resp.body.error.message
            : ('Error ' + resp.status);
          showError(msg);
          return;
        }
        urlEl.value = resp.body.public_url || '';
        resultEl.classList.remove('d-none');
      })
      .catch(function (e) {
        submit.disabled = false;
        showError(e && e.message ? e.message : 'Network error');
      });
    });

    copyBtn.addEventListener('click', function () {
      if (!urlEl.value) return;
      urlEl.select();
      try { document.execCommand('copy'); } catch (e) {}
      var span = copyBtn.querySelector('span');
      if (span) {
        var prev = span.textContent;
        span.textContent = '{$tCopied}';
        setTimeout(function () { span.textContent = prev; }, 1500);
      }
    });
  });
})();
</script>
HTML;

        return $banner . $modal;
    }

    /**
     * Insert the banner+modal just after the first matching anchor.
     */
    private function inject(string $body, string $payload): ?string
    {
        $patterns = [
            '#(<div[^>]+id=["\']main-column["\'][^>]*>)#i',
            '#(<main[^>]*>)#i',
            '#(<section[^>]+class=["\'][^"\']*\bcontent\b[^"\']*["\'][^>]*>)#i',
            '#(<div[^>]+class=["\'][^"\']*\bpage-content\b[^"\']*["\'][^>]*>)#i',
        ];
        foreach ($patterns as $pattern) {
            $count = 0;
            $replaced = preg_replace($pattern, '$1' . $payload, $body, 1, $count);
            if ($count > 0 && is_string($replaced)) {
                return $replaced;
            }
        }
        return null;
    }

    /**
     * Spatie CSP binds the active nonce on the request. Mirror the lookup
     * used by InjectCspNonces so injected <script> tags carry the live nonce.
     */
    private function cspNonceAttr(Response $response): string
    {
        // Spatie's nonce is read via the request macro `CspNonce` or via the
        // service container key `csp-nonce`. The InjectCspNonces middleware
        // uses the latter at render time — re-use the same source.
        try {
            $nonce = app('csp-nonce');
            if (is_string($nonce) && $nonce !== '') {
                return 'nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '"';
            }
        } catch (\Throwable $e) {
            // fall through
        }
        return '';
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
