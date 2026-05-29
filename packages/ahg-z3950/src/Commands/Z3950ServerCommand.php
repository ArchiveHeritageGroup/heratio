<?php

/**
 * Z3950ServerCommand — Start the Z39.50 server daemon.
 *
 * Usage:
 *   php artisan z3950:server [--host=HOST] [--port=PORT] [--timeout=SECONDS]
 *
 * The daemon:
 *   - Binds to the specified host:port (default 0.0.0.0:210)
 *   - Accepts concurrent connections via a select()-loop
 *   - Handles INIT / SEARCH / PRESENT / DELETE / CLOSE APDUs
 *   - Logs all requests to library_z3950_server_request
 *
 * Run as a systemd service for production use:
 *   sudo tee /etc/systemd/system/z3950-server.service <<'EOF'
 *   [Unit]
 *   Description=Heratio Z39.50 Server
 *   After=network.target
 *
 *   [Service]
 *   Type=simple
 *   User=www-data
 *   WorkingDirectory=/usr/share/nginx/heratio
 *   ExecStart=/usr/bin/php artisan z3950:server --port=210
 *   Restart=on-failure
 *   RestartSec=10s
 *   StandardOutput=journal
 *   StandardError=journal
 *
 *   [Install]
 *   WantedBy=multi-user.target
 *   EOF
 *
 *   sudo systemctl daemon-reload
 *   sudo systemctl enable --now z3950-server
 *
 * Copyright (C) 2026 Johan Pieterse
 * The Archive Heritage Group (Pty) Ltd — AGPL-3.0
 */

namespace AhgZ3950\Commands;

use AhgZ3950\Services\Z3950ServerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class Z3950ServerCommand extends Command
{
    protected $signature = 'z3950:server
        {--host=0.0.0.0 : Host to bind to}
        {--port=210 : TCP port to listen on}
        {--timeout=30 : Per-client socket timeout in seconds}';

    protected $description = 'Start the Z39.50 bibliographic server daemon (ISO 23950)';

    public function handle(Z3950ServerService $server): int
    {
        $host    = (string) $this->option('host');
        $port    = (int) $this->option('port');
        $timeout = (int) $this->option('timeout');

        if ($port < 1 || $port > 65535) {
            $this->error("Port must be between 1 and 65535, got {$port}.");
            return self::FAILURE;
        }

        if ($timeout < 1 || $timeout > 3600) {
            $this->error("Timeout must be between 1 and 3600 seconds.");
            return self::FAILURE;
        }

        // Check for privileged port requirement
        if ($port < 1024 && posix_geteuid() !== 0) {
            $this->warn("Port {$port} is privileged. Running as root is not recommended.");
        }

        // Check pcntl extension
        if (! function_exists('pcntl_signal')) {
            $this->error('The pcntl extension is required for the Z39.50 server daemon.');
            $this->line('Install it with: apt-get install php-cli  (or enable the pcntl extension)');
            return self::FAILURE;
        }

        $this->info("Starting Z39.50 server on {$host}:{$port}");
        $this->line('  Press Ctrl+C to stop.');
        $this->newLine();

        Log::info("[Z39.50 Server Command] Starting daemon on {$host}:{$port}");

        try {
            $server->run($host, $port, $timeout);
        } catch (\Throwable $e) {
            $this->error("Server error: {$e->getMessage()}");
            Log::error("[Z39.50 Server] Fatal error: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
