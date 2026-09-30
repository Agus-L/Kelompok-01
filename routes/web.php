<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Route::get('/lecturer/courses', [CourseController::class, 'index'])->middleware('role:dosen')->name('courses.index');
    // Route::get('/student/courses', [CourseController::class, 'index'])->middleware('role:mahasiswa')->name('courses.index'); 
    // terdapat dua rute terpisah untuk dosen dan mahasiswa yang didaftarkan menggunakan nama yang sama (courses.index). Akibat mekanisme penamaan rute di Laravel, rute mahasiswa menimpa rute dosen sehingga akun Dosen dan Admin mengalami penolakan akses (error 403 Forbidden) saat membuka daftar mata kuliah. Alur yang benar adalah menyatukan kedua rute tersebut ke dalam satu endpoint umum (/courses) bagi seluruh pengguna yang terotentikasi, mengingat diferensiasi hak akses dan data sudah dikelola secara terpusat melalui Policy dan Controller.
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/edit', [CourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

    Route::post('/courses/{course}/materials', [MaterialController::class, 'store'])->name('materials.store');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');
    // Route::get('/materials/{material}/delete', [MaterialController::class, 'destroy'])->name('materials.destroy');
    // Rute destruktif untuk menghapus materi sebelumnya menggunakan method HTTP GET (/materials/{material}/delete). Menggunakan GET untuk aksi destruktif melanggar standar HTTP (GET harus bersifat safe/read-only tanpa efek samping), rentan terhadap serangan CSRF (karena Laravel tidak memvalidasi token CSRF pada request GET), serta berisiko terhapus otomatis oleh web crawler atau fitur prefetch browser. Rute diperbaiki menggunakan method DELETE (/materials/{material}) sesuai standar arsitektur RESTful.
    Route::delete('/materials/{material}', [MaterialController::class, 'destroy'])->name('materials.destroy');

    Route::post('/courses/{course}/assignments', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
    Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy'])->name('assignments.destroy');

    Route::post('/assignments/{assignment}/submissions', [SubmissionController::class, 'store'])->name('submissions.store');
    Route::get('/submissions/{submission}/download', [SubmissionController::class, 'download'])->name('submissions.download');
    Route::post('/submissions/{submission}/grade', [SubmissionController::class, 'grade'])->name('submissions.grade');

    Route::resource('users', UserController::class);

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});
