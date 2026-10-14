<?php

/**
 * H09 acquisition tracking: one row per visit, click or enquiry on the demo
 * site, with where the visitor came from. No IP address, cookie, user agent or
 * personal detail is stored; a visit is tied to others only by a random id the
 * browser keeps for the tab session. Also seeds the contact_enquiry_type
 * dropdown that the contact form uses to say what an enquiry is about.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_acquisition_event')) {
            Schema::create('ahg_acquisition_event', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->dateTime('occurred_at');
                $t->string('event', 20);       // visit, demo, tour, docs, github, enquiry
                $t->string('source', 60);      // github, search, social, referral:<host>, campaign:<name>, direct
                $t->string('detail', 120)->nullable();
                $t->string('path', 255)->nullable();
                $t->char('visit_id', 16)->nullable();
                $t->index(['occurred_at', 'event']);
            });
        }

        if (Schema::hasTable('ahg_dropdown')) {
            $types = [
                'deployment' => 'Deploying Heratio at my institution',
                'hosting' => 'Hosting by The Archive and Heritage Group',
                'support' => 'Support for an existing installation',
                'migration' => 'Moving from AtoM or another system',
                'demo' => 'A demonstration or training',
                'partnership' => 'Partnership or research',
                'other' => 'Something else',
            ];
            $i = 0;
            foreach ($types as $code => $label) {
                DB::table('ahg_dropdown')->updateOrInsert(
                    ['taxonomy' => 'contact_enquiry_type', 'code' => $code],
                    ['taxonomy_label' => 'Contact enquiry type', 'label' => $label, 'sort_order' => ++$i,
                        'is_default' => $code === 'other' ? 1 : 0, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ahg_acquisition_event');
        if (Schema::hasTable('ahg_dropdown')) {
            DB::table('ahg_dropdown')->where('taxonomy', 'contact_enquiry_type')->delete();
        }
    }
};
