<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/dacs-manage')->middleware(['web'])->group(function () {
    Route::get('/edit', [\AhgDacsManage\Controllers\DacsManageController::class, 'edit'])->name('ahgdacsmanage.edit');
});
