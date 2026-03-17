<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\OaiPmhController;
use Illuminate\Support\Facades\Route;

// OAI-PMH 2.0 endpoint (public, XML responses)
Route::get('/oai', [OaiPmhController::class, 'handle'])->name('oai');

// Authentication routes
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration
Route::get('/user/register', [LoginController::class, 'showRegister'])->name('register');
Route::post('/user/register', [LoginController::class, 'register']);
Route::get('/research/register', [LoginController::class, 'showResearcherRegister'])->name('researcher.register');
Route::post('/research/register', [LoginController::class, 'researcherRegister'])->name('research.register.store');
Route::get('/research/registration-complete', [LoginController::class, 'registrationComplete'])->name('researcher.register.complete');

// Password reset (public)
Route::get('/user/password-reset', [LoginController::class, 'showPasswordReset'])->name('password.reset');
Route::post('/user/password-reset', [LoginController::class, 'submitPasswordReset']);
Route::get('/user/password-reset/{token}', [LoginController::class, 'showPasswordResetConfirm'])->name('password.reset.confirm');
Route::post('/user/password-reset/{token}', [LoginController::class, 'submitPasswordResetConfirm']);

// User profile and password change (auth required)
Route::middleware('auth.required')->group(function () {
    Route::get('/user/profile', [LoginController::class, 'showProfile'])->name('user.profile');
    Route::get('/user/profile/edit', [LoginController::class, 'showProfileEdit'])->name('user.profile.edit');
    Route::put('/user/profile', [LoginController::class, 'updateProfile'])->name('user.profile.update');
    Route::get('/user/password', [LoginController::class, 'showPasswordEdit'])->name('user.password.edit');
    Route::put('/user/password', [LoginController::class, 'updatePassword'])->name('user.password.update');
});

// Legacy AtoM URL redirects
Route::get('/admin/ahgSettings', fn () => redirect('/admin/settings'));
Route::get('/admin/ahgSetting', fn () => redirect('/admin/settings'));
Route::get('/settings', fn () => redirect('/admin/settings'));
Route::get('/index.php/settings', fn () => redirect('/admin/settings'));
Route::get('/settings/{page}', fn (string $page) => redirect('/admin/settings/' . \Illuminate\Support\Str::kebab($page)));
Route::get('/display/browse', fn (\Illuminate\Http\Request $r) => redirect('/glam/browse?' . $r->getQueryString()));
Route::get('/index.php/settings/{page}', fn (string $page) => redirect('/admin/settings/' . \Illuminate\Support\Str::kebab($page)));
Route::get('/home', fn () => redirect('/'));
Route::get('/contact', [\AhgStaticPage\Controllers\StaticPageController::class, 'show'])->defaults('slug', 'contact')->name('staticpage.contact');
Route::get('/favorites/browse', fn () => redirect('/favorites'));
Route::get('/cart/browse', fn () => redirect('/cart'));
Route::match(['get', 'post'], '/feedback/general', [App\Http\Controllers\FeedbackController::class, 'general'])->name('feedback.general');
Route::get('/feedback/submit/{slug?}', [App\Http\Controllers\FeedbackController::class, 'general'])->name('feedback.submit');

// Homepage
Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
