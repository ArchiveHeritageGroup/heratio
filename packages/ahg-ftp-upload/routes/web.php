<?php

use AhgFtpUpload\Controllers\FtpUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Main page (menu path: ftpUpload/index)
    Route::get('/ftpUpload/index', [FtpUploadController::class, 'index'])->name('ftpUpload.index');

    // Legacy single-file upload
    Route::post('/ftpUpload/upload', [FtpUploadController::class, 'upload'])->name('ftpUpload.upload');

    // Chunked upload (AJAX)
    Route::post('/ftpUpload/uploadChunk', [FtpUploadController::class, 'uploadChunk'])->name('ftpUpload.uploadChunk');

    // Combine an uploaded folder into a PDF/A (background)
    Route::post('/ftpUpload/combineFolder', [FtpUploadController::class, 'combineFolder'])->name('ftpUpload.combineFolder');

    // AJAX: list remote files
    Route::get('/ftpUpload/listFiles', [FtpUploadController::class, 'listFiles'])->name('ftpUpload.listFiles');

    // AJAX: delete remote file
    Route::post('/ftpUpload/deleteFile', [FtpUploadController::class, 'deleteFile'])->name('ftpUpload.deleteFile');

    // AJAX: delete all files (clear all)
    Route::post('/ftpUpload/clearAll', [FtpUploadController::class, 'clearAll'])->name('ftpUpload.clearAll');
});
