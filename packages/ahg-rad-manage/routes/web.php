<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/rad-manage')->middleware(['web', 'auth'])->group(function () {
    Route::get('/edit', [\AhgRadManage\Controllers\RadManageController::class, 'edit'])->name('ahgradmanage.edit');
});
