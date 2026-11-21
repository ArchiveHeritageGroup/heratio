<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * heratio#1767 - server speech-to-text fallback for voice search, under
 * Settings > Voice & AI. The fallback stays off until an administrator sets
 * the model (voice_stt_model is seeded empty). Existing rows are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ahg_settings')) {
            return;
        }
        foreach ([
            'voice_commands_enabled' => ['true', 'Show the microphone button and voice commands ("search for ...") on every page. Off removes the voice scripts from the site.'],
            'voice_stt_enabled' => ['true', 'Server speech-to-text for voice search in browsers without built-in speech recognition. Works only once a model is set. Audio is not stored: it is sent to the AI gateway and deleted when the request ends.'],
            'voice_stt_model' => ['', 'Transcription model the AI gateway serves (for example whisper-large-v3). Empty keeps the server fallback off.'],
            'voice_stt_path' => ['/ai/v1/audio/transcriptions', 'Transcription route on the AI gateway (a path, joined to the gateway address).'],
            'voice_stt_require_auth' => ['false', 'Only signed-in users may use server speech-to-text.'],
            'voice_stt_max_seconds' => ['15', 'Longest recording in seconds (3 to 60).'],
            'voice_stt_max_kb' => ['1024', 'Largest recording accepted, in KB (64 to 10240).'],
            'voice_stt_rate_limit' => ['10', 'Recordings per minute per user, or per IP address for visitors (1 to 600).'],
            'voice_stt_timeout' => ['20', 'Seconds to wait for the gateway before telling the user to type the search (3 to 120).'],
        ] as $key => [$value, $desc]) {
            if (! DB::table('ahg_settings')->where('setting_key', $key)->exists()) {
                $row = ['setting_key' => $key, 'setting_value' => $value, 'setting_group' => 'voice_ai'];
                if (Schema::hasColumn('ahg_settings', 'description')) {
                    $row['description'] = $desc;
                }
                DB::table('ahg_settings')->insert($row);
            }
        }
    }

    public function down(): void
    {
        // Additive only.
    }
};
