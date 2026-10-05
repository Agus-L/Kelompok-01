<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
// Route::get('/courses/{course}', [CourseController::class, 'show']); Endpoint GET /api/courses/{course} didefinisikan di luar grup middleware auth:sanctum sehingga siapa pun—termasuk pengguna yang belum login sama sekali—dapat mengakses detail mata kuliah tanpa perlu autentikasi, padahal di dalam controller-nya show() sudah memanggil Gate::authorize('view', $course) yang seharusnya hanya boleh diakses oleh pengguna terautentikasi.

Route::middleware('auth:sanctum')->name('api.')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    // Memindahkan rute tersebut ke dalam grup middleware auth:sanctum dan melengkapinya dengan penamaan rute ->name('courses.show').
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
});
