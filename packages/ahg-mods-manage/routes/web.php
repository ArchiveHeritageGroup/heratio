<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/mods-manage')->middleware(['web', 'auth'])->group(function () {
    Route::match(['get', 'post'], '/edit/{slug}', [\AhgModsManage\Controllers\ModsManageController::class, 'edit'])->name('ahgmodsmanage.edit')->middleware('acl:update'); // whole match route gated (GET form + POST mutation/publish share the route)
});
