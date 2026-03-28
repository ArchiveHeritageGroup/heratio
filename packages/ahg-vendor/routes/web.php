<?php

use Illuminate\Support\Facades\Route;

Route::prefix('admin/vendor')->middleware(['web', 'auth'])->group(function () {

    // Dashboard
    Route::get('/', [\AhgVendor\Controllers\VendorController::class, 'index'])->name('ahgvendor.index');

    // Vendor browse / list
    Route::get('/browse', [\AhgVendor\Controllers\VendorController::class, 'browse'])->name('ahgvendor.browse');
    Route::get('/list', [\AhgVendor\Controllers\VendorController::class, 'list'])->name('ahgvendor.list');

    // Service types management (before {slug} catch-all)
    Route::match(['get', 'post'], '/service-types', [\AhgVendor\Controllers\VendorController::class, 'serviceTypes'])->name('ahgvendor.service-types'); // ACL must be checked in controller (Route::match)

    // Transactions (before {slug} catch-all)
    Route::get('/transactions/browse', [\AhgVendor\Controllers\VendorController::class, 'transactions'])->name('ahgvendor.transactions');
    Route::match(['get', 'post'], '/transactions/add', [\AhgVendor\Controllers\VendorController::class, 'addTransaction'])->name('ahgvendor.add-transaction'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/transactions/{id}/edit', [\AhgVendor\Controllers\VendorController::class, 'editTransaction'])->name('ahgvendor.edit-transaction')->whereNumber('id'); // ACL must be checked in controller (Route::match)
    Route::get('/transactions/{id}', [\AhgVendor\Controllers\VendorController::class, 'viewTransaction'])->name('ahgvendor.view-transaction')->whereNumber('id');
    Route::post('/transactions/{id}/status', [\AhgVendor\Controllers\VendorController::class, 'updateTransactionStatus'])->name('ahgvendor.update-transaction-status')->whereNumber('id')->middleware('acl:update');

    // Transaction items
    Route::post('/transactions/{transactionId}/item/add', [\AhgVendor\Controllers\VendorController::class, 'addTransactionItem'])->name('ahgvendor.add-transaction-item')->whereNumber('transactionId')->middleware('acl:create');
    Route::post('/transactions/{transactionId}/item/{itemId}/update', [\AhgVendor\Controllers\VendorController::class, 'updateTransactionItem'])->name('ahgvendor.update-transaction-item')->whereNumber(['transactionId', 'itemId'])->middleware('acl:update');
    Route::post('/transactions/{transactionId}/item/{itemId}/remove', [\AhgVendor\Controllers\VendorController::class, 'removeTransactionItem'])->name('ahgvendor.remove-transaction-item')->whereNumber(['transactionId', 'itemId'])->middleware('acl:delete');

    // Vendor CRUD (slug-based routes last to avoid catching literal paths)
    Route::match(['get', 'post'], '/add', [\AhgVendor\Controllers\VendorController::class, 'add'])->name('ahgvendor.add'); // ACL must be checked in controller (Route::match)
    Route::match(['get', 'post'], '/{slug}/edit', [\AhgVendor\Controllers\VendorController::class, 'edit'])->name('ahgvendor.edit'); // ACL must be checked in controller (Route::match)
    Route::post('/{slug}/delete', [\AhgVendor\Controllers\VendorController::class, 'delete'])->name('ahgvendor.delete')->middleware('acl:delete');

    // Vendor contacts
    Route::post('/{slug}/contact/add', [\AhgVendor\Controllers\VendorController::class, 'addContact'])->name('ahgvendor.add-contact')->middleware('acl:create');
    Route::post('/{slug}/contact/{contactId}/update', [\AhgVendor\Controllers\VendorController::class, 'updateContact'])->name('ahgvendor.update-contact')->middleware('acl:update');
    Route::post('/{slug}/contact/{contactId}/delete', [\AhgVendor\Controllers\VendorController::class, 'deleteContact'])->name('ahgvendor.delete-contact')->middleware('acl:delete');

    // Vendor view (slug catch-all, must be last)
    Route::get('/{slug}', [\AhgVendor\Controllers\VendorController::class, 'view'])->name('ahgvendor.view');
});
