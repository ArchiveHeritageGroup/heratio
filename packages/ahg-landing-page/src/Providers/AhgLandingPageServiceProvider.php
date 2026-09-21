<?php

namespace AhgLandingPage\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AhgLandingPageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__.'/../../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ahg-landing-page');

        $this->seedWhatsAppSettings();
    }

    /**
     * WhatsApp chat bubble on the public landing page, managed under
     * Admin > AHG Settings > Features. Off by default: an install shows nothing
     * until an operator enters a number and switches it on. Missing rows are
     * added on boot so existing installs pick the settings up without a
     * migration; an operator's saved values are never overwritten.
     */
    private function seedWhatsAppSettings(): void
    {
        try {
            if (! Schema::hasTable('ahg_settings')) {
                return;
            }

            $defaults = [
                'landing_whatsapp_enabled' => ['0', 'boolean', 'Show a WhatsApp chat bubble on the public landing page.'],
                'landing_whatsapp_number' => ['', 'string', 'Your institution\'s WhatsApp Business number, in international format, e.g. +27 82 123 4567. It is published on the public site, so choose a business number deliberately. The bubble stays hidden until this is set.'],
                'landing_whatsapp_message' => ['', 'string', 'Optional opening text for the visitor\'s chat, e.g. "Hello, I have a question about the collection." Visitors can see and edit it before sending, so put no names, reference numbers or anything sensitive in it.'],
            ];

            $existing = DB::table('ahg_settings')
                ->whereIn('setting_key', array_keys($defaults))
                ->pluck('setting_key')
                ->all();

            $rows = [];
            foreach (array_diff(array_keys($defaults), $existing) as $key) {
                [$value, $type, $description] = $defaults[$key];
                $rows[] = [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'setting_type' => $type,
                    'setting_group' => 'features',
                    'description' => $description,
                ];
            }

            if ($rows) {
                DB::table('ahg_settings')->insertOrIgnore($rows);
            }
        } catch (Throwable) {
            // Never block boot.
        }
    }
}
