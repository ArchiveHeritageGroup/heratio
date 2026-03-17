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

    // AJAX: list remote files
    Route::get('/ftpUpload/listFiles', [FtpUploadController::class, 'listFiles'])->name('ftpUpload.listFiles');

    // AJAX: delete remote file
    Route::post('/ftpUpload/deleteFile', [FtpUploadController::class, 'deleteFile'])->name('ftpUpload.deleteFile');
});
