<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/dc-manage')->middleware(['web'])->group(function () {
    Route::get('/edit', [\AhgDcManage\Controllers\DcManageController::class, 'edit'])->name('ahgdcmanage.edit');
});
