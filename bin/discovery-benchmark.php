#!/usr/bin/env php
<?php
/**
 * discovery-benchmark — capture environment fingerprints for a discovery
 * eval run so v1 numbers can be replayed in v2.
 *
 * Implements GitHub issue #20 Part A.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * AGPL-3.0-or-later. See <https://www.gnu.org/licenses/>.
 *
 * Captures:
 *   - qrels file SHA-256
 *   - ES index versions + doc counts
 *   - Qdrant collection names + point counts + collection config hash
 *   - Ollama model digests (per loaded model)
 *   - SPARQL endpoint URL + per-class triple counts
 *   - Heratio platform version + git SHA + dirty flag
 *   - Server: hostname, kernel, PHP version, MySQL version, Qdrant version, Fuseki version
 *
 * Usage:
 *   php bin/discovery-benchmark.php \
 *     --qrels=tests/discovery/qrels.csv \
 *     --output=storage/discovery-eval/<run_id>/environment.json
 */

const ES_HOST       = 'http://localhost:9200';
const ES_INDICES    = ['heratio_qubitinformationobject', 'heratio_qubitactor', 'heratio_qubitterm', 'heratio_qubitrepository'];
const QDRANT_HOST   = 'http://localhost:6333';
const OLLAMA_HOSTS  = ['http://192.168.0.112:11434', 'http://192.168.0.78:11434'];
const SPARQL_HOST   = 'http://192.168.0.112:3030/ric';
const SPARQL_CLASSES = ['Record', 'RecordSet', 'Place', 'CorporateBody', 'Person', 'Agent', 'Activity', 'Event'];

$opts = parseArgs($argv);
$repoRoot = realpath(dirname(__DIR__));

$env = [
    'run_timestamp' => gmdate('c'),
    'qrels'         => qrelsFingerprint($opts['qrels']),
    'heratio'       => heratioVersion($repoRoot),
    'server'        => serverFingerprint(),
    'mysql'         => mysqlFingerprint(),
    'elasticsearch' => esFingerprint(),
    'qdrant'        => qdrantFingerprint(),
    'ollama'        => ollamaFingerprint(),
    'sparql'        => sparqlFingerprint(),
];

@mkdir(dirname($opts['output']), 0775, true);
file_put_contents($opts['output'], json_encode($env, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo realpath($opts['output']) ?: $opts['output'];
echo "\n";


function parseArgs(array $argv): array
{
    $opts = ['qrels' => null, 'output' => null];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            echo "Usage: php bin/discovery-benchmark.php --qrels=PATH --output=PATH\n";
            exit(0);
        }
        if (preg_match('/^--([a-z-]+)=(.*)$/', $arg, $m) && array_key_exists($m[1], $opts)) {
            $opts[$m[1]] = $m[2];
            continue;
        }
        fwrite(STDERR, "ERROR: bad argument: {$arg}\n");
        exit(2);
    }
    foreach (['qrels', 'output'] as $k) {
        if (empty($opts[$k])) {
            fwrite(STDERR, "ERROR: --{$k}= is required\n");
            exit(2);
        }
    }
    if (! is_readable($opts['qrels'])) {
        fwrite(STDERR, "ERROR: cannot read qrels: {$opts['qrels']}\n");
        exit(2);
    }
    return $opts;
}

function qrelsFingerprint(string $path): array
{
    return [
        'path'    => realpath($path) ?: $path,
        'sha256'  => hash_file('sha256', $path),
        'size'    => filesize($path),
        'mtime'   => gmdate('c', (int) filemtime($path)),
    ];
}

function heratioVersion(string $repoRoot): array
{
    $verFile = $repoRoot . '/version.json';
    $version = is_readable($verFile) ? (json_decode((string) file_get_contents($verFile), true)['version'] ?? null) : null;
    $sha = trim((string) shell_exec("cd " . escapeshellarg($repoRoot) . " && git rev-parse HEAD 2>/dev/null"));
    $shaShort = trim((string) shell_exec("cd " . escapeshellarg($repoRoot) . " && git rev-parse --short HEAD 2>/dev/null"));
    $dirty = trim((string) shell_exec("cd " . escapeshellarg($repoRoot) . " && git status --porcelain 2>/dev/null"));
    return [
        'platform_version' => $version,
        'git_sha'          => $sha ?: null,
        'git_sha_short'    => $shaShort ?: null,
        'dirty'            => $dirty !== '',
        'dirty_files'      => $dirty ? array_values(array_filter(array_map('trim', explode("\n", $dirty)))) : [],
    ];
}

function serverFingerprint(): array
{
    return [
        'hostname'      => trim((string) shell_exec('hostname 2>/dev/null')),
        'kernel'        => trim((string) shell_exec('uname -r 2>/dev/null')),
        'php_version'   => PHP_VERSION,
        'php_sapi'      => PHP_SAPI,
        'os_release'    => parseOsRelease(),
    ];
}

function parseOsRelease(): ?string
{
    if (! is_readable('/etc/os-release')) return null;
    foreach (file('/etc/os-release', FILE_IGNORE_NEW_LINES) as $line) {
        if (strpos($line, 'PRETTY_NAME=') === 0) {
            return trim(substr($line, 12), "\" ");
        }
    }
    return null;
}

function mysqlFingerprint(): array
{
    $ver = trim((string) shell_exec("mysql -u root -N -e 'SELECT VERSION()' 2>/dev/null"));
    $rows = [];
    foreach (['heratio', 'atom', 'archive'] as $db) {
        $cnt = trim((string) shell_exec("mysql -u root {$db} -N -e 'SELECT COUNT(*) FROM information_object' 2>/dev/null"));
        if ($cnt === '') continue;
        $rows[$db] = ['information_object_rows' => (int) $cnt];
    }
    return ['version' => $ver ?: null, 'databases' => $rows];
}

function esFingerprint(): array
{
    $info = httpJson(ES_HOST . '/');
    $out = [
        'host'    => ES_HOST,
        'version' => $info['version']['number'] ?? null,
        'indices' => [],
    ];
    foreach (ES_INDICES as $idx) {
        $stats = httpJson(ES_HOST . '/' . $idx . '/_stats?human=false');
        if (! is_array($stats) || ! isset($stats['indices'][$idx])) continue;
        $s = $stats['indices'][$idx];
        $out['indices'][$idx] = [
            'docs'         => $s['total']['docs']['count'] ?? null,
            'size_bytes'   => $s['total']['store']['size_in_bytes'] ?? null,
        ];
    }
    return $out;
}

function qdrantFingerprint(): array
{
    $list = httpJson(QDRANT_HOST . '/collections');
    $out = ['host' => QDRANT_HOST, 'collections' => []];
    foreach (($list['result']['collections'] ?? []) as $c) {
        $name = $c['name'] ?? null;
        if (! $name) continue;
        $detail = httpJson(QDRANT_HOST . '/collections/' . urlencode($name));
        $r = $detail['result'] ?? [];
        $out['collections'][$name] = [
            'points_count'          => $r['points_count'] ?? null,
            'indexed_vectors_count' => $r['indexed_vectors_count'] ?? null,
            'config_hash'           => isset($r['config']) ? hash('sha256', json_encode($r['config'])) : null,
            'vector_size'           => $r['config']['params']['vectors']['size'] ?? null,
            'distance'              => $r['config']['params']['vectors']['distance'] ?? null,
        ];
    }
    return $out;
}

function ollamaFingerprint(): array
{
    $out = [];
    foreach (OLLAMA_HOSTS as $host) {
        $tags = httpJson($host . '/api/tags', 3);
        if (! is_array($tags)) {
            $out[$host] = ['reachable' => false];
            continue;
        }
        $models = [];
        foreach (($tags['models'] ?? []) as $m) {
            $models[$m['name'] ?? '?'] = [
                'digest'        => $m['digest'] ?? null,
                'size'          => $m['size'] ?? null,
                'modified_at'   => $m['modified_at'] ?? null,
            ];
        }
        $out[$host] = ['reachable' => true, 'models' => $models];
    }
    return $out;
}

function sparqlFingerprint(): array
{
    $out = ['endpoint' => SPARQL_HOST, 'reachable' => false, 'classes' => []];
    foreach (SPARQL_CLASSES as $cls) {
        $sparql = "PREFIX rico: <https://www.ica.org/standards/RiC/ontology#>\n"
                . "SELECT (COUNT(DISTINCT ?s) AS ?c) WHERE { ?s a rico:{$cls} }";
        $body = sparqlSelect(SPARQL_HOST, $sparql, 30);
        if ($body === null) continue;
        $out['reachable'] = true;
        $bindings = $body['results']['bindings'] ?? [];
        $out['classes']['rico:' . $cls] = (int) ($bindings[0]['c']['value'] ?? 0);
    }
    return $out;
}

function httpJson(string $url, int $timeoutSec = 5): ?array
{
    $cmd = sprintf('curl -sk --max-time %d %s 2>/dev/null', $timeoutSec, escapeshellarg($url));
    $body = shell_exec($cmd);
    if ($body === null || $body === '') return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}

function sparqlSelect(string $endpoint, string $sparql, int $timeoutSec = 30): ?array
{
    $cmd = sprintf(
        'curl -sk --max-time %d -H %s -H %s --data-urlencode %s %s/query 2>/dev/null',
        $timeoutSec,
        escapeshellarg('Accept: application/sparql-results+json'),
        escapeshellarg('Content-Type: application/x-www-form-urlencoded'),
        escapeshellarg('query=' . $sparql),
        escapeshellarg(rtrim($endpoint, '/'))
    );
    $body = shell_exec($cmd);
    if ($body === null || $body === '') return null;
    $j = json_decode($body, true);
    return is_array($j) ? $j : null;
}
