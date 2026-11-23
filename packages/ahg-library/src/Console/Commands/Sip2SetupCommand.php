<?php

/**
 * Sip2SetupCommand - creates or updates a SIP2 terminal account, or sets a
 * patron's PIN (#1475). Secrets are prompted for, never taken from argv.
 *
 * Copyright (C) 2026 Johan Pieterse
 * License: AGPL-3.0-or-later
 */

namespace AhgLibrary\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Sip2SetupCommand extends Command
{
    protected $signature = 'ahg:library-sip2-setup
        {action : account or pin}
        {name : Terminal login, or patron card number}
        {--institution= : Institution id the terminal reports (account)}
        {--branch= : Branch repository id (account)}
        {--user= : Staff user id recorded on transactions (account)}
        {--allow-block : Terminal may block patrons (account)}
        {--allow-pay : Terminal may record fee payments (account)}
        {--no-pin : Terminal does not require a patron PIN (account)}';

    protected $description = 'Create a SIP2 terminal account or set a patron PIN';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'account' => $this->account((string) $this->argument('name')),
            'pin' => $this->pin((string) $this->argument('name')),
            default => $this->refuse('Action must be account or pin.'),
        };
    }

    private function account(string $login): int
    {
        $institution = trim((string) $this->option('institution'));
        if (! preg_match('/^[\w.-]{1,64}$/', $login) || $institution === '' || strlen($institution) > 64 || str_contains($institution, '|')) {
            return $this->refuse('A valid login and --institution are required.');
        }
        foreach (['branch', 'user'] as $opt) {
            if ($this->option($opt) !== null && ! ctype_digit((string) $this->option($opt))) {
                return $this->refuse("--{$opt} must be a number.");
            }
        }

        $password = (string) $this->secret('Terminal password');
        if (strlen($password) < 12 || str_contains($password, '|')) {
            return $this->refuse('The password needs at least 12 characters and no | character.');
        }

        DB::table('library_sip2_account')->updateOrInsert(['login' => $login], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'institution_id' => $institution,
            'branch_id' => $this->option('branch'),
            'user_id' => $this->option('user'),
            'require_patron_pin' => $this->option('no-pin') ? 0 : 1,
            'can_block' => $this->option('allow-block') ? 1 : 0,
            'can_pay' => $this->option('allow-pay') ? 1 : 0,
            'updated_at' => now(),
        ]);
        $this->info("Terminal account {$login} saved.");

        return self::SUCCESS;
    }

    private function pin(string $card): int
    {
        $patronId = DB::table('library_patron')->where('card_number', $card)->value('id');
        if (! $patronId) {
            return $this->refuse('No patron has that card number.');
        }

        $pin = (string) $this->secret('New PIN');
        if (! preg_match('/^\d{4,12}$/', $pin)) {
            return $this->refuse('A PIN is 4 to 12 digits.');
        }

        DB::table('library_patron_pin')->updateOrInsert(
            ['patron_id' => $patronId],
            ['pin_hash' => password_hash($pin, PASSWORD_DEFAULT), 'updated_at' => now()]
        );
        $this->info('PIN saved.');

        return self::SUCCESS;
    }

    private function refuse(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
