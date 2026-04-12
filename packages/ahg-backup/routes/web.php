<?php

use AhgBackup\Controllers\BackupController;
use Illuminate\Support\Facades\Route;

Route::middleware('admin')->group(function () {
    Route::get('/admin/backup', [BackupController::class, 'index'])->name('backup.index');
    Route::post('/admin/backup/create', [BackupController::class, 'create'])->name('backup.create');
    Route::get('/admin/backup/settings', [BackupController::class, 'settings'])->name('backup.settings');
    Route::post('/admin/backup/settings', [BackupController::class, 'saveSettings'])->name('backup.saveSettings');
    Route::get('/admin/restore', [BackupController::class, 'restore'])->name('backup.restore');
    Route::post('/admin/restore', [BackupController::class, 'doRestore'])->name('backup.doRestore');
    // Dashboard URL alias under /admin/backup/restore (matches reports dashboard link)
    Route::get('/admin/backup/restore', [BackupController::class, 'restore'])->name('backup.restore.alias');
    Route::post('/admin/backup/restore', [BackupController::class, 'doRestore'])->name('backup.doRestore.alias');
    Route::get('/admin/backup/download/{id}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('/admin/backup/{id}', [BackupController::class, 'destroy'])->name('backup.destroy');
});
