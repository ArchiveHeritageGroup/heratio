<?php

/**
 * AssignGalleryItemsCommand — bulk-create marketplace listings for gallery
 * items, assigning them all to a single seller.
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

namespace AhgMarketplace\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssignGalleryItemsCommand extends Command
{
    protected $signature = 'marketplace:assign-gallery-items
        {--seller= : Seller id (defaults to seller record matching --email)}
        {--email=johan@theahg.co.za : Seller email when --seller is not given}
        {--identifier-prefix=GALLERY-DEMO- : IO identifier prefix to scope which items get assigned}
        {--status=active : Listing status (draft, pending_review, active)}
        {--force : Overwrite if a listing already exists for an IO}
        {--dry-run : Preview without writing}';

    protected $description = 'Create one marketplace listing per gallery IO under a single seller';

    public function handle(): int
    {
        $sellerId = (int) $this->option('seller');
        if (!$sellerId) {
            $email = $this->option('email');
            $row = DB::table('marketplace_seller')->where('email', $email)->first(['id', 'display_name']);
            if (!$row) {
                $this->error("No marketplace_seller row found for email {$email}. Pass --seller=<id> instead.");
                return 1;
            }
            $sellerId = (int) $row->id;
            $this->info("Resolved seller id {$sellerId} ({$row->display_name}) from email {$email}.");
        }

        $prefix = $this->option('identifier-prefix');
        $status = $this->option('status');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $items = DB::table('information_object as io')
            ->join('information_object_i18n as i18n', function ($j) {
                $j->on('i18n.id', '=', 'io.id')->where('i18n.culture', '=', 'en');
            })
            ->leftJoin('museum_metadata as mm', 'mm.object_id', '=', 'io.id')
            ->leftJoin('display_object_config as doc', 'doc.object_id', '=', 'io.id')
            ->leftJoin('digital_object as do_ref', function ($j) {
                $j->on('do_ref.object_id', '=', 'io.id')->where('do_ref.usage_id', '=', 141);
            })
            ->leftJoin('digital_object as do_master', function ($j) {
                $j->on('do_master.object_id', '=', 'io.id')->where('do_master.usage_id', '=', 140);
            })
            ->where('io.identifier', 'like', $prefix . '%')
            ->select([
                'io.id', 'io.identifier',
                'i18n.title', 'i18n.scope_and_content', 'i18n.extent_and_medium',
                'mm.materials', 'mm.dimensions', 'mm.creator_identity',
                'doc.object_type as glam_type',
                'do_ref.name as ref_filename',
                'do_master.name as master_filename',
                'do_master.mime_type as master_mime',
            ])
            ->orderBy('io.identifier')
            ->get();

        if ($items->isEmpty()) {
            $this->warn("No information objects found with identifier prefix '{$prefix}'");
            return 1;
        }

        $created = 0;
        $skipped = 0;
        $updated = 0;

        foreach ($items as $io) {
            $existing = DB::table('marketplace_listing')
                ->where('information_object_id', $io->id)
                ->first();

            if ($existing && !$force) {
                $this->line("[{$io->identifier}] already has listing #{$existing->id} — skipping");
                $skipped++;
                continue;
            }

            $sector = in_array($io->glam_type, ['gallery', 'museum', 'archive', 'library', 'dam'], true)
                ? $io->glam_type
                : 'gallery';

            $featuredPath = $io->ref_filename
                ? '/uploads/r/' . $io->id . '/' . $io->ref_filename
                : ($io->master_filename ? '/uploads/r/' . $io->id . '/' . $io->master_filename : null);

            $payload = [
                'seller_id'             => $sellerId,
                'information_object_id' => $io->id,
                'sector'                => $sector,
                'listing_type'          => 'fixed_price',
                'status'                => $status,
                'title'                 => $io->title ?: $io->identifier,
                'description'           => $io->scope_and_content,
                'medium'                => $io->materials ?: $io->extent_and_medium,
                'dimensions'            => $io->dimensions,
                'artist_name'           => $io->creator_identity,
                'currency'              => 'ZAR',
                'is_physical'           => 1,
                'requires_shipping'     => 1,
                'shipping_from_country' => 'South Africa',
                'featured_image_path'   => $featuredPath,
                'updated_at'            => now(),
            ];

            if ($dryRun) {
                $action = $existing ? 'OVERWRITE' : 'CREATE';
                $this->info("[{$io->identifier}] DRY RUN — would {$action} listing for '{$payload['title']}'");
                continue;
            }

            if ($existing) {
                DB::table('marketplace_listing')->where('id', $existing->id)->update($payload);
                $this->info("[{$io->identifier}] Updated listing #{$existing->id} (forced)");
                $updated++;
            } else {
                $payload['listing_number'] = $this->generateListingNumber();
                $payload['slug']           = $this->generateUniqueSlug($payload['title']);
                $payload['created_at']     = now();
                if ($status === 'published') {
                    $payload['listed_at'] = now();
                }

                $listingId = DB::table('marketplace_listing')->insertGetId($payload);

                // Attach the master DO as the primary listing image so the
                // listing show page renders correctly without a manual upload step.
                if ($io->master_filename) {
                    DB::table('marketplace_listing_image')->insert([
                        'listing_id' => $listingId,
                        'file_path'  => '/uploads/r/' . $io->id . '/' . $io->master_filename,
                        'file_name'  => $io->master_filename,
                        'mime_type'  => $io->master_mime,
                        'is_primary' => 1,
                        'sort_order' => 0,
                        'created_at' => now(),
                    ]);
                }

                $this->info("[{$io->identifier}] Created listing #{$listingId} ({$payload['listing_number']})");
                $created++;
            }
        }

        $this->newLine();
        $this->info('=== Assign summary ===');
        $this->line("Seller:  {$sellerId}");
        $this->line("Created: {$created}");
        $this->line("Updated: {$updated}");
        $this->line("Skipped: {$skipped}");

        return 0;
    }

    private function generateListingNumber(): string
    {
        $date = date('Ymd');
        $last = DB::table('marketplace_listing')
            ->where('listing_number', 'LIKE', 'MKT-' . $date . '-%')
            ->orderByDesc('id')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->listing_number);
            $seq = (int) end($parts) + 1;
        }
        return sprintf('MKT-%s-%04d', $date, $seq);
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'listing';
        $slug = $base;
        $i = 1;
        while (DB::table('marketplace_listing')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
