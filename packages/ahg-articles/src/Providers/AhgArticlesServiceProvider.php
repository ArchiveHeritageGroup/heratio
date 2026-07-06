<?php

/**
 * AhgArticlesServiceProvider - registers the Articles plugin: public article
 * pages + comments, the admin authoring UI (dual Markdown/WYSIWYG editor), and
 * downloadable guides & templates. Self-installing so any institution can run
 * its own articles section.
 *
 * Routes are registered via callAfterResolving('router') because `/articles` is a
 * single top-level segment and must beat the locked `/{slug}` catch-all (see
 * memory/reference_slug_catchall_route_precedence).
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
 *
 * @copyright Plain Sailing Information Systems
 *
 * @license AGPL-3.0-or-later
 */

namespace AhgArticles\Providers;

use AhgArticles\Console\PersistArticlesCommand;
use AhgArticles\Controllers\Admin\BlogAdminController;
use AhgArticles\Controllers\BlogController;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AhgArticlesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->callAfterResolving('router', function ($router) {
            // Public: `/articles` (single segment) must be pre-registered to beat
            // the `/{slug}` catch-all. Comments are anonymous + throttled.
            $router->middleware(['web'])->group(function () use ($router) {
                $router->get('/articles', [BlogController::class, 'index'])->name('articles.index');
                $router->get('/articles/{slug}', [BlogController::class, 'show'])
                    ->where('slug', '[a-z0-9][a-z0-9-]*')->name('articles.show');
                $router->post('/articles/{slug}/comments', [BlogController::class, 'comment'])
                    ->where('slug', '[a-z0-9][a-z0-9-]*')->middleware('throttle:6,1')->name('articles.comment');
            });

            // Admin authoring + moderation (prefix already outside the catch-all).
            $router->middleware(['web', 'auth'])->prefix('admin/articles')->name('admin.articles.')->group(function () use ($router) {
                $router->get('/', [BlogAdminController::class, 'index'])->name('index');
                $router->get('/create', [BlogAdminController::class, 'create'])->name('create');
                $router->post('/', [BlogAdminController::class, 'store'])->name('store');
                $router->post('/upload-image', [BlogAdminController::class, 'uploadImage'])->name('upload-image');
                $router->get('/comments', [BlogAdminController::class, 'comments'])->name('comments');
                $router->put('/comments/{id}/status', [BlogAdminController::class, 'commentStatus'])->where('id', '[0-9]+')->name('comments.status');
                $router->delete('/comments/{id}', [BlogAdminController::class, 'commentDestroy'])->where('id', '[0-9]+')->name('comments.destroy');
                $router->post('/{id}/attachments', [BlogAdminController::class, 'storeAttachment'])->where('id', '[0-9]+')->name('attachments.store');
                $router->put('/{id}/attachments/{attachmentId}', [BlogAdminController::class, 'updateAttachment'])->where('id', '[0-9]+')->where('attachmentId', '[0-9]+')->name('attachments.update');
                $router->delete('/{id}/attachments/{attachmentId}', [BlogAdminController::class, 'destroyAttachment'])->where('id', '[0-9]+')->where('attachmentId', '[0-9]+')->name('attachments.destroy');
                $router->get('/{id}/edit', [BlogAdminController::class, 'edit'])->where('id', '[0-9]+')->name('edit');
                $router->post('/{id}/links', [BlogAdminController::class, 'linksAdd'])->where('id', '[0-9]+')->name('links.add');
                $router->delete('/{id}/links/{targetId}', [BlogAdminController::class, 'linksRemove'])->where('id', '[0-9]+')->where('targetId', '[0-9]+')->name('links.remove');
                $router->put('/{id}', [BlogAdminController::class, 'update'])->where('id', '[0-9]+')->name('update');
                $router->put('/{id}/protect', [BlogAdminController::class, 'toggleProtect'])->where('id', '[0-9]+')->name('protect');
                $router->delete('/{id}', [BlogAdminController::class, 'destroy'])->where('id', '[0-9]+')->name('destroy');
            });
        });
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'articles');
        Paginator::useBootstrapFive();   // the articles index paginator uses BS5 markup
        $this->install();

        if ($this->app->runningInConsole()) {
            $this->commands([PersistArticlesCommand::class]);
        }
    }

    /** First-boot self-install of tables + the attachment-type dropdown taxonomy. */
    private function install(): void
    {
        try {
            if (! Schema::hasTable('blog_post')) {
                DB::unprepared((string) file_get_contents(__DIR__.'/../../database/install.sql'));
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-articles: table install skipped: '.$e->getMessage());
        }
        try {
            if (Schema::hasTable('ahg_dropdown')
                && DB::table('ahg_dropdown')->where('taxonomy', 'blog_attachment_kind')->doesntExist()) {
                DB::unprepared((string) file_get_contents(__DIR__.'/../../database/seed_dropdowns.sql'));
            }
        } catch (\Throwable $e) {
            Log::warning('ahg-articles: dropdown seed skipped: '.$e->getMessage());
        }
    }
}
