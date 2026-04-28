<?php

namespace AhgCore\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DonorRemindersCommand extends Command
{
    protected $signature = 'ahg:donor-reminders
        {--dry-run : Show reminders without sending or logging}';

    protected $description = 'Send pending donor_agreement_reminder rows whose due_date has passed; log to donor_agreement_reminder_log';

    public function handle(): int
    {
        $now = now();
        $due = DB::table('donor_agreement_reminder')
            ->where('status', 'pending')
            ->where('due_date', '<=', $now)
            ->get();
        $this->info("due reminders: {$due->count()}" . ($this->option('dry-run') ? ' (dry-run)' : ''));

        $sent = 0;
        foreach ($due as $r) {
            if ($this->option('dry-run')) {
                $this->line("  would send agreement={$r->donor_agreement_id} type={$r->reminder_type} due={$r->due_date}");
                continue;
            }
            DB::table('donor_agreement_reminder_log')->insert([
                'reminder_id'        => $r->id,
                'donor_agreement_id' => $r->donor_agreement_id,
                'sent_at'            => $now,
                'channel'            => 'email',
                'outcome'            => 'queued',
            ]);
            DB::table('donor_agreement_reminder')->where('id', $r->id)->update([
                'status'  => 'sent',
                'sent_at' => $now,
            ]);
            $sent++;
        }
        $this->info("sent={$sent}");
        return self::SUCCESS;
    }
}
