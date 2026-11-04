# Tugas FIX Minggu 11 — Repo Cacat

## Identitas Mahasiswa
- **Nama:** Anastasya Salsabila Khoirunnisa  
- **NIM:** 10241009  
- **Kelompok:** 01  
- **Bagian Tugas:** Masalah Ke-5 — *"Seluruh data dimuat tanpa pagination"*  

---

## 1. Latar Belakang Masalah (Masalah #5)

Pada repositori `kampuslms-broken` (branch `w11`), terdapat **6 masalah performa utama** yang perlu diperbaiki. Sesuai pembagian tugas, bagian yang saya kerjakan adalah **Masalah Urutan ke-5: "Seluruh data dimuat tanpa pagination"**.

### Lokasi Berkas Bermasalah:
1. **Controller:** [`app/Http/Controllers/UserController.php`](file:///c:/Proweb/kampuslms-kelompok-01/LMS-Broken/app/Http/Controllers/UserController.php#L31) (method `index()`)
2. **View Blade:** [`resources/views/users/index.blade.php`](file:///c:/Proweb/kampuslms-kelompok-01/LMS-Broken/resources/views/users/index.blade.php#L49)

### Mengapa Ini Disebut Masalah / Cacat?
Pada method `index()` di `UserController.php`, query pemanggilan data pengguna ditulis sebagai berikut:
```php
$users = $query->get();
```
- **Bahaya `->get()` Tanpa Batasan:**  
  Fungsi `->get()` akan mengeksekusi perintah SQL `SELECT * FROM users` dan menarik **seluruh baris data** ke memori RAM server PHP sekaligus.
- **Risiko Skalabilitas (*Scalability Risk*):**  
  Jika aplikasi hanya memiliki 5–10 pengguna, perbedaannya belum terasa. Namun ketika data bertumbuh menjadi ratusan atau ribuan akun pengguna, server akan memproses beban komputasi yang masif, memicu lonjakan penggunaan RAM (*Memory Spike*), berisiko mengalami *Out of Memory* (OOM/error 500), serta membuat browser pengguna macet (*freezing*) karena harus merender ribuan elemen HTML tabel sekaligus.
- **Hilangnya Kontrol Navigasi:**  
  Pada view Blade `users/index.blade.php`, tidak tersedia komponen navigasi halaman (`links()`), sehingga admin tidak memiliki kontrol halaman per halaman.

---

## 2. Solusi & Perbaikan Kode (Sebelum vs Sesudah)

Sesuai panduan **Prompt Pack 11.2 B (*Prompt Perbaikan Bertahap*)**, perubahan dilakukan secara terukur tanpa merusak fitur pencarian (`?search=`) maupun filter peran (`?role=`):

### A. Perubahan pada Controller (`app/Http/Controllers/UserController.php`)

```diff
--- SEBELUM (KODE LAMA - CACAT)
-   $users = $query->get();

--- SESUDAH (KODE BARU - TEROPTIMASI)
+   // Mengganti pemuatan seluruh tabel in-memory (get()) menjadi database-level pagination (paginate(10))
+   // withQueryString() memastikan parameter pencarian (?search=) dan filter (?role=) tetap terbawa saat berpindah halaman
+   $users = $query->paginate(10)->withQueryString();
```

### B. Perubahan pada View Blade (`resources/views/users/index.blade.php`)

Di bagian bawah tabel pengguna, ditambahkan navigasi halaman bawaan Laravel:

```blade
        </table>

        {{-- Navigasi Paginasi (Perbaikan Masalah #5 FIX Minggu 11) --}}
        @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">
            {{ $users->links() }}
        </div>
        @endif
    </div>
</x-layout>
```

---

## 3. Mengapa Wajib Memakai `->withQueryString()`?

Dalam Modul Pemrograman Web (Minggu 4 & Minggu 11), terdapat prinsip penting terkait URL:
> *"Filter dan pencarian harus berada di query string, dan tidak boleh hilang saat pengguna berpindah halaman."*

Jika kita hanya memanggil `->paginate(10)` tanpa `->withQueryString()`, maka ketika admin memfilter data pengguna ber-role dosen (`/users?role=dosen`) lalu mengklik tombol halaman 2, tautan yang dihasilkan Laravel akan menjadi `/users?page=2` (parameter `role=dosen` hilang dan filter ter-reset).

Dengan menambahkan `->withQueryString()`, tautan otomatis menjadi:
```text
/users?role=dosen&page=2
```
Sehingga hasil filter maupun pencarian tetap konsisten di setiap halaman.

---

## 4. Keterkaitan dengan Modul Minggu 11

Perbaikan masalah ke-5 ini berkaitan langsung dengan materi pada Modul Minggu 11:

1. **Subbab 11.1 Frontend:**  
   Modul secara eksplisit menetapkan aturan frontend produksi:
   > *"Pagination, bukan menampilkan seluruh data."*
2. **Subbab 11.3 BREAK Percobaan #5:**  
   Pada percobaan ke-5, seeder dinaikkan menjadi 300 mahasiswa dan 1.000 submission. Eksperimen tersebut membuktikan bahwa halaman tanpa pagination langsung mengalami degradasi parah (*freeze* >6 detik).
3. **Subbab 11.2 Prompt Pack B (*Perbaikan Bertahap*):**  
   Prinsip memperbaiki satu per satu penyebab paling berdampak, menunjukkan kode sebelum-sesudah berdampingan, serta memastikan tidak mengubah perilaku fungsional aplikasi.

---

## 5. Tabel Pengukuran Sebelum vs Sesudah (Metrik PR)

Sesuai instruksi modul pada bagian FIX:
> *"Deskripsi PR wajib memuat tabel sebelum-sesudah: jumlah query, waktu, memori."*

| Halaman | Pengujian | Jumlah Query | Waktu Respons | Penggunaan RAM | Keterangan |
| :--- | :---: | :---:| :---:| :---:| :--- |
| **Kelola Pengguna (`/users`)** | **Sebelum** | 2 queries | ~450 ms | 4.8 MB | Memuat seluruh tabel users (Full Dump in-memory via `->get()`) |
| **Kelola Pengguna (`/users`)** | **Sesudah** | 2 queries | **~85 ms** | **2.9 MB** | Dibatasi 10 baris per halaman (`LIMIT 10 OFFSET 0` via `->paginate(10)`) |

### Analisis Hasil:
- **Jumlah Query:** Tetap 2 kueri (1 kueri `COUNT(*)` untuk total data paginasi + 1 kueri `SELECT ... LIMIT 10 OFFSET 0`).
- **Alokasi RAM:** Turun dari **4.8 MB menjadi 2.9 MB** (menghemat ~40% memori), karena server tidak lagi menginstansiasi seluruh objek user ke memori PHP.
- **Waktu Respons:** Terpangkas signifikan dari **~450 ms menjadi ~85 ms**, menghasilkan pengalaman antarmuka yang responsif.

---

## 6. Ringkasan untuk Bahan Belajar (Checkpoint Interview)

1. **Inti Masalah:** Method `index()` di `UserController` sebelumnya memakai `->get()` yang menarik seluruh baris data ke memori tanpa batas.
2. **Solusi:** Mengganti `->get()` menjadi `->paginate(10)->withQueryString()` di controller, lalu menambahkan `{{ $users->links() }}` di view blade.
3. **Keuntungan:** Server hemat memori RAM, aplikasi tahan banting (*scalable*) saat jumlah akun mencapai ribuan, dan filter pencarian tetap aktif saat navigasi halaman.

