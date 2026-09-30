# FIX #3 — Nested Route Tanpa scopeBindings

**Identitas**
- Nama: Anastasya Salsabila Khoirunnisa
- NIM: 10241009

## Masalah
Pada aplikasi `LMS-Broken`, terdapat nested route yang menghubungkan model parent `Course` dengan model child `Assignment` melalui URL `/courses/{course}/assignments/{assignment}`. Secara default di Laravel, jika nested route tidak dilengkapi dengan metode `scopeBindings()`, Route Model Binding akan mencari model `{course}` dan model `{assignment}` secara terpisah/independen tanpa memvalidasi apakah model `{assignment}` tersebut benar-benar dimiliki oleh model `{course}` yang ditentukan pada URL. Hal ini berpotensi menimbulkan celah keamanan di mana pengguna dapat mengakses data tugas (`Assignment`) milik mata kuliah (`Course`) lain hanya dengan mengubah ID course pada parameter URL.

## Kondisi Sebelum Perbaikan
1. **Route yang ditemukan:**
   Pada file `routes/web.php`:
   ```php
   Route::get('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'show'])->name('assignments.show');
   ```
   Route tersebut bersifat nested karena menerima dua model parameter (`{course}` dan `{assignment}`), namun belum menambahkan deklarasi `scopeBindings()`.

2. **Hasil Pengujian Sebelum Perbaikan:**
   - **URL Pengujian:** `/courses/1/assignments/2`
   - **Keterangan Data:** `Course ID 1` adalah *Pemrograman Web*, sedangkan `Assignment ID 2` (*Tugas 1 Basis Data Lanjut*) sebenarnya milik `Course ID 2`.
   - **Hasil Aktual:** HTTP Status **`200 OK`**.
   - **Analisis:** Aplikasi mengizinkan `Assignment ID 2` diakses melalui URL `/courses/1/assignments/2` meskipun `Assignment ID 2` bukan milik `Course ID 1`. Child model tidak terbatasi (*unscoped*) oleh parent model-nya.

## Perbaikan
Perbaikan dilakukan pada rute nested `assignments.show` di file `routes/web.php` dengan menambahkan metode `->scopeBindings()`:

```php
Route::get('/courses/{course}/assignments/{assignment}', [AssignmentController::class, 'show'])
    ->name('assignments.show')
    ->scopeBindings();
```

Selain itu, pada `app/Http/Controllers/AssignmentController.php`, signature method `show()` disesuaikan agar menerima tipe data model parent dan child:
```php
public function show(Course $course, Assignment $assignment)
```
Hal ini memastikan Laravel dapat melakukan pencarian *implicit scoped binding* berdasarkan relasi `course()` pada model `Assignment`.

## Pengujian Setelah Perbaikan
Pengujian dilakukan terhadap dua skenario menggunakan HTTP request handling pada aplikasi Laravel:

1. **Skenario 1 — Parent dan Child Tidak Cocok (Mismatch):**
   - **URL:** `/courses/1/assignments/2` (Assignment 2 milik Course 2)
   - **Hasil Aktual:** HTTP Status **`404 Not Found`** (`Illuminate\Database\Eloquent\ModelNotFoundException`).
   - **Keterangan:** Laravel gagal menemukan `Assignment ID 2` yang berada di bawah `Course ID 1`.

2. **Skenario 2 — Parent dan Child Cocok (Valid):**
   - **URL:** `/courses/1/assignments/1` (Assignment 1 milik Course 1)
   - **Hasil Aktual:** HTTP Status **`200 OK`**.
   - **Keterangan:** Tugas berhasil diakses secara normal karena `Assignment ID 1` benar-benar terdaftar di bawah `Course ID 1`.

## Hasil
| Skenario Pengujian | Sebelum Perbaikan | Sesudah Perbaikan |
| :--- | :--- | :--- |
| **URL Mismatch** (`/courses/1/assignments/2`) | **200 OK** (Dapat diakses walau beda Course) | **404 Not Found** (Akses ditolak & divalidasi) |
| **URL Valid** (`/courses/1/assignments/1`) | **200 OK** (Dapat diakses) | **200 OK** (Tetap dapat diakses normal) |

## Kesimpulan
Penerapan `scopeBindings()` pada rute nested `/courses/{course}/assignments/{assignment}` berhasil membatasi (*scoping*) pencarian model child (`Assignment`) berdasarkan parent model (`Course`) yang sesuai pada URL. Dengan demikian, jika ID child yang diminta tidak memiliki hubungan kepemilikan (*relasi*) dengan ID parent pada URL, Laravel akan secara otomatis mengembalikan respons **404 Not Found**, sehingga mencegah akses data tak sah antar-parent.
