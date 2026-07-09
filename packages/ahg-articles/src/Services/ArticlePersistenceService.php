<?php

/**
 * ArticlePersistenceService - keeps flagged articles (and every article's read
 * count) alive across the nightly demo DB reset.
 *
 * The demo box runs heratio-demo-reset.sh at 02:00, which restores the whole
 * `heratio` DB from a fixed baseline snapshot. That baseline predates any article
 * written since it was taken, so without this service the reset (a) deletes
 * post-baseline articles outright and (b) rolls every article's view_count back
 * to the baseline value.
 *
 * The fix is a capture/apply pair driven by two crons that bracket the reset:
 *   - capture() at ~01:50 reads the LIVE db and writes a durable JSON state file
 *     on disk (storage survives the DB wipe): full rows for protected articles
 *     plus their attachments and comments, and the current view_count of EVERY
 *     article.
 *   - apply() at ~02:10 re-adds the protect_from_reset column if the baseline
 *     restore dropped it, re-inserts the protected articles and their children,
 *     and restores every article's captured read count.
 *
 * The capture is schema-agnostic: it re-inserts whatever columns it read, so a
 * later column addition needs no change here.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgArticles\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ArticlePersistenceService
{
    /** Directory + file that hold the pre-reset snapshot (under storage, so it survives the DB wipe). */
    private function stateFile(): string
    {
        return storage_path('app/demo-extras/blog-state.json');
    }

    /**
     * Snapshot the live DB to disk. Returns a summary count array.
     * Safe to run any time; overwrites the previous snapshot.
     */
    public function capture(): array
    {
        if (! Schema::hasTable('blog_post')) {
            return ['protected' => 0, 'view_counts' => 0, 'captured' => false];
        }

        $protected = Schema::hasColumn('blog_post', 'protect_from_reset')
            ? DB::table('blog_post')->where('protect_from_reset', 1)->get()
            : collect();

        $ids = $protected->pluck('id')->all();

        $attachments = ($ids && Schema::hasTable('blog_attachment'))
            ? DB::table('blog_attachment')->whereIn('blog_post_id', $ids)->get()
            : collect();

        $comments = ($ids && Schema::hasTable('blog_comment'))
            ? DB::table('blog_comment')->whereIn('blog_post_id', $ids)->get()
            : collect();

        // Read counts for EVERY article, protected or not - analytics must not
        // vanish on demo/baseline articles either.
        $viewCounts = DB::table('blog_post')->pluck('view_count', 'id')->all();

        // heratio#1399 — persist article cross-links through the reset too.
        $links = Schema::hasTable('blog_post_link')
            ? DB::table('blog_post_link')->get()->map(fn ($r) => (array) $r)->all()
            : [];

        // Per-attachment presentation metadata (section grouping, drag order,
        // description) for EVERY article - not just protected ones. The baseline
        // restore keeps the files but drops these edits; we overlay them back by
        // id in apply(). This makes grouping/reorder stick on demo articles too,
        // matching how links persist globally.
        $hasGroup = Schema::hasTable('blog_attachment') && Schema::hasColumn('blog_attachment', 'group_label');
        $attachmentMeta = Schema::hasTable('blog_attachment')
            ? DB::table('blog_attachment')->get()->map(fn ($r) => [
                'id'          => $r->id,
                'group_label' => $hasGroup ? ($r->group_label ?? null) : null,
                'sort_order'  => $r->sort_order ?? 0,
                'description' => $r->description ?? null,
            ])->all()
            : [];

        $state = [
            'captured_at'     => now()->toIso8601String(),
            'posts'           => $protected->map(fn ($r) => (array) $r)->all(),
            'attachments'     => $attachments->map(fn ($r) => (array) $r)->all(),
            'attachment_meta' => $attachmentMeta,
            'comments'        => $comments->map(fn ($r) => (array) $r)->all(),
            'links'           => $links,
            'view_counts'     => $viewCounts,
        ];

        File::ensureDirectoryExists(dirname($this->stateFile()));
        File::put($this->stateFile(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return [
            'protected'   => count($state['posts']),
            'attachments' => count($state['attachments']),
            'comments'    => count($state['comments']),
            'view_counts' => count($state['view_counts']),
            'captured'    => true,
        ];
    }

    /**
     * Re-apply the last snapshot after a reset. Idempotent: re-runnable without
     * duplicating rows (updateOrInsert on posts; delete-then-insert children).
     */
    public function apply(): array
    {
        if (! File::exists($this->stateFile()) || ! Schema::hasTable('blog_post')) {
            return ['restored' => 0, 'view_counts' => 0, 'applied' => false];
        }

        $state = json_decode((string) File::get($this->stateFile()), true);
        if (! is_array($state)) {
            return ['restored' => 0, 'view_counts' => 0, 'applied' => false];
        }

        // The baseline restore recreates blog_post from the old schema, so the
        // protect flag column may be gone - re-add it before we write the flag.
        if (! Schema::hasColumn('blog_post', 'protect_from_reset')) {
            Schema::table('blog_post', function ($t) {
                $t->boolean('protect_from_reset')->default(false)->after('view_count');
            });
        }

        // heratio#1399 — (re)create the cross-link table BEFORE the transaction:
        // a CREATE TABLE inside a transaction implicit-commits in MySQL and would
        // break the surrounding DB::transaction().
        \AhgArticles\Services\BlogLinkService::ensureLinkTable();
        // Re-add the attachment "section" column dropped by the baseline restore,
        // so re-inserting captured attachments (which carry group_label) succeeds.
        \AhgArticles\Services\BlogService::ensureAttachmentGroupColumn();

        $restored = 0;
        DB::transaction(function () use ($state, &$restored) {
            foreach (($state['posts'] ?? []) as $row) {
                if (! isset($row['id'])) {
                    continue;
                }
                DB::table('blog_post')->updateOrInsert(['id' => $row['id']], $row);
                $restored++;

                // Rebuild this post's children from the snapshot.
                if (Schema::hasTable('blog_attachment')) {
                    DB::table('blog_attachment')->where('blog_post_id', $row['id'])->delete();
                }
                if (Schema::hasTable('blog_comment')) {
                    DB::table('blog_comment')->where('blog_post_id', $row['id'])->delete();
                }
            }

            foreach (($state['attachments'] ?? []) as $row) {
                if (Schema::hasTable('blog_attachment')) {
                    DB::table('blog_attachment')->insert($row);
                }
            }
            foreach (($state['comments'] ?? []) as $row) {
                if (Schema::hasTable('blog_comment')) {
                    DB::table('blog_comment')->insert($row);
                }
            }

            // heratio#1399 — restore article cross-links (table already ensured
            // above, outside the transaction; here just upsert the rows).
            foreach (($state['links'] ?? []) as $row) {
                if (! isset($row['post_id'], $row['related_post_id'])) {
                    continue;
                }
                $vals = ['created_at' => $row['created_at'] ?? now()];
                // Restore the link's order + description too (columns ensured above).
                if (array_key_exists('sort_order', $row)) {
                    $vals['sort_order'] = $row['sort_order'];
                }
                if (array_key_exists('description', $row)) {
                    $vals['description'] = $row['description'];
                }
                DB::table('blog_post_link')->updateOrInsert(
                    ['post_id' => $row['post_id'], 'related_post_id' => $row['related_post_id']],
                    $vals
                );
            }

            // Overlay per-attachment grouping/order/description onto the
            // baseline-restored files (every article, keyed by id). Only touches
            // rows that still exist post-reset, so it never resurrects a deleted
            // file - it just re-applies the presentation edits the reset wiped.
            $hasGroup = Schema::hasColumn('blog_attachment', 'group_label');
            foreach (($state['attachment_meta'] ?? []) as $m) {
                if (! isset($m['id'])) {
                    continue;
                }
                $upd = [
                    'sort_order'  => $m['sort_order'] ?? 0,
                    'description' => $m['description'] ?? null,
                ];
                if ($hasGroup) {
                    $upd['group_label'] = $m['group_label'] ?? null;
                }
                DB::table('blog_attachment')->where('id', $m['id'])->update($upd);
            }
        });

        // Restore read counts for every article that still exists post-reset.
        $vc = 0;
        foreach (($state['view_counts'] ?? []) as $id => $count) {
            $vc += DB::table('blog_post')->where('id', $id)->update(['view_count' => $count]);
        }

        return ['restored' => $restored, 'view_counts' => $vc, 'applied' => true];
    }
}
