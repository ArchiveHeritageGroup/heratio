<?php

/**
 * Overdue notice channels: patron preference and print runs (#1474 item 2).
 * Patron contact data is on these pages, so they match the patron screens.
 */

use AhgLibrary\Controllers\NoticeChannelController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('library-manage/notice-channels')->group(function () {
    Route::get('/', [NoticeChannelController::class, 'index'])->name('library.notice-channel.index');
    Route::post('/print-runs', [NoticeChannelController::class, 'createRun'])->name('library.notice-channel.run-create')->middleware('acl:update');
    Route::get('/print-runs/{id}/pdf', [NoticeChannelController::class, 'downloadRun'])->name('library.notice-channel.run-pdf')->where('id', '[0-9]+')->middleware('acl:update');
    Route::get('/patron/{id}', [NoticeChannelController::class, 'patron'])->name('library.notice-channel.patron')->where('id', '[0-9]+');
    Route::put('/patron/{id}', [NoticeChannelController::class, 'updatePatron'])->name('library.notice-channel.patron-update')->where('id', '[0-9]+')->middleware('acl:update');
});
