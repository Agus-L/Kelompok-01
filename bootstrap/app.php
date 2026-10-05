<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException $e, $request) {
            if ($request->is('api/*')) {
                // return response()->json(['message' => 'This action is unauthorized.'], 401); Ketika pengguna yang sudah login mencoba mengakses resource yang bukan haknya (misalnya mahasiswa mencoba menghapus mata kuliah), handler exception di bootstrap/app.php justru mengembalikan kode HTTP 401 Unauthorized padahal seharusnya 403 Forbidden, karena 401 berarti "kamu belum login" sedangkan 403 berarti "kamu sudah login, tapi tidak punya izin"—keduanya memiliki makna yang sangat berbeda dalam standar
                return response()->json(['message' => 'This action is unauthorized.'], 403);
            }
        });
    })->create();
