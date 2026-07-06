<?php

namespace AhgArticles\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * heratio#1399 — bidirectional cross-links between content articles (blog_post).
 * A link authored on one article surfaces as "Related" on both. Managed from
 * the article edit screen (searchable picker / paste-URL). Idempotent table
 * creation so it works on already-installed instances.
 */
class BlogLinkService
{
    public static function ensureLinkTable(): void
    {
        if (! Schema::hasTable('blog_post_link')) {
            Schema::create('blog_post_link', function ($t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('post_id');
                $t->unsignedBigInteger('related_post_id');
                $t->string('description', 500)->nullable();
                $t->integer('sort_order')->default(0);
                $t->timestamp('created_at')->nullable()->useCurrent();
                $t->unique(['post_id', 'related_post_id'], 'uq_blog_link');
                $t->index('related_post_id', 'idx_blog_link_related');
            });

            return;
        }
        // Idempotent column adds for instances created before these existed.
        if (! Schema::hasColumn('blog_post_link', 'sort_order')) {
            Schema::table('blog_post_link', fn ($t) => $t->integer('sort_order')->default(0));
        }
        if (! Schema::hasColumn('blog_post_link', 'description')) {
            Schema::table('blog_post_link', fn ($t) => $t->string('description', 500)->nullable());
        }
    }

    /**
     * Articles linked to $postId in EITHER direction (bidirectional), each
     * carrying the link's own description + sort_order. Ordered by the
     * user-set sort_order, then title as a stable tie-break.
     */
    public static function related(int $postId): array
    {
        if (! Schema::hasTable('blog_post_link')) {
            return [];
        }
        $hasOrder = Schema::hasColumn('blog_post_link', 'sort_order');
        $hasDesc  = Schema::hasColumn('blog_post_link', 'description');

        $rows = DB::table('blog_post_link')
            ->where('post_id', $postId)->orWhere('related_post_id', $postId)
            ->get();

        // The link row is shared by both directions; read its meta once per
        // linked article id.
        $meta = [];
        foreach ($rows as $r) {
            $otherId = ((int) $r->post_id === $postId) ? (int) $r->related_post_id : (int) $r->post_id;
            $meta[$otherId] = [
                'sort_order'  => $hasOrder ? (int) ($r->sort_order ?? 0) : 0,
                'description' => $hasDesc ? ($r->description ?? null) : null,
            ];
        }
        if (! $meta) {
            return [];
        }

        $posts = DB::table('blog_post')->whereIn('id', array_keys($meta))
            ->select('id', 'slug', 'title', 'status')->get()
            ->map(function ($r) use ($meta) {
                $a = (array) $r;
                $a['sort_order']  = $meta[(int) $r->id]['sort_order'] ?? 0;
                $a['description'] = $meta[(int) $r->id]['description'] ?? null;

                return $a;
            })->all();

        usort($posts, fn ($a, $b) => ($a['sort_order'] <=> $b['sort_order'])
            ?: strcasecmp((string) $a['title'], (string) $b['title']));

        return array_values($posts);
    }

    public static function add(int $postId, int $targetId, ?string $description = null): bool
    {
        self::ensureLinkTable();
        if ($postId <= 0 || $targetId <= 0 || $postId === $targetId) {
            return false;
        }

        $values = ['created_at' => now()];
        if (Schema::hasColumn('blog_post_link', 'description')) {
            $desc = $description !== null ? trim($description) : '';
            $values['description'] = $desc !== '' ? $desc : null;
        }
        // New links land at the end of the current order.
        if (Schema::hasColumn('blog_post_link', 'sort_order')) {
            $max = (int) DB::table('blog_post_link')
                ->where('post_id', $postId)->orWhere('related_post_id', $postId)
                ->max('sort_order');
            $values['sort_order'] = $max + 1;
        }

        DB::table('blog_post_link')->updateOrInsert(
            ['post_id' => $postId, 'related_post_id' => $targetId],
            $values
        );

        return true;
    }

    /**
     * Persist a new display order for $postId's linked articles. $orderedTargetIds
     * is the linked-article ids in the desired order; each link row (either
     * direction) gets its sort_order set to its index.
     */
    public static function reorder(int $postId, array $orderedTargetIds): void
    {
        if (! Schema::hasTable('blog_post_link') || ! Schema::hasColumn('blog_post_link', 'sort_order')) {
            return;
        }
        $i = 0;
        foreach ($orderedTargetIds as $targetId) {
            $targetId = (int) $targetId;
            if ($targetId <= 0) {
                continue;
            }
            DB::table('blog_post_link')
                ->where(fn ($q) => $q->where('post_id', $postId)->where('related_post_id', $targetId))
                ->orWhere(fn ($q) => $q->where('post_id', $targetId)->where('related_post_id', $postId))
                ->update(['sort_order' => $i]);
            $i++;
        }
    }

    public static function remove(int $postId, int $targetId): void
    {
        if (! Schema::hasTable('blog_post_link')) {
            return;
        }
        DB::table('blog_post_link')
            ->where(fn ($q) => $q->where('post_id', $postId)->where('related_post_id', $targetId))
            ->orWhere(fn ($q) => $q->where('post_id', $targetId)->where('related_post_id', $postId))
            ->delete();
    }

    /** Resolve an id from a slug, a /articles/{slug} path/URL, or an exact title. */
    public static function resolveId(string $ref): ?int
    {
        $ref = trim($ref);
        if ($ref === '') {
            return null;
        }
        if (preg_match('~/articles/([a-z0-9][a-z0-9\-]*)~i', $ref, $m)) {
            $ref = $m[1];
        }
        $id = (int) DB::table('blog_post')->where('slug', $ref)->value('id');
        if ($id === 0) {
            $id = (int) DB::table('blog_post')->where('title', $ref)->value('id');
        }

        return $id > 0 ? $id : null;
    }

    /** All other articles for the picker datalist. */
    public static function allForPicker(int $excludeId): array
    {
        return DB::table('blog_post')->where('id', '!=', $excludeId)
            ->select('id', 'slug', 'title')->orderBy('title')->limit(1000)
            ->get()->map(fn ($r) => (array) $r)->all();
    }
}
