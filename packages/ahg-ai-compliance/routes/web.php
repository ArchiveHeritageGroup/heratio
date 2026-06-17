<?php
/**
 * Heratio - AI Compliance routes (Article 12 record-keeping).
 *
 * @copyright Copyright (c) 2026, Plain Sailing Information Systems
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @license   AGPL-3.0-or-later
 */

use AhgAiCompliance\Controllers\Annex4Controller;
use AhgAiCompliance\Controllers\ModelRegistryController;
use AhgAiCompliance\Controllers\OversightController;
use AhgAiCompliance\Controllers\PublicKeyController;
use AhgAiCompliance\Controllers\RiskController;
use AhgAiCompliance\Controllers\SystemInventoryController;
use Illuminate\Support\Facades\Route;

// Public verifier endpoint. Stable URL - external auditors / regulators
// fetch this once and pin the key against their copy of the chain.
Route::get('/.well-known/ai-inference-pubkey', [PublicKeyController::class, 'show'])
    ->name('ai-compliance.pubkey');

// --- BEGIN issue #724: Article 9 risk register admin ---
Route::middleware(['web', 'auth'])->prefix('admin/ai-compliance')->name('ai-compliance.')->group(function () {
    Route::get('/risk',                    [RiskController::class, 'index'])->name('risk.index');
    Route::get('/risk/new',                [RiskController::class, 'create'])->name('risk.create');
    Route::post('/risk',                   [RiskController::class, 'store'])->name('risk.store');
    Route::get('/risk/{id}/edit',          [RiskController::class, 'edit'])->where('id', '[0-9]+')->name('risk.edit');
    Route::put('/risk/{id}',               [RiskController::class, 'update'])->where('id', '[0-9]+')->name('risk.update');
    Route::post('/risk/{id}/sign-off',     [RiskController::class, 'signOff'])->where('id', '[0-9]+')->name('risk.sign-off');
    Route::post('/risk/{id}/archive',      [RiskController::class, 'archive'])->where('id', '[0-9]+')->name('risk.archive');
    Route::post('/risk/{id}/incident',     [RiskController::class, 'reportIncident'])->where('id', '[0-9]+')->name('risk.incident');
});
// --- END issue #724 ---

// --- BEGIN #1281: EU AI Act system inventory + risk tiering (Art. 6 / 52) ---
Route::middleware(['web', 'auth'])->prefix('admin/ai-compliance')->name('ai-compliance.')->group(function () {
    Route::get('/systems',            [SystemInventoryController::class, 'index'])->name('systems.index');
    Route::get('/systems/new',        [SystemInventoryController::class, 'create'])->name('systems.create');
    Route::post('/systems',           [SystemInventoryController::class, 'store'])->name('systems.store');
    Route::get('/systems/{id}/edit',  [SystemInventoryController::class, 'edit'])->where('id', '[0-9]+')->name('systems.edit');
    Route::put('/systems/{id}',       [SystemInventoryController::class, 'update'])->where('id', '[0-9]+')->name('systems.update');
    Route::delete('/systems/{id}',    [SystemInventoryController::class, 'destroy'])->where('id', '[0-9]+')->name('systems.destroy');
});
// --- END #1281 ---

// --- BEGIN issue #725: Annex IV ---
// Admin-only CRUD for the AI model registry + Annex IV documentation index.
Route::middleware(['web', 'auth'])->prefix('admin/ai-compliance')->group(function () {
    // ai_model_registry CRUD
    Route::get('/models',           [ModelRegistryController::class, 'index'])->name('ai-compliance.models.index');
    Route::get('/models/create',    [ModelRegistryController::class, 'create'])->name('ai-compliance.models.create');
    Route::post('/models',          [ModelRegistryController::class, 'store'])->name('ai-compliance.models.store');
    Route::get('/models/{id}/edit', [ModelRegistryController::class, 'edit'])->whereNumber('id')->name('ai-compliance.models.edit');
    Route::put('/models/{id}',      [ModelRegistryController::class, 'update'])->whereNumber('id')->name('ai-compliance.models.update');
    Route::delete('/models/{id}',   [ModelRegistryController::class, 'destroy'])->whereNumber('id')->name('ai-compliance.models.destroy');

    // Annex IV technical-documentation generator + viewer
    Route::get('/documentation',           [Annex4Controller::class, 'index'])->name('ai-compliance.documentation.index');
    Route::post('/documentation/generate', [Annex4Controller::class, 'generate'])->name('ai-compliance.documentation.generate');
    Route::get('/documentation/{filename}',[Annex4Controller::class, 'show'])->name('ai-compliance.documentation.show')
        ->where('filename', '[A-Za-z0-9_\-]+\.md');
});
// --- END issue #725 ---

// --- BEGIN issue #726: Article 14 human oversight ---
Route::middleware(['web', 'auth'])->prefix('admin/ai-compliance')->group(function () {
    Route::get('/oversight',                          [OversightController::class, 'index'])->name('ai-compliance.oversight.index');
    Route::put('/oversight/policy/{id}',              [OversightController::class, 'updatePolicy'])->whereNumber('id')->name('ai-compliance.oversight.update');
    Route::post('/oversight/halt/{service}',          [OversightController::class, 'halt'])->where('service', '[a-z0-9_-]+')->name('ai-compliance.oversight.halt');
    Route::post('/oversight/resume/{service}',        [OversightController::class, 'resume'])->where('service', '[a-z0-9_-]+')->name('ai-compliance.oversight.resume');
    Route::post('/oversight/halt-all',                [OversightController::class, 'haltAll'])->name('ai-compliance.oversight.halt-all');
    Route::post('/oversight/attest',                  [OversightController::class, 'attest'])->name('ai-compliance.oversight.attest');
    Route::post('/oversight/countersign/{id}',        [OversightController::class, 'countersign'])->whereNumber('id')->name('ai-compliance.oversight.countersign');
});
// --- END issue #726 ---
