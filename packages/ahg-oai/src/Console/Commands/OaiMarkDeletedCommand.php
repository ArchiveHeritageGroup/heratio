<?php

/**
 * OaiMarkDeletedCommand - Record an OAI tombstone for an IO that has been
 * removed or permanently unpublished.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace AhgOai\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OaiMarkDeletedCommand extends Command
{
    protected $signature = 'oai:mark-deleted
        {oai_local_identifier? : The oai_local_identifier of the record to tombstone}
        {--reason= : Optional reason text recorded with the tombstone}
        {--all-unpublished : Tombstone every IO that has an oai_local_identifier but is not currently published}
        {--list : Show current tombstones and exit (no writes)}';

    protected $description = 'Record an OAI-PMH tombstone so harvesters can clean up a deleted record.';

    public function handle(): int
    {
        if (!Schema::hasTable('oai_deleted_record')) {
            $this->error('oai_deleted_record table is missing. Run `php artisan migrate` first.');
            return self::FAILURE;
        }

        if ($this->option('list')) {
            return $this->listTombstones();
        }

        if ($this->option('all-unpublished')) {
            return $this->markAllUnpublished();
        }

        $id = $this->argument('oai_local_identifier');
        if ($id === null || $id === '') {
            $this->error('Provide an oai_local_identifier, or use --all-unpublished / --list.');
            return self::INVALID;
        }

        return $this->markSingle((int) $id, (string) $this->option('reason'));
    }

    private function listTombstones(): int
    {
        $rows = DB::table('oai_deleted_record')->orderBy('deleted_at', 'desc')->limit(50)->get();
        if ($rows->isEmpty()) {
            $this->info('No tombstones recorded.');
            return self::SUCCESS;
        }
        $this->table(['oai_local_id', 'deleted_at', 'reason'],
            $rows->map(fn ($r) => [$r->oai_local_identifier, $r->deleted_at, $r->reason ?? ''])->all());
        return self::SUCCESS;
    }

    private function markSingle(int $oaiLocalId, string $reason = ''): int
    {
        $existing = DB::table('oai_deleted_record')->where('oai_local_identifier', $oaiLocalId)->exists();
        if ($existing) {
            $this->warn("Tombstone for oai_local_identifier={$oaiLocalId} already exists; updating timestamp + reason.");
            DB::table('oai_deleted_record')
                ->where('oai_local_identifier', $oaiLocalId)
                ->update(['deleted_at' => now(), 'reason' => $reason ?: null]);
        } else {
            DB::table('oai_deleted_record')->insert([
                'oai_local_identifier' => $oaiLocalId,
                'deleted_at' => now(),
                'reason' => $reason ?: null,
            ]);
        }
        $this->info("Tombstone recorded for oai_local_identifier={$oaiLocalId}.");
        return self::SUCCESS;
    }

    /**
     * Mark every IO that has an oai_local_identifier set but is not currently
     * published as deleted. Idempotent — re-runs only add new tombstones.
     */
    private function markAllUnpublished(): int
    {
        // Published = status table row of type_id=158 (publication status) with status_id=160 (published).
        $candidates = DB::table('information_object as io')
            ->leftJoin('status as st', function ($j) {
                $j->on('st.object_id', '=', 'io.id')->where('st.type_id', '=', 158);
            })
            ->leftJoin('oai_deleted_record as dr', 'dr.oai_local_identifier', '=', 'io.oai_local_identifier')
            ->whereNotNull('io.oai_local_identifier')
            ->where(function ($q) {
                $q->whereNull('st.status_id')->orWhere('st.status_id', '!=', 160);
            })
            ->whereNull('dr.oai_local_identifier')
            ->select('io.oai_local_identifier')
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No unpublished records need tombstoning.');
            return self::SUCCESS;
        }

        $now = now();
        $inserts = $candidates->map(fn ($c) => [
            'oai_local_identifier' => $c->oai_local_identifier,
            'deleted_at' => $now,
            'reason' => 'unpublished',
        ])->all();
        DB::table('oai_deleted_record')->insert($inserts);
        $this->info('Recorded ' . count($inserts) . ' tombstones for unpublished records.');
        return self::SUCCESS;
    }
}
