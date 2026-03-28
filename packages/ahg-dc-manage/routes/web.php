<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/dc-manage')->middleware(['web', 'auth'])->group(function () {
    Route::match(['get', 'post'], '/edit/{slug}', [\AhgDcManage\Controllers\DcManageController::class, 'edit'])->name('ahgdcmanage.edit');
});
