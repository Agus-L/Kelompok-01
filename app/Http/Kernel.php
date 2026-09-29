<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

// class Kernel extends HttpKernel
// {
//     protected $middlewareAliases = [
//         'role' => \App\Http\Middleware\RoleMiddleware::class,
//     ];
// } , Middleware role didaftarkan di Kernel.php yang sudah tidak digunakan di Laravel 12, sehingga middleware tidak terdaftar dan semua route dengan ->middleware('role:...') akan error, seharusnya didaftarkan via $middleware->alias() di bootstrap/app.php.