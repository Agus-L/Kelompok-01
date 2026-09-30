# FIX MINGGU 4

## Identitas
Nama: Anastasya Salsabila Khoirunnisa
NIM: 10241009

## 1. Validasi Hanya di Frontend

### Masalah
Pada kondisi awal sebelum perbaikan, method `store()` pada `CourseController.php` tidak memiliki validasi di sisi server (server-side validation). Method tersebut langsung mengambil input mentah dari `$request` (`$request->code`, `$request->name`, `$request->description`, `$request->sks`, `$request->lecturer_id`, `$request->status`) dan langsung memanggil `Course::create([...])`. Validasi hanya mengandalkan atribut HTML5 di view `resources/views/courses/create.blade.php` seperti `required`, `min="1"`, dan `max="6"`.

### Penyebab
Validasi hanya di frontend bukan merupakan mekanisme keamanan karena frontend sepenuhnya berada di sisi klien (client-side) dan berada di luar kendali server. Atribut HTML seperti `required`, `min`, `max`, maupun validasi JavaScript dapat dilewati dengan sangat mudah, misalnya dengan memodifikasi form lewat Developer Tools browser, menonaktifkan JavaScript, atau mengirim HTTP POST request langsung menggunakan `curl`, Postman, atau script. Tanpa validasi server, data yang kosong, SKS di luar batas (misal 99), status yang tidak valid, atau ID dosen fiktif (foreign key tidak ada di tabel users) akan langsung masuk ke database atau memicu uncaught database exception.

### Perbaikan
Menerapkan validasi di sisi server pada method `store()` di `CourseController.php` menggunakan `$request->validate()` sesuai dengan pola arsitektur yang digunakan di controller lain pada project:
1. `code`: `['required', 'string', 'max:20', 'unique:courses,code']`
2. `name`: `['required', 'string', 'max:255']`
3. `description`: `['nullable', 'string']`
4. `sks`: `['required', 'integer', 'min:1', 'max:6']`
5. `lecturer_id`: `['required', 'exists:users,id']` (mencegah data yatim / orphan foreign key)
6. `status`: `['required', 'in:draft,active,archived']` (mengunci nilai enum)
7. Menyertakan pesan error spesifik dalam bahasa Indonesia yang ramah pada parameter kedua `$request->validate()`.
8. Menggunakan hasil array `$validated` dari `$request->validate()` pada `Course::create($validated)` (bukan `$request->all()`), sehingga secara efektif mencegah celah mass assignment.

### Pengujian
Pengujian dilakukan secara nyata menggunakan Feature Test Laravel (`tests/Feature/FixMinggu4Test.php`) yang menyimulasikan request langsung ke endpoint POST `/courses`:
1. **Pengujian Data Valid:** Mengirim request POST dengan data lengkap dan valid (`code` = 'CS101', `name` = 'Dasar Pemrograman', `sks` = 3, `lecturer_id` = ID dosen valid, `status` = 'active').
2. **Pengujian Penolakan Data Tidak Valid (Bypass Frontend):** Mengirim request POST langsung tanpa melalui form browser dengan data tidak valid: `name` = '', `sks` = 99, `lecturer_id` = 99999, `status` = 'unauthorized_status'.
3. **Pengujian Duplikasi Kode:** Mengirim request POST dengan `code` yang sudah terdaftar di database ('CS102').

### Hasil
1. **Data Valid:** Server berhasil memvalidasi dan memproses request, mengembalikan redirect ke `courses.index` (HTTP status 302), menyertakan flash message 'success', dan data berhasil tersimpan di database `courses` (`assertDatabaseHas`).
2. **Data Tidak Valid:** Server berhasil menolak request, mengembalikan redirect kembali (HTTP status 302) dengan session error untuk field `name`, `sks`, `lecturer_id`, dan `status`. Data dipastikan tidak masuk ke database (`assertDatabaseMissing`).
3. **Duplikasi Kode:** Server menolak request dan memberikan session error pada field `code` ("Kode mata kuliah ini sudah dipakai.").

### Dampak bagi Pengguna
- **Bagi Pengguna Umum:** Pengguna mendapatkan umpan balik (feedback) yang jelas dan spesifik dalam bahasa Indonesia ketika salah mengisi form, dan input sebelumnya tetap dipertahankan dengan `old()` sehingga tidak perlu mengetik ulang seluruh form dari awal.
- **Integritas Data:** Pengguna terhindar dari anomali data seperti mata kuliah dengan SKS tidak wajar atau mata kuliah tanpa pengampu yang valid.

### Dampak bagi Penyerang
Jika validasi hanya di frontend:
- Penyerang dapat memasukkan `lecturer_id` fiktif yang merusak konsistensi relasi basis data.
- Penyerang dapat menyuntikkan status di luar enum aplikasi (`draft`, `active`, `archived`).
- Penyerang dapat mencoba mass assignment dengan menyisipkan field yang tidak sah.
Setelah diperbaiki di server-side:
- Semua request ilegal yang melewati frontend langsung ditolak oleh filter validasi Laravel sebelum dieksekusi oleh query Eloquent/Database.

---

## 2. Unique pada UPDATE Menolak Dirinya Sendiri

### Masalah
Pada kondisi awal `CourseController::update()`, aturan validasi untuk kolom unik ditulis sebagai:
`'code' => 'required|string|max:20|unique:courses,code'`
Ketika pengguna ingin memperbarui data mata kuliah (misalnya mengubah nama, deskripsi, dosen, atau SKS) tanpa mengubah kode mata kuliah tersebut, validasi Laravel menjalankan query pengecekan duplikasi `SELECT count(*) FROM courses WHERE code = ?`. Karena kode tersebut sudah ada (yaitu milik record itu sendiri), validasi menganggapnya sebagai duplikat dan menolak update dengan pesan bahwa kode mata kuliah sudah dipakai.

### Penyebab
Aturan `unique:courses,code` secara default memeriksa seluruh baris di tabel `courses` tanpa mengabaikan ID dari record yang sedang diperbarui. Hal ini cocok untuk operasi STORE, namun pada operasi UPDATE, record yang sedang diedit sudah memiliki nilai tersebut secara sah di database. Tanpa menyertakan klausul `ignore()`, record akan memvalidasi terhadap dirinya sendiri dan menyebabkan penolakan palsu (false positive).

### Perbaikan
Mengubah aturan validasi kolom `code` pada method `update()` di `CourseController.php` dengan menambahkan `Rule::unique('courses', 'code')->ignore($course->id)`:
```php
'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course->id)],
```
Serta menambahkan impor `use Illuminate\Validation\Rule;` pada bagian header controller. Dengan demikian, query SQL yang dieksekusi Laravel secara otomatis menyertakan klausul pengecualian `AND id != ?` untuk ID record yang sedang diproses.

### Pengujian
Pengujian nyata dilakukan melalui Feature Test Laravel (`tests/Feature/FixMinggu4Test.php`) dengan skenario:
1. **UPDATE tanpa mengubah nilai unik:** Memperbarui nama dan SKS pada Course yang sudah ada (`code` = 'CS201') dengan tetap mempertahankan kodenya ('CS201').
2. **UPDATE menggunakan nilai unik milik record lain:** Memperbarui Course B (`code` = 'CS302') menggunakan kode yang sudah dimiliki Course A ('CS301').

### Hasil
1. **UPDATE tanpa mengubah nilai unik:** Request PUT berhasil diproses tanpa penolakan. Server mengembalikan HTTP 302 redirect ke `courses.show`, flash message berhasil diset, dan perubahan data berhasil tersimpan di database (`assertDatabaseHas`).
2. **UPDATE menggunakan nilai unik milik record lain:** Server menolak request dan mengembalikan session error untuk field `code` ("Kode mata kuliah ini sudah dipakai."). Data Course B di database tetap mempertahankan kode aslinya ('CS302').

### Dampak bagi Pengguna
- **Bagi Pengguna Umum:** Pengguna dapat dengan leluasa memperbarui atribut mata kuliah apa pun tanpa dipaksa untuk mengubah kode mata kuliah yang sebenarnya sudah benar.
- **Konsistensi UX:** Menghilangkan keluhan dan frustrasi pengguna kampus akibat kegagalan update data yang tidak masuk akal.

### Dampak bagi Penyerang
Jika aturan unique dihilangkan sama sekali pada update untuk menghindari bug tersebut:
- Penyerang dapat menduplikasi kode mata kuliah lain sehingga terjadi tabrakan identitas data (*duplicate unique constraint violation* di level DB atau duplikasi logis).
Dengan penggunaan `Rule::unique()->ignore($course->id)`:
- Keunikan kode mata kuliah antar entitas yang berbeda tetap ditegakkan dengan ketat, sementara record yang bersangkutan diperbolehkan mempertahankan identitas uniknya sendiri.

---

## Kesimpulan
Dua permasalahan pada bagian FIX MINGGU 4 telah berhasil diperbaiki dan diuji:
1. **Validasi Hanya di Frontend (FIX 1):** Telah diatasi dengan menambahkan validasi server-side lengkap pada method `CourseController::store()` menggunakan `$request->validate()` dan penyimpanan berbasis `$validated`.
2. **Unique pada UPDATE Menolak Dirinya Sendiri (FIX 2):** Telah diatasi dengan menambahkan `Rule::unique('courses', 'code')->ignore($course->id)` pada method `CourseController::update()`.

Seluruh pengujian nyata pada kedua skenario berhasil lulus (PASS) 100% tanpa menyentuh atau mengubah bagian yang menjadi tanggung jawab teman kelompok lainnya.
