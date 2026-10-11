# Identitas Mahasiswa

- Nama: Anastasya Salsabila Khoirunnisa
- NIM: 10241009

---

## Laporan Perbaikan Masalah (Task FIX — Minggu 7)

### 1. Judul Masalah
**Perbaikan Satu Route Dosen Tanpa Middleware (`GET /courses/create`)**

---

### 2. Lokasi Berkas & Baris Cacat
- **Berkas:** `routes/web.php`
- **Baris Cacat Awal:** Baris 22 (sebelum diperbaiki).
- **Potongan Kode Bermasalah:**
  ```php
  Route::middleware('guest')->group(function () {
      Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
      Route::post('/login', [AuthController::class, 'login']);
  });

  // [CACAT]: Berada di luar grup middleware auth (publik/tanpa middleware)
  Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');

  Route::middleware('auth')->group(function () {
      ...
  ```

---

### 3. Analisis Dampak Nyata & Risiko Keamanan

1. **Dampak dari Sudut Pandang Penyerang / Guest (Pengguna Tanpa Login):**
   - Rute `GET /courses/create` adalah endpoint untuk merender antarmuka formulir pembuatan mata kuliah baru (kurikulum, silabus, SKS, nama dosen, dan kode MK).
   - Karena rute ini didefinisikan secara bebas di luar blok `Route::middleware('auth')`, siapa pun di internet (tamu/unauthenticated user atau web crawler luar) dapat langsung membuka URL `http://<domain-lms>/courses/create` tanpa harus login terlebih dahulu.
   - Hal ini membocorkan struktur formulir internal akademik kampus, opsi input dosen pengampu, serta memicu celah eksploitasi lebih lanjut jika controller memiliki ketergantungan pada objek `auth()->user()`.

2. **Alasan Arsitektural & Konsep *Defense-in-Depth*:**
   - Dalam arsitektur keamanan web Laravel modern, keamanan tidak boleh hanya bergantung pada satu lapis pertahanan saja (*Single Point of Failure*).
   - Diperlukan prinsip **Pertahanan Berlapis (*Defense-in-Depth*)**:
     - **Lapis 1 (Perimeter Luar / Route Middleware):** Middleware `auth` bertindak sebagai gerbang terluar untuk menyaring dan memastikan bahwa request hanya berasal dari pengguna yang memiliki sesi login aktif. Tamu otomatis dipental (HTTP 302 Redirect ke `/login`).
     - **Lapis 2 (Penjaga Objek / Controller Policy):** `Gate::authorize('create', Course::class)` bertindak di dalam controller untuk memverifikasi hak akses spesifik (memastikan hanya user dengan role `dosen` atau `admin` yang boleh membuat mata kuliah).
   - Mengabaikan middleware pada level route meruntuhkan pertahanan perimeter terluar aplikasi.

3. **Pencegahan Masalah *First-Match-Wins* (Urutan Rute):**
   - Pemindahan rute `GET /courses/create` ke dalam grup `auth` wajib diletakkan di **ATAS** rute dinamis `GET /courses/{course}`.
   - Jika diletakkan di bawah `/courses/{course}`, string literal `"create"` akan ditangkap sebagai parameter wildcard `{course}`, mengakibatkan controller memproses `CourseController::show('create')` yang memicu error pencarian model `404 Not Found` atau kegagalan query database.

---

### 4. Perubahan Kode (Before vs After)

#### Before (`routes/web.php`):
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// [CACAT]: Route berada di area publik tanpa middleware auth
Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
```

#### After (`routes/web.php`):
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    // [PERBAIKAN]: Rute dipindahkan ke dalam middleware auth dan sebelum wildcard {course}
    Route::get('/courses/create', [CourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
```

---

### 5. Bukti Pengujian (Verifikasi cURL / Status HTTP)

#### A. Kondisi Sebelum Perbaikan (Cacat Masih Ada):
Pengujian request dilakukan oleh pengguna tamu (*guest*) tanpa mengirimkan session cookie terotentikasi:
```bash
curl -i http://127.0.0.1:8000/courses/create
```
**Respon Server:**
```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8

<!DOCTYPE html>
<html>
<!-- Formulir pembuatan mata kuliah berhasil dibuka oleh guest tanpa login -->
```
*Hasil:* Cacat terbukti nyata. Tamu tanpa login dapat menembus dan melihat form pembuatan mata kuliah (HTTP `200 OK`).

---

#### B. Kondisi Sesudah Perbaikan (Telah Dilindungi Middleware):
Pengujian request yang sama diulang setelah rute dipindahkan ke dalam grup `auth`:
```bash
curl -i http://127.0.0.1:8000/courses/create
```
**Respon Server:**
```http
HTTP/1.1 302 Found
Location: http://127.0.0.1:8000/login
```
*Hasil:* Tamu langsung dicegat oleh middleware `Illuminate\Auth\Middleware\Authenticate` dan dialihkan (HTTP `302 Found` Redirect) ke halaman login `/login`. Halaman hanya dapat dibuka jika pengguna telah login sebagai dosen atau admin.

---

### 6. Daftar Ringkas untuk Deskripsi Pull Request (PR)

Teks berikut dapat langsung disalin ke formulir PR GitHub:

```markdown
### Ringkasan Perbaikan:
- Memindahkan rute `GET /courses/create` ke dalam grup `Route::middleware('auth')` pada `routes/web.php`.
- Menempatkan rute `GET /courses/create` di atas rute wildcard `GET /courses/{course}` untuk mencegah collision rute (First-Match-Wins).

### Dampak Keamanan:
- Menutup akses publik tanpa autentikasi terhadap formulir pembuatan mata kuliah.
- Menegakkan prinsip Defense-in-Depth: middleware `auth` sebagai perimeter terluar dan Policy otorisasi di dalam controller.
- Tamu (guest) yang mencoba mengakses `/courses/create` kini otomatis dialihkan ke halaman login (HTTP 302).
```
