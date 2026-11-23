<?php

/**
 * ReleaseDoiService - DOIs for versioned releases of a collection (#1586).
 *
 * ahg_doi holds one DOI per information object, so a release series (one
 * concept DOI plus one DOI per version) is tracked by its owning package and
 * only the DataCite conversation lives here, on DoiService's credentials,
 * guard and request path.
 *
 * Every call is an idempotent upsert, so a publish that failed half way can be
 * run again. Without $publish the DOI stays a draft, which DataCite lets you
 * delete; a findable DOI is permanent.
 *
 * @copyright 2026 Johan Pieterse / Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgDoiManage\Services;

class ReleaseDoiService extends DoiService
{
    public function conceptDoi(object $config, string $seriesKey): string
    {
        return rtrim((string) $config->datacite_prefix, '/').'/portable.'.strtolower($seriesKey);
    }

    public function versionDoi(string $conceptDoi, int $version): string
    {
        return $conceptDoi.'.v'.$version;
    }

    /**
     * DataCite's own view of a DOI: draft, registered or findable, or null if it
     * does not exist. A crash can leave a DOI public before the local row says so.
     *
     * @return array{ok:bool, state:?string, error:?string}
     */
    public function remoteState(?int $repositoryId, string $doi): array
    {
        $config = $this->configFor($repositoryId);
        if ($problem = $this->unconfiguredResult($config, 'registration')) {
            return ['ok' => false, 'state' => null, 'error' => $problem['error']];
        }
        $resp = $this->dataciteRequest($config, 'GET', '/dois/'.str_replace('%2F', '/', rawurlencode($doi)));
        if (! $resp['ok']) {
            return str_starts_with((string) $resp['error'], 'HTTP 404')
                ? ['ok' => true, 'state' => null, 'error' => null]
                : ['ok' => false, 'state' => null, 'error' => $resp['error']];
        }
        $state = $resp['body']['data']['attributes']['state'] ?? null;

        return is_string($state)
            ? ['ok' => true, 'state' => $state, 'error' => null]
            : ['ok' => false, 'state' => null, 'error' => 'DataCite did not report a state.'];
    }

    /** @return array{ok:bool, error:?string} */
    public function sync(?int $repositoryId, string $doi, array $attributes, bool $publish): array
    {
        $config = $this->configFor($repositoryId);
        if ($problem = $this->unconfiguredResult($config, 'registration')) {
            return ['ok' => false, 'error' => $problem['error']];
        }

        $attributes['doi'] = $doi;
        unset($attributes['event']);
        if ($publish) {
            $attributes['event'] = 'publish';
        }
        $payload = ['data' => ['id' => $doi, 'type' => 'dois', 'attributes' => $attributes]];

        $path = '/dois/'.str_replace('%2F', '/', rawurlencode($doi));
        $existing = $this->dataciteRequest($config, 'GET', $path);
        if (! $existing['ok'] && ! str_starts_with((string) $existing['error'], 'HTTP 404')) {
            return ['ok' => false, 'error' => $existing['error']];
        }

        $resp = $existing['ok']
            ? $this->dataciteRequest($config, 'PUT', $path, $payload)
            : $this->dataciteRequest($config, 'POST', '/dois', $payload);

        $this->log(null, null, 'release_'.($publish ? 'publish' : 'draft'), null, $resp['ok'] ? ($publish ? 'findable' : 'draft') : 'failed', [
            'doi' => $doi,
            'error' => $resp['ok'] ? null : mb_substr((string) $resp['error'], 0, 500),
        ]);

        return ['ok' => (bool) $resp['ok'], 'error' => $resp['ok'] ? null : $resp['error']];
    }
}
