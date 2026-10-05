# Laporan Pengerjaan FIX Minggu 06 - Status Code pada `store` dan `destroy`

- **Nama**: Anastasya Salsabila Khoirunnisa
- **NIM**: 10241009
- **Kelompok**: 01
- **Repository**: Agus-L/Kelompok-01 (`LMS-Broken`)
- **Branch**: `W06`
- **Bagian Tugas FIX**: Status code salah pada `store` dan `destroy`

---

## 1. Deskripsi Masalah

Pada branch `W06` repositori `LMS-Broken`, ditemukan ketidaksesuaian status code HTTP pada RESTful API controller `app/Http/Controllers/Api/CourseController.php`:

1. **Method `store` (Pembuatan Data Mata Kuliah)**:
   - Mengembalikan HTTP status code **200 OK**.
   - Menurut standar RESTful API dan spesifikasi modul praktikum, pembuatan resource baru yang berhasil harus mengembalikan status code **201 Created**.
2. **Method `destroy` (Penghapusan Data Mata Kuliah)**:
   - Mengembalikan HTTP status code **200 OK** beserta response body JSON `{"message": "Mata kuliah berhasil dihapus"}`.
   - Menurut standar RESTful API dan spesifikasi modul praktikum, penghapusan resource yang berhasil harus mengembalikan status code **204 No Content** tanpa response body.

---

## 2. Kondisi Sebelum Perbaikan

Kondisi kode pada [app/Http/Controllers/Api/CourseController.php](file:///C:/Proweb/kampuslms-kelompok-01/LMS-Broken/app/Http/Controllers/Api/CourseController.php) sebelum dilakukan perbaikan:

### Method `store()`
```php
public function store(Request $request)
{
    $validated = $request->validate([
        'code'        => 'required|string|max:10|unique:courses,code',
        'name'        => 'required|string|max:255',
        'credits'     => 'required|integer|min:1|max:6',
        'lecturer_id' => 'required|exists:users,id',
    ]);

    $course = Course::create($validated);
    $course->load('lecturer');

    return response()->json(new CourseResource($course), 200);
}
```

### Method `destroy()`
```php
public function destroy(Course $course)
{
    // Authorization check
    if ($course->lecturer_id !== auth()->id() && auth()->user()->role !== 'admin') {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    $course->delete();

    return response()->json(['message' => 'Mata kuliah berhasil dihapus'], 200);
}
```

---

## 3. Perbaikan yang Dilakukan

Perbaikan dilakukan secara spesifik hanya pada return response method `store()` dan `destroy()` pada file [app/Http/Controllers/Api/CourseController.php](file:///C:/Proweb/kampuslms-kelompok-01/LMS-Broken/app/Http/Controllers/Api/CourseController.php):

1. **Pada method `store()`**:
   Mengubah return response agar memanfaatkan format resource transformer dengan status code 201:
   ```php
   return (new CourseResource($course))
       ->response()
       ->setStatusCode(201);
   ```
   *Catatan*: Menggunakan `(new CourseResource($course))->response()->setStatusCode(201)` memastikan respons tetap memiliki key pembungkus `data` sesuai standar JsonResource Laravel dan kontrak pengujian API.

2. **Pada method `destroy()`**:
   Mengubah return response agar mengembalikan helper `response()->noContent()`:
   ```php
   return response()->noContent();
   ```
   *Catatan*: Helper `response()->noContent()` di Laravel secara otomatis menghasilkan HTTP status 204 No Content dan mengosongkan response body.

### Ringkasan Diff Perubahan
```diff
--- a/app/Http/Controllers/Api/CourseController.php
+++ b/app/Http/Controllers/Api/CourseController.php
@@ -43,7 +43,9 @@ public function store(Request $request)
         $course = Course::create($validated);
         $course->load('lecturer');
 
-        return response()->json(new CourseResource($course), 200);
+        return (new CourseResource($course))
+            ->response()
+            ->setStatusCode(201);
     }
 
     public function show(Course $course)
@@ -77,6 +79,6 @@ public function destroy(Course $course)
 
         $course->delete();
 
-        return response()->json(['message' => 'Mata kuliah berhasil dihapus'], 200);
+        return response()->noContent();
     }
 }
```

---

## 4. Pengujian dan Bukti Hasil

Pengujian dilakukan melalui dua pendekatan: pengujian otomatis menggunakan PHPUnit Feature Test dan pengujian manual menggunakan `curl`.

### A. Pengujian Otomatis (PHPUnit / Pest Test)

File test `tests/Feature/ApiTest.php` dijalankan untuk memastikan endpoint `store` mengembalikan 201 dan `destroy` mengembalikan 204.

#### Perintah:
```bash
php artisan test tests/Feature/ApiTest.php
```

#### Hasil Eksekusi Test:
```
   PASS  Tests\Feature\ApiTest
  ✓ api courses returns correct json structure                            0.18s  
  ✓ api course show returns single course                                 0.03s  
  ✓ api courses requires authentication                                   0.02s  
  ✓ api store returns 201 created                                         0.04s  
  ✓ api destroy returns 204 no content                                    0.03s  

  Tests:    5 passed (13 assertions)
  Duration: 0.74s
```

Semua pengujian pada `tests/Feature/ApiTest.php` berhasil lulus (PASS), membuktikan status 201 untuk `store` dan 204 untuk `destroy` terpenuhi.

---

### B. Pengujian Manual Menggunakan `curl`

Server Laravel dijalankan secara lokal pada `http://127.0.0.1:8081`.

#### 1. Autentikasi untuk Mendapatkan Bearer Token
```bash
curl.exe -i -X POST http://127.0.0.1:8081/api/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\":\"dosen@kampuslms.test\",\"password\":\"password\"}"
```

Hasil response:
```http
HTTP/1.1 200 OK
Host: 127.0.0.1:8081
Content-Type: application/json

{"token":"1|laravel_sanctum_..."}
```

Token yang didapat digunakan sebagai header `Authorization: Bearer <TOKEN>` pada permintaan berikutnya.

---

#### 2. Pengujian Endpoint `store` (`POST /api/courses`)

Perintah `curl`:
```bash
curl.exe -i -X POST http://127.0.0.1:8081/api/courses \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"code\":\"CS999\",\"name\":\"Cloud Computing\",\"credits\":3,\"lecturer_id\":2}"
```

Hasil Response Header & Body:
```http
HTTP/1.1 201 Created
Host: 127.0.0.1:8081
Connection: close
X-Powered-By: PHP/8.5.10
Cache-Control: no-cache, private
Date: Mon, 05 Oct 2026 14:43:26 GMT
Content-Type: application/json
Access-Control-Allow-Origin: *

{"data":{"id":6,"code":"CS999","name":"Cloud Computing","credits":3,"lecturer":{"id":2,"name":"Dr. Budi Santoso","email":"dosen@kampuslms.test"}}}
```

**Status**: `HTTP/1.1 201 Created` berhasil dikembalikan bersama payload resource data yang valid.

---

#### 3. Pengujian Endpoint `destroy` (`DELETE /api/courses/{id}`)

Perintah `curl`:
```bash
curl.exe -i -X DELETE http://127.0.0.1:8081/api/courses/6 \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Accept: application/json"
```

Hasil Response Header & Body:
```http
HTTP/1.1 204 No Content
Host: 127.0.0.1:8081
Connection: close
X-Powered-By: PHP/8.5.10
Cache-Control: no-cache, private
Date: Mon, 05 Oct 2026 14:45:18 GMT
Access-Control-Allow-Origin: *

```

**Status**: `HTTP/1.1 204 No Content` berhasil dikembalikan dan response body kosong (tanpa body).

---

## 5. Kesimpulan

1. Status code pada method `CourseController::store` berhasil diperbaiki dari `200` menjadi `201 Created`.
2. Status code pada method `CourseController::destroy` berhasil diperbaiki dari `200` dengan JSON pesan menjadi `204 No Content` tanpa body response.
3. Seluruh pengujian fitur dan pengujian manual menggunakan `curl` menghasilkan status code yang sesuai dengan spesifikasi modul REST API Minggu 06.
4. Perbaikan dibatasi secara ketat hanya pada bagian tugas yang diberikan tanpa menyentuh bagian tugas anggota kelompok lain atau melakukan commit/push Git.
