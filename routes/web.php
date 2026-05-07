<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// OAI-PMH route is in the ahg-oai package

// Locale switcher — accepts a `culture` field via POST and persists it to
// session('locale'). The SetLocale middleware applies it on the next request.
// Validates against ahg_settings.i18n_languages, falling back to filenames in
// /lang/*.json if the setting table is empty.
Route::post('/set-locale', function (\Illuminate\Http\Request $request) {
    $culture = (string) $request->input('culture', '');
    if (! preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/', $culture)) {
        return back();
    }
    $enabled = [];
    if (\Illuminate\Support\Facades\Schema::hasTable('setting')) {
        $enabled = \Illuminate\Support\Facades\DB::table('setting')
            ->where('scope', 'i18n_languages')
            ->where('editable', 1)
            ->pluck('name')
            ->toArray();
    }
    if (empty($enabled)) {
        $files = glob(base_path('lang/*.json')) ?: [];
        $enabled = array_map(fn ($f) => pathinfo($f, PATHINFO_FILENAME), $files);
    }
    if (empty($enabled) || in_array($culture, $enabled, true)) {
        session(['locale' => $culture]);
        \Illuminate\Support\Facades\App::setLocale($culture);
    }
    $redirectTo = (string) $request->input('redirect_to', '/');
    $response = (! preg_match('#^https?://#i', $redirectTo) || str_starts_with($redirectTo, $request->getSchemeAndHttpHost()))
        ? redirect($redirectTo)
        : redirect('/');
    // Year-long cookie so the locale survives logout / new sessions
    // (mirrors AtoM's atom_culture cookie). Cleared by sending culture=en.
    return $response->cookie('locale', $culture, 60 * 24 * 365, '/', null, true, false, false, 'lax');
})->name('set-locale');

// Authentication routes
// #47: POST /login is throttled via the 'login' RateLimiter::for closure
// in AppServiceProvider (5/min per IP + 5/min per username). GET stays
// unthrottled so a victim of the throttle can still see the form and
// understand what's happening.
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// Registration
// #47: POST /user/register also throttled via the same 'login' bucket
// (registration is the second-most-common credential-bruteforce target -
// account-creation flooding to fill the user table).
Route::get('/user/register', [LoginController::class, 'showRegister'])->name('register');
Route::post('/user/register', [LoginController::class, 'register'])->middleware('throttle:login');
Route::get('/research/register', [LoginController::class, 'showResearcherRegister'])->name('researcher.register');
Route::post('/research/register', [LoginController::class, 'researcherRegister'])->name('research.register.store')->middleware('throttle:login');
Route::get('/research/registration-complete', [LoginController::class, 'registrationComplete'])->name('researcher.register.complete');

// Password reset (public)
// #47: throttle the two POST surfaces via the 'passwordReset' bucket
// (3/min per IP - tighter than login because each request triggers a
// transactional email that costs real money on providers like AWS SES).
Route::get('/user/password-reset', [LoginController::class, 'showPasswordReset'])->name('password.reset');
Route::post('/user/password-reset', [LoginController::class, 'submitPasswordReset'])->middleware('throttle:passwordReset');
Route::get('/user/password-reset/{token}', [LoginController::class, 'showPasswordResetConfirm'])->name('password.reset.confirm');
Route::post('/user/password-reset/{token}', [LoginController::class, 'submitPasswordResetConfirm'])->middleware('throttle:passwordReset');

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
Route::get('/admin/description-updates', fn () => redirect('/search/descriptionUpdates'));
Route::get('/admin/global-replace', fn () => redirect('/search/globalReplace'));
Route::get('/home', fn () => redirect('/'));
Route::get('/contact', [\AhgStaticPage\Controllers\StaticPageController::class, 'show'])->defaults('slug', 'contact')->name('staticpage.contact');
Route::get('/favorites/browse', fn () => redirect('/favorites'));
Route::get('/cart/browse', fn () => redirect('/cart'));
Route::get('/menu/list', fn () => redirect('/admin/menu/browse', 301));

// Records (information object) aliases
Route::get('/records/browse', fn () => redirect('/informationobject/browse'));
Route::get('/records/add', fn () => redirect('/informationobject/add'));
Route::get('/records/autocomplete', fn () => redirect('/informationobject/autocomplete'));
// Feedback routes are in ahg-feedback package

// Public version endpoint — single source of truth for "what is deployed?"
// Reads version.json (the same file the footer + ./bin/release write).
// Returns JSON: { name, version, release_date, description, git_commit }.
// Cache headers prevent CDN caching so a deploy is reflected immediately.
Route::get('/version', function () {
    $data = ['name' => 'Heratio', 'version' => null, 'release_date' => null, 'description' => null, 'git_commit' => null];
    $file = base_path('version.json');
    if (is_file($file)) {
        $data = array_merge($data, json_decode((string) file_get_contents($file), true) ?: []);
    }
    $head = base_path('.git/HEAD');
    if (is_file($head)) {
        $ref = trim((string) file_get_contents($head));
        if (str_starts_with($ref, 'ref: ')) {
            $refFile = base_path('.git/' . substr($ref, 5));
            if (is_file($refFile)) {
                $data['git_commit'] = substr(trim((string) file_get_contents($refFile)), 0, 12);
            }
        } elseif (preg_match('/^[a-f0-9]{40}$/', $ref)) {
            $data['git_commit'] = substr($ref, 0, 12);
        }
    }
    return response()->json($data)->header('Cache-Control', 'no-store, max-age=0');
})->name('version');

// Homepage
Route::get('/', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
Route::get('/homepage', [App\Http\Controllers\HomeController::class, 'index'])->name('homepage'); // AtoM alias
