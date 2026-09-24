# FIX MINGGU 4 - LAPORAN PERBAIKAN BUG

## Identitas Mahasiswa
- **Nama** : Agus Liberty Purba
- **NIM**  : 10241005
- **Branch**: `W04`
- **Fokus Tanggung Jawab Utama**: Filter disimpan di session (Nomor 3) & Verifikasi Seluruh 6 Bugfix

---

## Ringkasan 6 Perbaikan Bug

| # | Permasalahan | Lokasi Berkas (Path) | Status |
|---|---|---|:---:|
| 1 | Validasi hanya di frontend | `app/Http/Controllers/CourseController.php` | **SELESAI** |
| 2 | Unique pada update menolak dirinya sendiri | `app/Http/Controllers/CourseController.php` | **SELESAI** |
| 3 | Filter disimpan di session | `app/Http/Controllers/CourseController.php` | **SELESAI** |
| 4 | Pagination kehilangan query string | `app/Http/Controllers/CourseController.php` | **SELESAI** |
| 5 | Store tanpa redirect (pelanggaran pola PRG) | `app/Http/Controllers/CourseController.php` | **SELESAI** |
| 6 | Satu form tanpa `@csrf` | `resources/views/courses/create.blade.php` | **SELESAI** |

---

## 1. Validasi Hanya di Frontend

### Lokasi Berkas
`app/Http/Controllers/CourseController.php` (method `store()`)

### Masalah
Method `store()` sebelumnya langsung mengambil data dari `$request` dan menyimpannya ke database via `Course::create([...])` tanpa ada validasi server-side. Pengecekan hanya mengandalkan atribut HTML5 di view `resources/views/courses/create.blade.php` (`required`, `min="1"`, `max="6"`).

### Penyebab & Bahaya
Validasi frontend berada di sisi client dan sangat mudah dimatikan atau dilewati (misalnya via inspect element DevTools, script otomatis, atau HTTP client seperti `curl`/Postman). Tanpa validasi server:
- Kolom wajib bisa dikirimkan kosong.
- Nilai SKS bisa diisi angka di luar aturan (misal 99 atau minus).
- Kolom `lecturer_id` bisa diisi sembarang ID yang tidak ada di tabel `users`, menyebabkan error SQL fatal (*Foreign Key constraint violation*).
- Status publikasi bisa disusupi nilai liar di luar enum (`draft`, `active`, `archived`).

### Perbaikan
Menambahkan validasi sisi server menggunakan `$request->validate()` dan memastikan penyimpanan hanya menggunakan array `$validated`:
```php
$validated = $request->validate([
    'code'        => 'required|string|max:20|unique:courses,code',
    'name'        => 'required|string|max:255',
    'description' => 'nullable|string',
    'sks'         => 'required|integer|min:1|max:6',
    'lecturer_id' => 'required|exists:users,id',
    'status'      => 'required|in:draft,active,archived',
], [
    // Pesan ramah dalam Bahasa Indonesia
]);

Course::create($validated);
```

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix1_valid_course_can_be_stored()`
- `test_fix1_invalid_course_rejected_by_server_validation()`
- `test_fix1_duplicate_code_rejected_on_store()`

---

## 2. Unique pada UPDATE Menolak Dirinya Sendiri

### Lokasi Berkas
`app/Http/Controllers/CourseController.php` (method `update()`)

### Masalah
Aturan validasi kolom unik pada pembaruan data ditulis sebagai `'code' => 'required|string|max:20|unique:courses,code'`. Saat pengguna memperbarui informasi mata kuliah (misalnya mengubah nama atau deskripsi) tanpa mengganti kode mata kuliah, sistem menolak update tersebut dengan alasan kode sudah digunakan.

### Penyebab & Bahaya
Aturan `unique:courses,code` secara default memeriksa seluruh baris tabel `courses`. Karena kode mata kuliah tersebut memang sudah ada di database (milik record yang sedang diedit), validator mendeteksinya sebagai duplikat (*false positive*). Akibatnya, pengguna tidak pernah bisa menyimpan pembaruan kecuali terpaksa mengubah kode mata kuliah.

### Perbaikan
Menggunakan class rule `Rule::unique` dengan mengecualikan ID course yang sedang diedit:
```php
use Illuminate\Validation\Rule;

// Di dalam method update():
$validated = $request->validate([
    'code' => ['required', 'string', 'max:20', Rule::unique('courses', 'code')->ignore($course->id)],
    // ... aturan lainnya
]);
```

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix2_update_course_without_changing_unique_code_succeeds()`
- `test_fix2_update_course_with_another_courses_code_fails()`

---

## 3. Filter Disimpan di Session (Fokus Tanggung Jawab Utama)

### Lokasi Berkas
`app/Http/Controllers/CourseController.php` (method `index()`)

### Masalah
Sebelumnya, method `index()` menyimpan parameter pencarian dan filter status ke dalam session browser pengguna:
```php
// KODE CACAT SEBELUMNYA:
if ($request->has('search')) {
    session(['course_search' => $request->search]);
}
if ($request->has('status')) {
    session(['course_status' => $request->status]);
}

$search = session('course_search');
$status = session('course_status', 'active');
```

### Penyebab & Bahaya
Menyimpan filter di session menghasilkan masalah arsitektur serius (*Sticky Filter Bug*):
1. **Melanggar Prinsip Stateless HTTP:** Filter tersimpan di state server/session pengguna, bukan di URL query string.
2. **Filter Menempel Tanpa Akhir:** Ketika pengguna ingin kembali melihat seluruh mata kuliah dengan mengakses URL bersih `/courses`, daftar tetap terfilter karena nilai session sebelumnya tidak terhapus.
3. **Link Tidak Dapat Dibagikan (Unshareable URL):** URL pencarian tidak merepresentasikan data yang tampil. Jika pengguna membagikan URL `/courses` ke dosen lain, dosen tersebut tidak melihat hasil yang sama.
4. **Banyak Tab Bentrok:** Membuka filter berbeda di tab browser yang berbeda akan saling menimpa nilai session satu sama lain.

### Perbaikan
Menghapus seluruh dependensi session untuk filter dan membaca nilai pencarian langsung dari parameter HTTP Request:
```php
public function index(Request $request)
{
    $query = Course::with('lecturer')->withCount('students');

    if ($request->filled('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', '%'.$search.'%')
              ->orWhere('code', 'like', '%'.$search.'%');
        });
    }

    if ($request->filled('status')) {
        $query->where('status', $request->status);
    } else {
        $query->where('status', 'active');
    }

    $courses = $query->paginate(10)->withQueryString();

    return view('courses.index', compact('courses'));
}
```

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix3_filters_are_stateless_and_not_stored_in_session()`
  Memverifikasi bahwa saat pencarian dilakukan, `session()->has('course_search')` bernilai `false`, dan saat mengakses URL bersih `/courses` setelahnya, seluruh data aktif tampil normal tanpa terikat filter lama.

---

## 4. Pagination Kehilangan Query String

### Lokasi Berkas
`app/Http/Controllers/CourseController.php` (method `index()`)

### Masalah
Pemanggilan pagination pada controller sebelumnya ditulis:
```php
// KODE CACAT SEBELUMNYA:
$courses = $query->paginate(10);
```
tanpa menyertakan method `->withQueryString()`.

### Penyebab & Bahaya
Ketika pengguna mencari mata kuliah (misalnya `?search=Web&status=active`) dan hasil pencarian terdiri dari beberapa halaman, link nomor halaman di bagian pagination yang dibuat Laravel secara default hanya membawa parameter `?page=2`. Parameter `search` dan `status` terbuang dari URL, sehingga ketika pengguna mengklik halaman 2, hasil filter langsung hilang dan sistem kembali menampilkan seluruh mata kuliah umum.

### Perbaikan
Menambahkan `->withQueryString()` pada pemanggilan pagination:
```php
$courses = $query->paginate(10)->withQueryString();
```

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix4_pagination_preserves_query_string()`
  Memverifikasi bahwa tautan HTML pagination halaman berikutnya tetap mempertahankan string query `search=...` dan `status=...`.

---

## 5. Store Tanpa Redirect

### Lokasi Berkas
`app/Http/Controllers/CourseController.php` (method `store()`)

### Masalah
Setelah menyimpan record mata kuliah baru, controller sebelumnya langsung merender view index secara langsung:
```php
// KODE CACAT SEBELUMNYA:
Course::create($validated);
$courses = Course::paginate(10);
return view('courses.index', compact('courses'));
```

### Penyebab & Bahaya
Pola ini melanggar standar arsitektur web **Post/Redirect/Get (PRG)**:
1. **Duplikasi Data (Double Submit):** Jika pengguna me-refresh halaman (F5) setelah menyimpan form, browser akan memunculkan dialog konfirmasi pengiriman ulang form (*Confirm Form Resubmission*). Jika disetujui, data yang sama akan tersimpan ganda di database.
2. **Logic Bypass:** Merender `view('courses.index')` secara langsung di `store()` memotong logika eager-loading (`with('lecturer')`), pencarian, dan filtering yang seharusnya berjalan melalui method `index()`.
3. **URL Tidak Sinkron:** Halaman menampilkan daftar index, tetapi URL di browser masih berada pada endpoint POST `/courses`.

### Perbaikan
Mengubah return value menjadi HTTP Redirect ke route `courses.index` disertai pesan flash session:
```php
return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil ditambahkan.');
```

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix5_store_redirects_to_index_with_flash_message()`
  Memverifikasi bahwa response menghasilkan status HTTP 302 (redirect) menuju route `courses.index` dan menyertakan session success.

---

## 6. Satu Form Tanpa `@csrf`

### Lokasi Berkas
`resources/views/courses/create.blade.php` (baris form pembuka)

### Masalah
Tag `<form action="{{ route('courses.store') }}" method="POST">` pada form pembuatan mata kuliah baru tidak menyertakan directive proteksi `@csrf`.

### Penyebab & Bahaya
1. **Error HTTP 419:** Middleware keamanan Laravel (`ValidateCsrfToken`) secara ketat menolak setiap request mutasi HTTP POST yang tidak membawa token valid, sehingga pengguna akan selalu disambut halaman error `419 Page Expired`.
2. **Celah Serangan CSRF:** Tanpa validasi token acak per sesi, aplikasi rentan terhadap serangan *Cross-Site Request Forgery*, di mana pihak penyerang dapat mengecoh browser pengguna yang sedang login untuk mengirimkan data ke aplikasi secara diam-diam.

### Perbaikan
1. Menambahkan directive `@csrf` tepat di bawah tag form:
```blade
<form action="{{ route('courses.store') }}" method="POST" class="space-y-5">
    {{-- Form ini sebelumnya tanpa @csrf yang menyebabkan error 419 Page Expired saat disubmit, kemudian ditambahkan @csrf untuk menyertakan token keamanan agar request POST valid dan data berhasil disimpan. --}}
    @csrf
    <div class="grid grid-cols-2 gap-4">
    ...
```
2. Memperbaiki sintaks catatan yang sebelumnya memakai `// ...` (komentar JS/PHP yang bocor menjadi teks HTML mentah di web) diubah menjadi komentar Blade yang valid `{{-- ... --}}`.

### Bukti Pembuktian
Tercakup dalam unit test `tests/Feature/FixMinggu4Test.php`:
- `test_fix6_create_form_contains_csrf_token()`
  Memverifikasi bahwa form create berhasil merender input tersembunyi `_token`.

---

## Kesimpulan & Hasil Pengujian
Seluruh 6 masalah yang ada pada branch `W04` repositori cacat telah diperiksa, diverifikasi, diperbaiki, dan dilengkapi pengujian otomatis pada `tests/Feature/FixMinggu4Test.php`. Semua perbaikan telah selaras dengan standar arsitektur web Laravel dan siap diajukan untuk Pull Request.
