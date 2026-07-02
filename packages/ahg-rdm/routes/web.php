<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

use AhgRdm\Controllers\DatasetController;
use Illuminate\Support\Facades\Route;

// All paths sit under /research/datasets (the 'research' prefix is excluded
// from the locked /{slug} catch-all, and every route here is >=2 segments).
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/research/datasets', [DatasetController::class, 'index'])->name('rdm.datasets.index');
    Route::get('/research/datasets/dashboard', [DatasetController::class, 'dashboard'])->middleware('admin')->name('rdm.datasets.dashboard'); // #1393 org-wide compliance report = admin only
    Route::get('/research/datasets/compliance', [DatasetController::class, 'compliance'])->middleware('admin')->name('rdm.datasets.compliance'); // #1393
    Route::get('/research/datasets/create', [DatasetController::class, 'create'])->name('rdm.datasets.create');
    Route::post('/research/datasets', [DatasetController::class, 'store'])->name('rdm.datasets.store');
    Route::get('/research/datasets/{id}', [DatasetController::class, 'show'])->name('rdm.datasets.show')->where('id', '[0-9]+');
    Route::post('/research/datasets/{id}/deposit', [DatasetController::class, 'deposit'])->name('rdm.datasets.deposit')->where('id', '[0-9]+');
    Route::post('/research/datasets/{id}/scan', [DatasetController::class, 'scan'])->name('rdm.datasets.scan')->where('id', '[0-9]+');
    Route::post('/research/datasets/{id}/findings/{fid}/resolve', [DatasetController::class, 'resolveFinding'])->name('rdm.datasets.finding.resolve')->where(['id' => '[0-9]+', 'fid' => '[0-9]+']);
    Route::post('/research/datasets/{id}/disposition', [DatasetController::class, 'setDisposition'])->name('rdm.datasets.disposition')->where('id', '[0-9]+');

    // #1337 Feature 1 - link/unlink a Data Management Plan (orchestrates the
    // ahg-research DMP builder; the plan itself is authored in the research portal).
    Route::post('/research/datasets/{id}/dmp', [DatasetController::class, 'linkDmp'])->name('rdm.datasets.dmp.link')->where('id', '[0-9]+');
    Route::delete('/research/datasets/{id}/dmp', [DatasetController::class, 'unlinkDmp'])->name('rdm.datasets.dmp.unlink')->where('id', '[0-9]+');
});

// Public citable landing page (#1341) - no auth: a DOI resolves here. Metadata +
// citation + access-status only; binaries stay gated by the disposition/ODRL.
Route::middleware('web')->group(function () {
    Route::get('/research/datasets/{id}/landing', [DatasetController::class, 'landing'])->name('rdm.datasets.landing')->where('id', '[0-9]+');
});
