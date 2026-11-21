<?php

/**
 * LiveStreamService - live events on a record (heratio#1743, GCIS 5.1.1).
 *
 * Ingest: ffmpeg is its own RTMP server (-listen 1). The worker
 * (ahg:live-ingest, or RunLiveIngestJob from the event page) listens on the
 * configured port for rtmp://<host>:<port>/live/<key>, copies the incoming
 * stream - no re-encoding - into rolling live HLS (6 s segments, the newest
 * ten kept, delete_segments+append_list) and, in parallel, into a full
 * Matroska recording. Matroska is used for the recording because a file cut
 * short (the worker killed) still plays up to the cut.
 *
 * The stream key: ffmpeg's listener does not refuse a stream published under
 * another name - it only warns "Unexpected stream X, expecting Y". The worker
 * watches for that warning, drops the connection and its files, and listens
 * again, so only the event's own key goes live.
 *
 * Status: scheduled -> live when the first frames arrive -> ended when the
 * source stops (or staff stop it, or live_max_hours passes). failed when no
 * encoder arrives within live_wait_minutes, or the worker dies (a heartbeat
 * older than HEARTBEAT_STALE seconds). One worker per port: a second event on
 * a port another live worker holds is refused.
 *
 * Archive: when the event ends the recording is remuxed to MP4 (faststart,
 * still no re-encoding) and goes through DigitalObjectService::upload() - the
 * normal create path - as the record's master, so its derivatives, playback
 * copy and HLS renditions are made the usual way. A record that already has a
 * master gets it as an attached object (AttachedDigitalObjectService, which
 * also uses upload()).
 *
 * Viewing: the live HLS is served only by the access-checked playlist route.
 * Viewers pass MediaProtection::mayPlay() for the record (publication,
 * ICIP/TK, embargo, classification, tenant, PII screening, release clearance;
 * staff pass) plus the ODRL policy on the record. Live segments are NOT
 * encrypted (media_hls_encrypt applies to stored video only); the route's
 * access check is what protects them.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AhgMediaStreaming\Services;

use AhgCore\Services\AclService;
use AhgCore\Services\AhgSettingsService;
use AhgCore\Services\AttachedDigitalObjectService;
use AhgCore\Services\DigitalObjectService;
use AhgCore\Services\MediaProtection;
use AhgCore\Support\AuditLog;
use AhgMediaStreaming\Jobs\RunLiveIngestJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LiveStreamService
{
    public const TABLE = 'live_event';

    public const SCHEDULED = 'scheduled';

    public const LIVE = 'live';

    public const ENDED = 'ended';

    public const FAILED = 'failed';

    /** Defaults; the labels shown come from ahg_dropdown 'live_event_status'. */
    public const STATUS_LABELS = [
        self::SCHEDULED => 'Scheduled',
        self::LIVE => 'Live',
        self::ENDED => 'Ended',
        self::FAILED => 'Failed',
    ];

    /** A worker that has not written its heartbeat for this long is gone. */
    public const HEARTBEAT_STALE = 30;

    /** Rolling live HLS: segment length (s) and segments kept in the playlist. */
    public const SEGMENT_SECONDS = 6;

    public const LIST_SIZE = 10;

    /** Files the playlist route serves. */
    public const HLS_FILE = '/^(index\.m3u8|seg\d{5}\.ts)$/';

    /** Set by a SIGTERM/SIGINT while run() is working: stop the stream cleanly. */
    private bool $signalled = false;

    // ---------------------------------------------------------------
    // Settings and paths
    // ---------------------------------------------------------------

    public function available(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    public function port(): int
    {
        $p = (int) AhgSettingsService::get('live_ingest_port', '1935');

        return $p >= 1 && $p <= 65535 ? $p : 1935;
    }

    public function host(): string
    {
        $h = trim((string) AhgSettingsService::get('live_ingest_host', ''));
        if ($h === '') {
            $h = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: request()->getHost());
        }

        return $h;
    }

    public function waitSeconds(): int
    {
        return 60 * max(1, (int) (AhgSettingsService::get('live_wait_minutes', '60') ?: 60));
    }

    public function maxSeconds(): int
    {
        return 3600 * max(1, min(12, (int) (AhgSettingsService::get('live_max_hours', '8') ?: 8)));
    }

    public function queue(): string
    {
        return trim((string) AhgSettingsService::get('live_queue', 'live')) ?: 'live';
    }

    /** The live folder; refused when it lies inside the uploads path (never serve live files as uploads). */
    public function root(): string
    {
        $root = rtrim((string) config('heratio.live_path', storage_path('app/live')), '/');
        $uploads = rtrim((string) config('heratio.uploads_path', ''), '/');
        if ($root === '' || ($uploads !== '' && ($root === $uploads || str_starts_with($root.'/', $uploads.'/')))) {
            throw new \RuntimeException('heratio.live_path must be outside the uploads path.');
        }

        return $root;
    }

    public function dir(object $e): string
    {
        return $this->root().'/'.(int) $e->id;
    }

    public function hlsDir(object $e): string
    {
        return $this->dir($e).'/hls';
    }

    public function recordingFile(object $e): string
    {
        return $this->dir($e).'/recording.mkv';
    }

    /** What to paste into the encoder: server URL, stream key, and the two joined. */
    public function ingest(object $e): array
    {
        $server = 'rtmp://'.$this->host().':'.$this->port().'/live';

        return ['server' => $server, 'key' => $e->stream_key, 'url' => $server.'/'.$e->stream_key];
    }

    public function statusLabels(): array
    {
        $labels = array_map(fn ($l) => __($l), self::STATUS_LABELS);
        if (Schema::hasTable('ahg_dropdown')) {
            foreach (DB::table('ahg_dropdown')->where('taxonomy', 'live_event_status')->get(['code', 'label']) as $r) {
                $labels[$r->code] = __($r->label);
            }
        }

        return $labels;
    }

    // ---------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------

    public function find(int $id): ?object
    {
        return $this->available() ? DB::table(self::TABLE)->where('id', $id)->first() : null;
    }

    public function create(int $ioId, string $title, string $scheduledAt, ?string $description = null, ?int $userId = null): int
    {
        $now = now();
        $id = (int) DB::table(self::TABLE)->insertGetId([
            'information_object_id' => $ioId,
            'title' => mb_substr($title, 0, 255),
            'description' => $description !== null && $description !== '' ? $description : null,
            'scheduled_at' => $scheduledAt,
            'status' => self::SCHEDULED,
            'stream_key' => bin2hex(random_bytes(16)),
            'created_by' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        AuditLog::captureSecondaryMutation($ioId, 'information_object', 'live_event_created', ['live_event_id' => $id, 'title' => $title, 'scheduled_at' => $scheduledAt]);

        return $id;
    }

    /** Events, newest scheduled first, with the record title and slug. */
    public function list(?int $ioId = null, ?string $status = null, int $limit = 200)
    {
        if (! $this->available()) {
            return collect();
        }
        $this->reap();
        $q = DB::table(self::TABLE.' as e')
            ->leftJoin('information_object_i18n as i', fn ($j) => $j->on('i.id', '=', 'e.information_object_id')->where('i.culture', '=', 'en'))
            ->leftJoin('slug as s', 's.object_id', '=', 'e.information_object_id')
            ->select('e.*', 'i.title as record_title', 's.slug')
            ->orderByDesc('e.scheduled_at')->orderByDesc('e.id')->limit($limit);
        if ($ioId) {
            $q->where('e.information_object_id', $ioId);
        }
        if ($status) {
            $q->where('e.status', $status);
        }

        return $q->get();
    }

    /** The event the record page shows: the live one, else the next scheduled, else the latest ended. */
    public function forRecord(int $ioId): ?object
    {
        if (! $this->available()) {
            return null;
        }
        $this->reap();
        $q = fn () => DB::table(self::TABLE)->where('information_object_id', $ioId);

        return $q()->where('status', self::LIVE)->orderByDesc('started_at')->first()
            ?? $q()->where('status', self::SCHEDULED)->orderBy('scheduled_at')->first()
            ?? $q()->whereIn('status', [self::ENDED, self::FAILED])->orderByDesc('ended_at')->orderByDesc('id')->first();
    }

    /** A worker holds this event: claimed and its heartbeat is fresh. */
    public function workerActive(object $e): bool
    {
        return $e->worker_started_at && $e->heartbeat_at
            && strtotime((string) $e->heartbeat_at) >= time() - self::HEARTBEAT_STALE;
    }

    /** Another event whose live worker holds $port, or null. */
    public function activeOnPort(int $port, int $exceptId = 0): ?object
    {
        return DB::table(self::TABLE)->where('ingest_port', $port)->where('id', '!=', $exceptId)
            ->whereNotNull('worker_started_at')
            ->where('heartbeat_at', '>=', now()->subSeconds(self::HEARTBEAT_STALE))
            ->orderBy('worker_started_at')->first();
    }

    /** Events whose worker died while holding them: live ones become failed, waiting ones are released. */
    public function reap(): void
    {
        $stale = DB::table(self::TABLE)->whereNotNull('worker_started_at')
            ->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', now()->subSeconds(self::HEARTBEAT_STALE)))
            ->get();
        foreach ($stale as $e) {
            $upd = ['worker_started_at' => null, 'heartbeat_at' => null, 'worker_pid' => null, 'updated_at' => now()];
            if ($e->status === self::LIVE) {
                $upd += ['status' => self::FAILED, 'ended_at' => now(),
                    'error' => 'The ingest worker stopped without finishing. Any recording made so far is kept; run ahg:live-ingest '.$e->id.' --archive to add it to the record.'];
            }
            DB::table(self::TABLE)->where('id', $e->id)->update($upd);
        }
    }

    /** Staff start: queue the worker job. Returns an error message, or null. */
    public function requestStart(object $e, ?int $userId = null): ?string
    {
        if (! in_array($e->status, [self::SCHEDULED, self::FAILED], true)) {
            return __('Only a scheduled or failed event can be started.');
        }
        if ($this->workerActive($e)) {
            return __('The ingest worker is already running for this event.');
        }
        if ($this->leftover($e)) {
            return __('This event has a recording from an earlier run that is not yet on the record. Run ahg:live-ingest :id --archive first.', ['id' => $e->id]);
        }
        if ($other = $this->activeOnPort($this->port(), (int) $e->id)) {
            return __('Port :port is in use by live event #:id. One live event per port at a time.', ['port' => $this->port(), 'id' => $other->id]);
        }
        DB::table(self::TABLE)->where('id', $e->id)->update(['start_requested_at' => now(), 'stop_requested_at' => null, 'error' => null, 'updated_at' => now()]);
        RunLiveIngestJob::dispatch((int) $e->id, $userId)->onConnection('live')->onQueue($this->queue());
        AuditLog::captureSecondaryMutation((int) $e->information_object_id, 'information_object', 'live_event_started', ['live_event_id' => (int) $e->id]);

        return null;
    }

    /** Staff stop: the worker sees it within a second and ends the stream. */
    public function requestStop(object $e): void
    {
        DB::table(self::TABLE)->where('id', $e->id)->update(['stop_requested_at' => now(), 'start_requested_at' => null, 'updated_at' => now()]);
        AuditLog::captureSecondaryMutation((int) $e->information_object_id, 'information_object', 'live_event_stopped', ['live_event_id' => (int) $e->id]);
    }

    // ---------------------------------------------------------------
    // Access
    // ---------------------------------------------------------------

    /**
     * May the current user watch this event? The record's media rules: a
     * stand-in video master on the record goes through MediaProtection::mayPlay
     * (staff pass; publication, ICIP/TK, embargo, classification, tenant, PII
     * screening, release clearance), then the ODRL policy on the record itself
     * (mayPlay's ODRL check needs a stored master to find the record).
     */
    public function mayView(object $e, ?object $user = null): bool
    {
        $user = $user ?? auth()->user();
        $io = (int) $e->information_object_id;
        $standIn = (object) ['id' => 0, 'object_id' => $io, 'parent_id' => null, 'mime_type' => 'video/mp4', 'media_type_id' => DigitalObjectService::MEDIA_VIDEO];
        if (! MediaProtection::mayPlay($standIn, false, $user)) {
            return false;
        }
        if (MediaProtection::isStaff($user) || ! class_exists(\AhgResearch\Services\OdrlService::class)) {
            return true;
        }
        try {
            $researcher = $user && Schema::hasTable('research_researcher')
                ? DB::table('research_researcher')->where('user_id', $user->id)->value('id') : null;

            return app(\AhgResearch\Services\OdrlService::class)->isPermitted('archival_description', $io, $researcher !== null ? (int) $researcher : null, 'use');
        } catch (\Throwable) {
            return false; // fail closed, as mayPlay does
        }
    }

    public function mayManage(object $e, ?int $userId): bool
    {
        return $userId !== null && AclService::hasPermission($userId, 'update', (int) $e->information_object_id);
    }

    /** Absolute path of a live HLS file, or null when it is not there. */
    public function hlsPath(object $e, string $file): ?string
    {
        if (! preg_match(self::HLS_FILE, $file)) {
            return null;
        }
        $path = $this->hlsDir($e).'/'.$file;

        return is_file($path) ? $path : null;
    }

    // ---------------------------------------------------------------
    // The worker
    // ---------------------------------------------------------------

    /**
     * Listen for the event's stream and run it to the end; then archive the
     * recording. Blocks for the whole event. $say gets progress lines.
     * Returns the final status. Throws when the event cannot be started here.
     */
    public function run(int $id, ?int $port = null, ?callable $say = null): string
    {
        $say ??= fn (string $m) => null;
        $e = $this->find($id) ?? throw new \RuntimeException("Live event #$id not found.");
        $this->reap();
        $e = $this->find($id);
        if ($e->status === self::LIVE || $e->status === self::ENDED) {
            throw new \RuntimeException("Live event #$id is already {$e->status}.");
        }
        if ($this->workerActive($e)) {
            throw new \RuntimeException("Live event #$id already has an ingest worker.");
        }
        $port ??= $this->port();
        if ($other = $this->activeOnPort($port, $id)) {
            throw new \RuntimeException("Port $port is in use by live event #{$other->id}. One live event per port at a time.");
        }
        if ($this->leftover($e)) {
            throw new \RuntimeException("Live event #$id has a recording from an earlier run that is not yet on the record. Run ahg:live-ingest $id --archive first.");
        }
        $ffmpeg = trim((string) shell_exec('command -v ffmpeg'));
        if ($ffmpeg === '') {
            throw new \RuntimeException('ffmpeg is not installed.');
        }

        $this->touch($id, [
            'ingest_port' => $port, 'worker_started_at' => now(), 'heartbeat_at' => now(),
            'worker_host' => gethostname() ?: null, 'worker_pid' => getmypid() ?: null,
            'start_requested_at' => null, 'stop_requested_at' => null, 'error' => null,
            'status' => self::SCHEDULED, 'started_at' => null, 'ended_at' => null,
        ]);
        // Two workers claiming the same port at once: the earlier claim keeps it.
        if (($other = $this->activeOnPort($port, $id)) && $other->worker_started_at <= $this->find($id)->worker_started_at) {
            $this->release($id);
            throw new \RuntimeException("Port $port is in use by live event #{$other->id}. One live event per port at a time.");
        }

        $handlers = $this->trapSignals();
        try {
            return $this->listen($e, $port, $ffmpeg, $say);
        } finally {
            $this->release($id);
            $this->restoreSignals($handlers);
        }
    }

    private function listen(object $e, int $port, string $ffmpeg, callable $say): string
    {
        $id = (int) $e->id;
        $dir = $this->dir($e);
        $hls = $this->hlsDir($e);
        $rec = $this->recordingFile($e);
        $deadline = time() + $this->waitSeconds();
        $url = 'rtmp://0.0.0.0:'.$port.'/live/'.$e->stream_key;
        $say("Listening on rtmp://0.0.0.0:$port/live/<key> for live event #$id.");

        while (true) {
            $this->clean($dir);
            File::ensureDirectoryExists($hls, 0775);
            $wait = $deadline - time();
            if ($wait <= 0) {
                $this->clean($dir);

                return $this->finish($id, self::FAILED, ['error' => 'No stream arrived within '.intdiv($this->waitSeconds(), 60).' minutes.']);
            }
            $r = $this->ffmpeg([
                $ffmpeg, '-hide_banner', '-nostdin', '-nostats', '-loglevel', 'warning', '-progress', 'pipe:1',
                // Probe one second of the stream, not the default five, so HLS starts sooner.
                '-analyzeduration', '1000000', '-listen', '1', '-timeout', (string) $wait, '-i', $url,
                '-map', '0', '-c', 'copy', '-f', 'hls', '-hls_time', (string) self::SEGMENT_SECONDS,
                '-hls_list_size', (string) self::LIST_SIZE, '-hls_flags', 'delete_segments+append_list',
                '-hls_segment_filename', $hls.'/seg%05d.ts', $hls.'/index.m3u8',
                '-map', '0', '-c', 'copy', '-f', 'matroska', $rec,
            ], $id, $say);

            if ($r['wrongKey'] && ! $r['live']) {
                $say('Refused a stream published with another stream key; listening again.');
                continue;
            }
            if ($r['live']) {
                $this->finish($id, self::ENDED, ['ended_at' => now()]);
                $say("Live event #$id ended.");
                $this->archive($id, $say);

                return self::ENDED;
            }
            if ($r['stopped']) {
                $this->clean($dir);

                return $this->finish($id, self::SCHEDULED, []); // stopped while waiting: back to scheduled
            }

            $this->clean($dir);

            return $this->finish($id, self::FAILED, ['error' => $r['exit'] !== 0 && time() >= $deadline
                ? 'No stream arrived within '.intdiv($this->waitSeconds(), 60).' minutes.'
                : 'The ingest stopped before the stream went live: '.mb_substr(trim($r['stderr']), -500)]);
        }
    }

    /**
     * Run one ffmpeg listen and watch it: live on the first frame, the
     * heartbeat every few seconds, stop on request / signal / max duration,
     * and drop a stream with the wrong key.
     *
     * @return array{live: bool, wrongKey: bool, stopped: bool, exit: int, stderr: string}
     */
    private function ffmpeg(array $cmd, int $id, callable $say): array
    {
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($proc)) {
            throw new \RuntimeException('Could not start ffmpeg.');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $out = ['live' => false, 'wrongKey' => false, 'stopped' => false, 'exit' => -1, 'stderr' => ''];
        $buf = '';
        $beat = 0;
        $liveSince = null;
        $termAt = null;

        while (true) {
            $st = proc_get_status($proc);
            if (! $st['running']) {
                $out['exit'] = (int) $st['exitcode'];
                break;
            }
            $r = [$pipes[1], $pipes[2]];
            $w = $x = null;
            if (@stream_select($r, $w, $x, 1) > 0) {
                foreach ($r as $p) {
                    $chunk = (string) fread($p, 65536);
                    if ($p === $pipes[2]) {
                        $out['stderr'] = mb_substr($out['stderr'].$chunk, -4000);
                        if (! $out['live'] && str_contains($out['stderr'], 'Unexpected stream')) {
                            $out['wrongKey'] = true;
                            proc_terminate($proc, 9); // not ours: drop it, nothing is kept
                        }
                    } else {
                        $buf .= $chunk;
                    }
                }
            }
            if (! $out['live'] && ! $out['wrongKey'] && preg_match('/^(frame=[1-9]|out_time_us=[1-9])/m', $buf)) {
                $out['live'] = true;
                $liveSince = time();
                $this->touch($id, ['status' => self::LIVE, 'started_at' => now()]);
                $say("Live event #$id is live.");
            }
            $buf = substr($buf, -2000);

            if (time() - $beat >= 3) {
                $beat = time();
                $this->touch($id, ['heartbeat_at' => now()]);
                $stop = DB::table(self::TABLE)->where('id', $id)->value('stop_requested_at');
                $tooLong = $liveSince && time() - $liveSince >= $this->maxSeconds();
                if (($stop || $this->signalled || $tooLong) && $termAt === null) {
                    $out['stopped'] = true;
                    $termAt = time();
                    $say($tooLong ? 'Longest event length reached; stopping.' : 'Stop requested; stopping.');
                    proc_terminate($proc, 15); // ffmpeg finishes the playlist and the recording on SIGTERM
                }
            }
            if ($this->signalled && $termAt === null) {
                $out['stopped'] = true;
                $termAt = time();
                proc_terminate($proc, 15);
            }
            if ($termAt !== null && time() - $termAt > 15) {
                proc_terminate($proc, 9);
            }
        }
        $out['stderr'] = mb_substr($out['stderr'].stream_get_contents($pipes[2]), -4000);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        return $out;
    }

    // ---------------------------------------------------------------
    // Archive
    // ---------------------------------------------------------------

    /**
     * The recording becomes the record's digital object through the normal
     * create path. Returns the new master id. The event folder is removed
     * after the recording is stored.
     */
    public function archive(int $id, ?callable $say = null): int
    {
        $say ??= fn (string $m) => null;
        $e = $this->find($id) ?? throw new \RuntimeException("Live event #$id not found.");
        if ($e->recording_digital_object_id) {
            return (int) $e->recording_digital_object_id;
        }
        $rec = $this->recordingFile($e);
        if (! is_file($rec) || filesize($rec) === 0) {
            $this->touch($id, ['error' => 'No recording was written.']);
            throw new \RuntimeException("Live event #$id has no recording.");
        }

        // Remux to MP4 with the index at the front (no re-encoding); keep the
        // Matroska file when the remux does not work.
        $mp4 = $this->dir($e).'/recording.mp4';
        $cmd = 'ffmpeg -v error -y -i '.escapeshellarg($rec).' -map 0 -c copy -movflags +faststart '.escapeshellarg($mp4).' 2>&1';
        $err = (string) shell_exec($cmd);
        [$file, $ext, $mime] = is_file($mp4) && filesize($mp4) > 0 ? [$mp4, 'mp4', 'video/mp4'] : [$rec, 'mkv', 'video/x-matroska'];
        if ($file === $rec && $err !== '') {
            Log::warning('[live] MP4 remux failed for event '.$id.': '.trim($err));
        }

        $ioId = (int) $e->information_object_id;
        $base = preg_replace('/[^a-zA-Z0-9_-]+/', '_', mb_substr($e->title, 0, 60)) ?: 'event';
        $name = 'live_'.trim($base, '_').'_'.date('Ymd_His', strtotime((string) ($e->started_at ?: 'now'))).'.'.$ext;
        $upload = new UploadedFile($file, $name, $mime, null, true);

        $hasMaster = DB::table('digital_object')->where('object_id', $ioId)->where('usage_id', DigitalObjectService::USAGE_MASTER)->exists();
        if ($hasMaster && AttachedDigitalObjectService::available()) {
            $link = app(AttachedDigitalObjectService::class)->attach($ioId, $upload, __('Live recording').': '.$e->title, 'live_recording');
            $masterId = (int) DB::table(AttachedDigitalObjectService::TABLE)->where('id', $link)->value('digital_object_id');
        } else {
            $masterId = DigitalObjectService::upload($ioId, $upload);
        }
        $this->touch($id, ['recording_digital_object_id' => $masterId]);
        AuditLog::captureSecondaryMutation($ioId, 'information_object', 'live_event_archived', ['live_event_id' => $id, 'digital_object_id' => $masterId]);
        $say("Recording stored as digital object #$masterId on record #$ioId.");
        $this->clean($this->dir($e));

        return $masterId;
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** A recording left by a worker that died while live, not yet archived. */
    public function leftover(object $e): bool
    {
        return ! $e->recording_digital_object_id && is_file($this->recordingFile($e));
    }

    private function touch(int $id, array $row): void
    {
        DB::table(self::TABLE)->where('id', $id)->update($row + ['updated_at' => now()]);
    }

    private function finish(int $id, string $status, array $row): string
    {
        $this->touch($id, ['status' => $status] + $row);

        return $status;
    }

    private function release(int $id): void
    {
        $this->touch($id, ['worker_started_at' => null, 'heartbeat_at' => null, 'worker_pid' => null]);
    }

    /** Remove an event folder - only ever a numbered folder directly under the live root. */
    private function clean(string $dir): void
    {
        if (preg_match('#^'.preg_quote($this->root(), '#').'/\d+$#', $dir) && is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    /** SIGTERM / SIGINT (systemctl stop, Ctrl-C) end the stream cleanly instead of killing it mid-file. */
    private function trapSignals(): array
    {
        $this->signalled = false;
        if (! function_exists('pcntl_signal')) {
            return [];
        }
        $old = [];
        foreach ([SIGTERM, SIGINT] as $sig) {
            $old[$sig] = pcntl_signal_get_handler($sig);
            $prev = $old[$sig];
            pcntl_signal($sig, function (...$args) use ($prev) {
                $this->signalled = true;
                if (is_callable($prev)) {
                    $prev(...$args); // the queue worker's own handler: it quits after this job
                }
            });
        }
        $old['async'] = pcntl_async_signals(true);

        return $old;
    }

    private function restoreSignals(array $old): void
    {
        if (! $old) {
            return;
        }
        pcntl_async_signals((bool) $old['async']);
        foreach ([SIGTERM, SIGINT] as $sig) {
            pcntl_signal($sig, $old[$sig] ?? SIG_DFL);
        }
    }
}
