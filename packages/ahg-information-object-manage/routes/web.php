<?php

use AhgInformationObjectManage\Controllers\InformationObjectController;
use Illuminate\Support\Facades\Route;

Route::get('/informationobject/browse', [InformationObjectController::class, 'browse'])->name('informationobject.browse');
Route::get('/{slug}', [InformationObjectController::class, 'show'])->name('informationobject.show')->where('slug', '[a-z0-9-]+');
