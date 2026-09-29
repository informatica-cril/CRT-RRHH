<?php

use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ── Web de control horari (Vue de src/, compilada a public/app amb `npm run build:web` a l'arrel) ──
// Tot en un sol domini: la web a /app, l'API a /api i el panell Inertia a /dashboard.
Route::get('/', function () {
    return redirect('/app/');
});

// Rutes amb # (createWebHashHistory): només cal servir l'index. Sense caché perquè
// cada desplegament es vegi a l'instant (els assets porten hash al nom).
Route::get('app', function () {
    $index = public_path('app/index.html');
    abort_unless(is_file($index), 503, 'La web no està compilada: executa `npm run build:web` a l\'arrel del projecte.');

    return response()->file($index, [
        'Content-Type' => 'text/html; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});

// ── Dashboard (Inertia) ──
Route::get('/dashboard', function () {
    $user = auth()->user();
    if (!$user) {
        return redirect()->route('login');
    }

    // Route by role
    if ($user->role === 'admin' || $user->role === 'coordinator') {
        return Inertia::render('Administracion/Dashboard');
    }
    return Inertia::render('Fisios/Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// ── Login (Inertia) ──
Route::get('/login', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return Inertia::render('Auth/Login');
})->middleware('guest')->name('login');

Route::post('/login', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (auth()->attempt($credentials)) {
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    return back()->withErrors([
        'email' => 'Les credencials proporcionades no són correctes.',
    ])->onlyInput('email');
})->middleware('guest')->name('login.post');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    auth()->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('login');
})->name('logout');

// ── Rutas Inertia para fichaje segmentado y coordinación ──
Route::middleware(['auth'])->group(function () {
    // Vista detallada de un fichaje con tramos segmentados
    Route::get('work-logs/{workLogId}/detail', [WebController::class, 'workLogDetail'])
        ->name('administracion.workLogDetail');

    // Configuración de pausa obligatoria (admin + coordinator)
    Route::get('break-settings', [WebController::class, 'breakSettings'])
        ->name('administracion.breakSettings');

    // Panel de coordinación (admin + coordinator)
    Route::get('coordinator-panel', [WebController::class, 'coordinatorPanel'])
        ->name('administracion.coordinatorPanel');

    // Acreditacions de la formació obligatòria (admin + RRHH)
    Route::get('formacio-acreditacions', [WebController::class, 'formacioAcreditacions'])
        ->name('administracion.formacioAcreditacions');


    // Profile
    Route::get('profile/edit', function () {
        return Inertia::render('Profile/Edit');
    })->name('profile.edit');

    Route::get('administracion/profile/edit', function () {
        return Inertia::render('Administracion/Profile/Edit');
    })->name('profile.edit.administracion');

    Route::get('fisios/complementaries', function () {
        return Inertia::render('Fisios/Complementaries');
    })->name('fisios.complementaries');

    Route::get('fisios/profile/edit', function () {
        return Inertia::render('Fisios/Profile/Edit');
    })->name('profile.edit.fisios');
});
