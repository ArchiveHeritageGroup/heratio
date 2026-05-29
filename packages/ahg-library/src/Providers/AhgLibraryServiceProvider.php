<?php

namespace AhgLibrary\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AhgLibraryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Seed library-acquisition dropdown taxonomies.
        // Schema: ahg_dropdown { taxonomy, taxonomy_label, taxonomy_section,
        // code, label, sort_order, is_default, is_active, created_at, updated_at }
        // taxonomy = group key; code = option value; label = display string.
        // Guarded: a fresh install / test DB may not have ahg_dropdown yet, and
        // an unguarded query here crashes every request + test boot.
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ahg_dropdown')) {
                $this->seedAcquisitionsDropdowns();
            }
        } catch (\Throwable) {
            // tables not migrated yet - skip seeding, boot continues
        }

        \Illuminate\Support\Facades\Route::middleware('web')
            ->group(__DIR__ . '/../../routes/web.php');

        // #1100 library acquisitions JSON:API. Route file applies its own
        // api.auth scope + prefix groups, so it is loaded without an extra
        // middleware wrap.
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'ahg-library');

        // Register the package's anonymous-component directory with no prefix so
        // circulation / ILL / trading-partner views can use <x-library-layout>.
        // Without this the unprefixed tag never resolves and view:cache aborts
        // with "Unable to locate a class or view for component [library-layout]".
        \Illuminate\Support\Facades\Blade::anonymousComponentPath(
            __DIR__ . '/../../resources/views/components'
        );

        // #1100 role-based policies for the acquisitions resources. They share
        // the AclService action gate used by the web ACL + the JSON:API
        // controllers, so Gate/@can stays consistent across surfaces.
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibraryOrder::class, \AhgLibrary\Policies\LibraryOrderPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibraryBudget::class, \AhgLibrary\Policies\LibraryBudgetPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibraryVendor::class, \AhgLibrary\Policies\LibraryVendorPolicy::class);
        // #1092 serials JSON:API policies
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibrarySerial::class, \AhgLibrary\Policies\LibrarySerialPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibrarySerialIssue::class, \AhgLibrary\Policies\LibrarySerialIssuePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\AhgLibrary\Models\LibrarySerialSubscription::class, \AhgLibrary\Policies\LibrarySerialSubscriptionPolicy::class);

        // Alias for the OPAC gate so route files can use ['opac.enabled']
        $this->app['router']->aliasMiddleware('opac.enabled', \AhgLibrary\Middleware\EnsureOpacEnabled::class);
        $this->app['router']->aliasMiddleware('library.patron.auth', \AhgLibrary\Middleware\EnsurePatronAuthenticated::class);

        // #766 per-event COUNTER instrumentation: inject usage-tracker.js into
        // library-item show pages via a global response middleware. Same
        // technique as the chatbot widget injector - keeps the locked layout
        // templates untouched.
        try {
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            if (method_exists($kernel, 'appendMiddlewareToGroup')) {
                $kernel->appendMiddlewareToGroup('web', \AhgLibrary\Middleware\InjectUsageTracker::class);
            }
        } catch (\Throwable) {
            // best-effort; the route handler still records direct beacon hits
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \AhgLibrary\Console\Commands\ImportLibraryCsvCommand::class,
                \AhgLibrary\Console\Commands\AutoExpireHoldsCommand::class,
                \AhgLibrary\Console\Commands\AutoExpirePatronsCommand::class,
                \AhgLibrary\Console\Commands\CalculateFinesCommand::class,
                \AhgLibrary\Console\Commands\BackfillLibraryAuthorsCommand::class,
                \AhgLibrary\Console\Commands\KbartRefreshFeedsCommand::class,
                \AhgLibrary\Console\Commands\EmailUsageReportsCommand::class,
                \AhgLibrary\Console\Commands\OdiRefreshScorecardCommand::class,   // #1097
                \AhgLibrary\Console\Commands\SerialClaimAlertsCommand::class,     // #1092
                \AhgLibrary\Console\Commands\SerialExpiryAlertsCommand::class,    // #1092
                \AhgLibrary\Console\Commands\SendOverdueNoticesCommand::class,    // #1093
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                // Daily housekeeping for the circulation surface. Each command
                // guards itself on its own setting flag, so flipping a toggle in
                // /admin/ahgSettings/library is enough to silence the schedule.
                $schedule->command('ahg:library-auto-expire-holds')->dailyAt('02:30')->withoutOverlapping(60);
                $schedule->command('ahg:library-auto-expire-patrons')->dailyAt('02:45')->withoutOverlapping(60);
                $schedule->command('ahg:library-calculate-fines')->dailyAt('03:15')->withoutOverlapping(60);
                // Issue #768 - KBART remote feed refresh: daily at 01:00, before
                // the circulation batch so feed metadata is updated before staff arrive.
                $schedule->command('ahg:library-kbart-refresh')->dailyAt('01:00')->withoutOverlapping(60);
                // Issue #766 - COUNTER R5 report email delivery: 1st of every
                // month at 04:00 SAST. The command itself resolves the prior
                // period and short-circuits when no recipients are configured.
                $schedule->command('ahg:library-email-usage-reports')
                    ->monthlyOn(1, '04:00')
                    ->withoutOverlapping(120);
                // #1092 serials: claim overdue issues + warn before subscription expiry.
                $schedule->command('ahg:library-serial-claim-alerts')->dailyAt('03:30')->withoutOverlapping(60);
                $schedule->command('ahg:library-serial-expiry-alerts')->dailyAt('03:45')->withoutOverlapping(60);
                // #1093 circulation: tiered overdue notices.
                $schedule->command('ahg:library-overdue-notices')->dailyAt('06:00')->withoutOverlapping(120);
            });
        }
    }

    protected function seedAcquisitionsDropdowns(): void
    {
        // Codes aligned with PSISA migration_full_library.sql.
        $taxonomies = [
            'library_order_status' => [
                'label'   => 'Acquisition Order Status',
                'section' => 'library',
                'options' => [
                    ['code' => 'draft',       'label' => 'Draft',                    'default' => true],
                    ['code' => 'submitted',  'label' => 'Submitted',                  'default' => false],
                    ['code' => 'approved',   'label' => 'Approved',                  'default' => false],
                    ['code' => 'ordered',    'label' => 'Ordered',                   'default' => false],
                    ['code' => 'partial',    'label' => 'Partially Received',         'default' => false],
                    ['code' => 'received',   'label' => 'Received',                   'default' => false],
                    ['code' => 'cancelled', 'label' => 'Cancelled',                 'default' => false],
                ],
            ],
            'library_order_type' => [
                'label'   => 'Acquisition Order Type',
                'section' => 'library',
                'options' => [
                    ['code' => 'purchase',       'label' => 'Purchase Order',      'default' => true],
                    ['code' => 'standing_order', 'label' => 'Standing Order',      'default' => false],
                    ['code' => 'gift',           'label' => 'Gift / Donation',     'default' => false],
                    ['code' => 'exchange',        'label' => 'Exchange',            'default' => false],
                    ['code' => 'deposit',         'label' => 'Deposit Agreement',   'default' => false],
                    ['code' => 'approval',        'label' => 'Approval Plan',       'default' => false],
                ],
            ],
            'acq_payment_status' => [
                'label'   => 'Acquisition Payment Status',
                'section' => 'library',
                'options' => [
                    ['code' => 'unpaid',    'label' => 'Unpaid',                 'default' => true],
                    ['code' => 'invoiced',  'label' => 'Invoiced',               'default' => false],
                    ['code' => 'paid',      'label' => 'Paid',                   'default' => false],
                    ['code' => 'overdue',   'label' => 'Overdue',                'default' => false],
                    ['code' => 'refunded',  'label' => 'Refunded / Credited',    'default' => false],
                ],
            ],
        ];

        foreach ($taxonomies as $taxonomy => $def) {
            foreach ($def['options'] as $idx => $opt) {
                $isDefault = $opt['default'] ? 1 : 0;
                DB::table('ahg_dropdown')->updateOrInsert(
                    ['taxonomy' => $taxonomy, 'code' => $opt['code']],
                    [
                        'taxonomy_label'   => $def['label'],
                        'taxonomy_section' => $def['section'],
                        'label'            => $opt['label'],
                        'sort_order'       => ($idx + 1) * 10,
                        'is_default'       => $isDefault,
                        'is_active'        => 1,
                        'updated_at'       => now(),
                    ]
                );
            }
        }
    }
}
