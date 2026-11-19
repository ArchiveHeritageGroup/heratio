<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1769 / #1770: user types decide rights and prices.
 *  - research_researcher_type: the email domains that verify a type, its
 *    discount and whether it has free access; a "School" type.
 *  - research_researcher: the institutional email confirmation.
 *  - subscription tiers that grant a type for a period, and users' subscriptions.
 *  - ahg_order: the subscription an order pays for.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('research_researcher_type')) {
            Schema::table('research_researcher_type', function (Blueprint $t) {
                $add = fn (string $c) => ! Schema::hasColumn('research_researcher_type', $c);
                if ($add('email_domains')) {
                    $t->string('email_domains', 500)->nullable();
                }
                if ($add('discount_percent')) {
                    $t->decimal('discount_percent', 5, 2)->default(0);
                }
                if ($add('free_access')) {
                    $t->boolean('free_access')->default(false);
                }
            });
            if (! DB::table('research_researcher_type')->where('code', 'school')->exists()) {
                DB::table('research_researcher_type')->insert([
                    'name' => 'School learner or educator', 'code' => 'school',
                    'description' => 'Learners and teachers at schools. Free access to reproductions for education; confirmed by a school email address.',
                    'max_booking_days_advance' => 14, 'max_booking_hours_per_day' => 4, 'max_materials_per_booking' => 10,
                    'can_remote_access' => 0, 'can_request_reproductions' => 1, 'can_export_data' => 0,
                    'requires_id_verification' => 0, 'auto_approve' => 1, 'expiry_months' => 12, 'priority_level' => 4,
                    'is_active' => 1, 'sort_order' => 15, 'free_access' => 1, 'discount_percent' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        if (Schema::hasTable('research_researcher')) {
            Schema::table('research_researcher', function (Blueprint $t) {
                if (! Schema::hasColumn('research_researcher', 'email_verify_token')) {
                    $t->string('email_verify_token', 64)->nullable()->index();
                }
                if (! Schema::hasColumn('research_researcher', 'email_verified_at')) {
                    $t->dateTime('email_verified_at')->nullable();
                }
            });
        }

        if (! Schema::hasTable('ahg_subscription_tier')) {
            Schema::create('ahg_subscription_tier', function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 150);
                $t->text('description')->nullable();
                $t->unsignedInteger('researcher_type_id');
                $t->decimal('price', 10, 2)->default(0);
                $t->unsignedSmallInteger('period_months')->default(12);
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('sort_order')->default(100);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ahg_user_subscription')) {
            Schema::create('ahg_user_subscription', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('user_id')->index();
                $t->unsignedInteger('tier_id');
                $t->string('status', 20)->default('pending');
                $t->dateTime('starts_at')->nullable();
                $t->dateTime('ends_at')->nullable();
                $t->unsignedInteger('order_id')->nullable();
                $t->timestamps();
            });
        }
        if (Schema::hasTable('ahg_order') && ! Schema::hasColumn('ahg_order', 'subscription_id')) {
            Schema::table('ahg_order', function (Blueprint $t) {
                $t->unsignedInteger('subscription_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Additive only; nothing to undo safely.
    }
};
