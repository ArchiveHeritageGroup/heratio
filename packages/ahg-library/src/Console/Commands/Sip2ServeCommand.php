<?php

/**
 * Sip2ServeCommand - the SIP2 socket listener for self-check terminals, or
 * with --feed a one-shot run of messages from a file (#1475).
 *
 * ponytail: SIP2 is cleartext and this has no TLS, so it binds to loopback by
 * default. Reach it from a terminal VLAN or through a TLS tunnel.
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use AhgLibrary\Services\Sip2\Sip2Handler;
use AhgLibrary\Services\Sip2\Sip2Message;
use AhgLibrary\Services\Sip2\Sip2Session;
use Illuminate\Console\Command;

class Sip2ServeCommand extends Command
{
    protected $signature = 'ahg:library-sip2-serve
        {--host=127.0.0.1 : Address to bind}
        {--port=6001 : Port to bind}
        {--max-clients=32 : Most simultaneous terminals}
        {--idle=300 : Seconds before an idle terminal is disconnected}
        {--feed= : Answer the CR or newline separated messages in this file, then exit}';

    protected $description = 'Serve the SIP2 self-check protocol, or answer messages from a file with --feed';

    private bool $running = true;

    public function handle(Sip2Handler $handler): int
    {
        if ($this->option('feed') !== null) {
            return $this->feed($handler, (string) $this->option('feed'));
        }

        $host = (string) $this->option('host');
        $port = (int) $this->option('port');
        if (filter_var($host, FILTER_VALIDATE_IP) === false || $port < 1 || $port > 65535) {
            $this->error('Invalid host or port.');

            return self::FAILURE;
        }

        $server = @stream_socket_server("tcp://{$host}:{$port}", $errno, $error);
        if ($server === false) {
            $this->error("Could not bind {$host}:{$port}: {$error}");

            return self::FAILURE;
        }
        stream_set_blocking($server, false);

        if (! in_array($host, ['127.0.0.1', '::1'], true)) {
            $this->warn('SIP2 sends passwords and PINs in clear text. Restrict this port to the terminal network.');
        }
        $this->info("SIP2 listening on {$host}:{$port}");

        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn () => $this->running = false);
            pcntl_signal(SIGINT, fn () => $this->running = false);
        }

        $this->loop($server, $handler, max(1, (int) $this->option('max-clients')), max(1, (int) $this->option('idle')));
        fclose($server);

        return self::SUCCESS;
    }

    private function loop($server, Sip2Handler $handler, int $maxClients, int $idle): void
    {
        $clients = [];

        while ($this->running) {
            $read = array_merge([$server], array_column($clients, 'socket'));
            $write = $except = null;
            if (@stream_select($read, $write, $except, 5) === false) {
                continue;
            }

            foreach ($read as $socket) {
                if ($socket === $server) {
                    $client = @stream_socket_accept($server, 0);
                    if ($client === false) {
                        continue;
                    }
                    if (count($clients) >= $maxClients) {
                        fclose($client);
                        continue;
                    }
                    stream_set_blocking($client, false);
                    stream_set_timeout($client, 5);
                    $clients[(int) $client] = ['socket' => $client, 'buffer' => '', 'session' => new Sip2Session(), 'seen' => time()];
                    continue;
                }

                $id = (int) $socket;
                $chunk = fread($socket, 8192);
                if ($chunk === '' || $chunk === false) {
                    if (feof($socket) || $chunk === false) {
                        fclose($socket);
                        unset($clients[$id]);
                    }
                    continue;
                }

                $clients[$id]['seen'] = time();
                $clients[$id]['buffer'] .= $chunk;
                if (! $this->drain($clients[$id], $handler)) {
                    fclose($socket);
                    unset($clients[$id]);
                }
            }

            foreach ($clients as $id => $client) {
                if (time() - $client['seen'] > $idle) {
                    fclose($client['socket']);
                    unset($clients[$id]);
                }
            }
        }

        foreach ($clients as $client) {
            fclose($client['socket']);
        }
    }

    /** Answer every complete message in the buffer. False drops the connection. */
    private function drain(array &$client, Sip2Handler $handler): bool
    {
        while (preg_match('/^(.*?)[\r\n]+/s', $client['buffer'], $m)) {
            $client['buffer'] = substr($client['buffer'], strlen($m[0]));
            if ($m[1] === '') {
                continue;
            }
            if (! $this->writeAll($client['socket'], $handler->handle($m[1], $client['session']))) {
                return false;
            }
        }

        return strlen($client['buffer']) <= Sip2Message::MAX_LENGTH;
    }

    private function writeAll($socket, string $data): bool
    {
        $deadline = microtime(true) + 5;
        while ($data !== '') {
            $sent = @fwrite($socket, $data);
            if ($sent === false || microtime(true) > $deadline) {
                return false;
            }
            $data = substr($data, $sent);
            if ($sent === 0) {
                usleep(10000);
            }
        }

        return true;
    }

    private function feed(Sip2Handler $handler, string $path): int
    {
        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Cannot read the feed file.');

            return self::FAILURE;
        }

        $session = new Sip2Session();
        foreach (preg_split('/[\r\n]+/', (string) file_get_contents($path), -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $this->line(rtrim($handler->handle($line, $session), "\r"));
        }

        return self::SUCCESS;
    }
}
