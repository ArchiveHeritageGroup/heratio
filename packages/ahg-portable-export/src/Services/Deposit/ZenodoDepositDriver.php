<?php

namespace AhgPortableExport\Services\Deposit;

use AhgPortableExport\Services\ReleaseException;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Zenodo deposit API. The release keeps its own DataCite DOI, passed as an
 * existing DOI, so Zenodo does not mint a second one for the same version.
 */
class ZenodoDepositDriver implements DepositDriver
{
    /** The token is only ever sent to these hosts. */
    public const HOSTS = ['zenodo.org', 'sandbox.zenodo.org'];

    public static function isAllowedUrl(?string $url): bool
    {
        return is_string($url)
            && parse_url($url, PHP_URL_SCHEME) === 'https'
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), self::HOSTS, true)
            && parse_url($url, PHP_URL_PORT) === null
            && parse_url($url, PHP_URL_USER) === null;
    }

    public function deposit(object $target, object $deposit, array $files, array $metadata, callable $remember): array
    {
        $base = rtrim((string) $target->base_url, '/');
        if (! self::isAllowedUrl($base)) {
            throw new ReleaseException('Deposit refused.', ['The Zenodo target URL is not an allowed Zenodo host.']);
        }
        if (empty($target->api_token)) {
            throw new ReleaseException('Deposit refused.', ['The Zenodo target has no API token.']);
        }
        $client = fn (int $timeout = 60): PendingRequest => Http::withToken(Crypt::decryptString($target->api_token))
            ->acceptJson()->withoutRedirecting()->timeout($timeout);

        if (! empty($deposit->external_id)) {
            $record = $this->ok($client()->get($base.'/api/deposit/depositions/'.rawurlencode($deposit->external_id)), 'read the deposit');
            if (! empty($record['submitted'])) {
                return $this->result($record);
            }
        } else {
            $record = $this->ok($client()->post($base.'/api/deposit/depositions', (object) []), 'create the deposit');
            $remember((string) $record['id']);
        }

        $id = rawurlencode((string) ($record['id'] ?? ''));
        $bucket = (string) ($record['links']['bucket'] ?? '');
        if ($id === '' || ! self::isAllowedUrl($bucket) || parse_url($bucket, PHP_URL_HOST) !== parse_url($base, PHP_URL_HOST)) {
            throw new ReleaseException('Deposit failed.', ['Zenodo returned an unexpected upload location.']);
        }

        foreach ([
            $files['base_name'].'.zip' => $files['package'],
            'release-manifest.json' => $files['manifest'],
            'CHANGELOG.md' => $files['changelog'],
        ] as $name => $path) {
            $body = Utils::streamFor(fopen($path, 'rb'));
            $this->ok($client(7200)->withBody($body, 'application/octet-stream')
                ->put(rtrim($bucket, '/').'/'.rawurlencode($name)), 'upload '.$name);
        }

        $this->ok($client()->put($base.'/api/deposit/depositions/'.$id, ['metadata' => $this->metadata($target, $metadata)]), 'set the metadata');

        return $this->result($this->ok($client(300)->post($base.'/api/deposit/depositions/'.$id.'/actions/publish'), 'publish the deposit'));
    }

    public function discard(object $target, object $deposit): void
    {
        if (empty($deposit->external_id)) {
            return;
        }
        $base = rtrim((string) $target->base_url, '/');
        if (! self::isAllowedUrl($base) || empty($target->api_token)) {
            throw new ReleaseException('Abandon refused.', ['The Zenodo target cannot be reached to confirm the deposit is unpublished.']);
        }
        $client = Http::withToken(Crypt::decryptString($target->api_token))->acceptJson()->withoutRedirecting()->timeout(60);
        $url = $base.'/api/deposit/depositions/'.rawurlencode((string) $deposit->external_id);

        $read = $client->get($url);
        if ($read->status() === 404) {
            return;
        }
        $record = $this->ok($read, 'read the deposit');
        if (! array_key_exists('submitted', $record) || $record['submitted'] !== false) {
            throw new ReleaseException('Abandon refused.', ['This deposit is already published at Zenodo, or its state is unclear, so the version cannot be abandoned.']);
        }

        $deleted = $client->delete($url);
        if (! $deleted->successful() && $deleted->status() !== 404) {
            throw new ReleaseException('Abandon refused.', ['Zenodo refused to delete the draft deposit (HTTP '.$deleted->status().').']);
        }
    }

    private function metadata(object $target, array $m): array
    {
        $related = [['relation' => 'isVersionOf', 'identifier' => $m['concept_doi'], 'scheme' => 'doi']];
        if (! empty($m['previous_doi'])) {
            $related[] = ['relation' => 'isNewVersionOf', 'identifier' => $m['previous_doi'], 'scheme' => 'doi'];
        }
        $out = [
            'upload_type' => 'dataset',
            'title' => $m['title'],
            'creators' => [['name' => $m['creator']]],
            'description' => e($m['description']),
            'version' => $m['version'],
            'publication_date' => $m['date'],
            'access_right' => 'open',
            'license' => $m['license'],
            'doi' => $m['doi'],
            'related_identifiers' => $related,
        ];
        if (! empty($target->community)) {
            $out['communities'] = [['identifier' => $target->community]];
        }

        return $out;
    }

    private function ok(Response $response, string $step): array
    {
        if (! $response->successful()) {
            // Status only: a response body is third-party text and may echo the request.
            throw new ReleaseException('Deposit failed.', ['Zenodo refused to '.$step.' (HTTP '.$response->status().').']);
        }

        return (array) $response->json();
    }

    private function result(array $record): array
    {
        return ['external_id' => (string) ($record['id'] ?? ''), 'external_url' => $record['links']['record_html'] ?? ($record['links']['html'] ?? null)];
    }
}
