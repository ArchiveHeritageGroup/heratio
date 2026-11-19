<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1775: a release download link belongs to one user and one record's
 * original. ahg_cart_downloads held only a token and an expiry, so the download
 * route could never find a file. Adds who the link is for, what it releases,
 * why (paid order item or approved access request), a count and revocation.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'user_id' => 'unsignedInteger',
        'information_object_id' => 'unsignedInteger',
        'digital_object_id' => 'unsignedInteger',
        'order_item_id' => 'unsignedInteger',
        'access_request_id' => 'unsignedInteger',
        'download_count' => 'unsignedInteger',
        'revoked_at' => 'dateTime',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('ahg_cart_downloads')) {
            return;
        }
        Schema::table('ahg_cart_downloads', function (Blueprint $t) {
            foreach (self::COLUMNS as $col => $type) {
                if (Schema::hasColumn('ahg_cart_downloads', $col)) {
                    continue;
                }
                $c = $t->{$type}($col);
                $col === 'download_count' ? $c->default(0) : $c->nullable();
            }
        });
        Schema::table('ahg_cart_downloads', function (Blueprint $t) {
            if (! $this->hasIndex('ahg_cart_downloads', 'ahg_cart_downloads_token_index')) {
                $t->index('token');
            }
            if (! $this->hasIndex('ahg_cart_downloads', 'ahg_cart_downloads_user_id_index')) {
                $t->index('user_id');
            }
        });
    }

    private function hasIndex(string $table, string $name): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($i) => $i['name'] === $name);
    }

    public function down(): void
    {
        // Additive only; nothing to undo safely.
    }
};
