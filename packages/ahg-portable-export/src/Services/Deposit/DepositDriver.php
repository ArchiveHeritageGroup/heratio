<?php

namespace AhgPortableExport\Services\Deposit;

interface DepositDriver
{
    /**
     * Deposit a frozen release. Safe to call again after a partial failure:
     * $deposit->external_id holds what an earlier attempt created, and
     * $remember is called as soon as a new external id exists.
     *
     * @param  array{package:string, manifest:string, changelog:string, base_name:string}  $files
     * @param  array  $metadata  title, creator, description, version, doi, concept_doi, previous_doi, license, date
     * @return array{external_id:string, external_url:?string}
     */
    public function deposit(object $target, object $deposit, array $files, array $metadata, callable $remember): array;

    /**
     * Remove an unpublished deposit left by a failed attempt. Must throw a
     * ReleaseException if the deposit is already public or its state is unknown.
     */
    public function discard(object $target, object $deposit): void;
}
