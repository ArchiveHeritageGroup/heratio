#!/usr/bin/env python3
with open('packages/ahg-library/routes/web.php', 'r') as f:
    c = f.read()

old = ("    Route::get('/library-manage/serial/{id}', [LibraryController::class, 'serialView'])->name('library.serial-view')->where('id', '[0-9]+');\n\n    // Reports")

new = """    Route::get('/library-manage/serial/{id}', [LibraryController::class, 'serialView'])->name('library.serial-view')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/add',  [LibraryController::class,'serialCreate'])->name('library.serial-create');
    Route::post('/library-manage/serial/add', [LibraryController::class,'serialStore'])->name('library.serial-store');
    Route::get('/library-manage/serial/{id}/edit',  [LibraryController::class,'serialEdit'])->name('library.serial-edit')->where('id', '[0-9]+');
    Route::put('/library-manage/serial/{id}',  [LibraryController::class,'serialUpdate'])->name('library.serial-update')->where('id', '[0-9]+');
    Route::delete('/library-manage/serial/{id}', [LibraryController::class,'serialDelete'])->name('library.serial-delete')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/issue', [LibraryController::class,'serialAddIssue'])->name('library.serial-add-issue')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/subscription', [LibraryController::class,'serialSubscription'])->name('library.serial-subscription')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/subscription', [LibraryController::class,'serialSubscriptionStore'])->name('library.serial-subscription-store')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/predict', [LibraryController::class,'serialPredict'])->name('library.serial-predict')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/{id}/coverage', [LibraryController::class,'serialCoverage'])->name('library.serial-coverage')->where('id', '[0-9]+');
    Route::post('/library-manage/serial/{id}/clone', [LibraryController::class,'serialClone'])->name('library.serial-clone')->where('id', '[0-9]+');
    Route::get('/library-manage/serial/overdue-claims', [LibraryController::class,'serialOverdueClaims'])->name('library.serial-overdue-claims');
    Route::post('/library-manage/serial/{serialId}/claim/{issueId}',[LibraryController::class,'serialClaimIssue'])->name('library.serial-claim-issue')->where(['serialId'=>'[0-9]+','issueId'=>'[0-9]+']);

    // Reports"""

nc = c.replace(old, new)
if nc == c:
    print('NOT FOUND')
else:
    with open('packages/ahg-library/routes/web.php', 'w') as f:
        f.write(nc)
    print('OK', nc.count('\n'), 'lines')