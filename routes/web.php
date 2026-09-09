<?php

use App\Http\Controllers\CourseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// [FIX] Method HTTP yang digunakan untuk menghapus data seharusnya DELETE, bukan GET.
// Jika menggunakan GET, siapapun yang membuka URL tersebut di browser bisa menghapus data tanpa sengaja.
// Baris di bawah adalah versi yang SALAH (dikomentari sebagai bukti):
#Route::get('/courses/{id}/delete', [CourseController::class, 'destroy'])->name('courses.destroy.broken');
// Baris di bawah adalah versi yang BENAR menggunakan Route::delete():
Route::delete('/courses/{id}/delete', [CourseController::class, 'destroy'])->name('courses.destroy.broken');

// [FIX] Urutan route diperbaiki: /courses/create HARUS didefinisikan SEBELUM /courses/{id}.
// Jika /courses/{id} diletakkan lebih dulu, Laravel akan menganggap "create" sebagai nilai {id},
// sehingga halaman tambah mata kuliah tidak akan pernah terbuka.
Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
Route::get('/courses/{id}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
// Baris di bawah adalah versi urutan yang SALAH (dikomentari sebagai bukti):
#Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');, untuk courses/create harusnya di letakkan sebelum courses/{id} karena jika Route /courses/{id} didefinisikan sebelum /courses/create, URL /courses/create dapat ditangkap sebagai parameter {id} dan memanggil CourseController@show dengan id = "create", sehingga method create() tidak dijalankan dan halaman tambah mata kuliah tidak dapat berjalan sebagaimana mestinya.

Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');