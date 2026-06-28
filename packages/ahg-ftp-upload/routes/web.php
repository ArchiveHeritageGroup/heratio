<?php

use AhgFtpUpload\Controllers\FtpUploadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    // Main page (menu path: ftpUpload/index)
    Route::get('/ftpUpload/index', [FtpUploadController::class, 'index'])->name('ftpUpload.index');

    // Legacy single-file upload
    Route::post('/ftpUpload/upload', [FtpUploadController::class, 'upload'])->name('ftpUpload.upload')->middleware('acl:create'); // #1354

    // Chunked upload (AJAX)
    Route::post('/ftpUpload/uploadChunk', [FtpUploadController::class, 'uploadChunk'])->name('ftpUpload.uploadChunk')->middleware('acl:create'); // #1354

    // Combine an uploaded folder into a PDF/A (background)
    Route::post('/ftpUpload/combineFolder', [FtpUploadController::class, 'combineFolder'])->name('ftpUpload.combineFolder')->middleware('acl:create'); // #1354

    // AJAX: list remote files
    Route::get('/ftpUpload/listFiles', [FtpUploadController::class, 'listFiles'])->name('ftpUpload.listFiles');

    // AJAX: delete remote file
    Route::post('/ftpUpload/deleteFile', [FtpUploadController::class, 'deleteFile'])->name('ftpUpload.deleteFile')->middleware('acl:delete'); // #1354

    // AJAX: delete all files (clear all)
    Route::post('/ftpUpload/clearAll', [FtpUploadController::class, 'clearAll'])->name('ftpUpload.clearAll')->middleware('acl:delete'); // #1354

    // AJAX: list combined PDFs ready to link (no-slug combines)
    Route::get('/ftpUpload/readyToLink', [FtpUploadController::class, 'readyToLink'])->name('ftpUpload.readyToLink');

    // AJAX: attach a combined PDF to a record by slug
    Route::post('/ftpUpload/attachExisting', [FtpUploadController::class, 'attachExisting'])->name('ftpUpload.attachExisting')->middleware('acl:create'); // #1354
});
