<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/mods-manage')->middleware(['web'])->group(function () {
    Route::get('/edit', [\AhgModsManage\Controllers\ModsManageController::class, 'edit'])->name('ahgmodsmanage.edit');
});
