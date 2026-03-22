<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/rad-manage')->middleware(['web'])->group(function () {
    Route::get('/edit', [\AhgModsManage\Controllers\ModsManageController::class, 'edit'])->name('ahgradmanage.edit');
});
