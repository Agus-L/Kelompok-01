# Catatan Perbaikan Repo Cacat (FIX) — Minggu 10

## Identitas Mahasiswa

- **Nama:** Anastasya Salsabila Khoirunnisa  
- **NIM:** 10241009  
- **Branch:** `W10`  
- **Repositori:** `kampuslms-broken` (LMS-Broken)  
- **Fokus Perbaikan:** Masalah **"Tidak ada `failed_jobs`"**  

---

## 1. Deskripsi Masalah

Pada branch `w10` di repositori `kampuslms-broken`, terdapat 7 masalah arsitektur antrean dan notifikasi. Bagian tugas yang saya kerjakan adalah mengatasi **ketiadaan skema migrasi tabel `failed_jobs`**.

Di Laravel, ketika sistem menggunakan antrean database (`QUEUE_CONNECTION=database`), framework membutuhkan tabel `failed_jobs` untuk mencatat riwayat job yang mengalami kegagalan permanen setelah semua percobaan retry (`maxTries`) habis.

---

## 2. Lokasi Cacat pada Kode

- **Berkas yang Bermasalah:**  
  [`database/migrations/0001_01_01_000002_create_jobs_table.php`](file:///c:/Proweb/kampuslms-kelompok-01/LMS-Broken/database/migrations/0001_01_01_000002_create_jobs_table.php)
- **Konfigurasi Terkait:**  
  [`config/queue.php`](file:///c:/Proweb/kampuslms-kelompok-01/LMS-Broken/config/queue.php#L106-L110) pada blok konfigurasi `failed`:
  ```php
  'failed' => [
      'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
      'database' => env('DB_CONNECTION', 'sqlite'),
      'table' => 'failed_jobs',
  ],
  ```
- **Kondisi Awal (Cacat):**  
  Pada berkas migrasi `0001_01_01_000002_create_jobs_table.php`, blok pembentukan tabel `failed_jobs` di dalam method `up()` sengaja dihapus, dan pembersihan tabel pada method `down()` juga dihilangkan.

---

## 3. Analisis Risiko & Dampak Nyata (Mengapa Cacat Ini Berbahaya)

1. **Fatal Crash pada Queue Worker Saat Job Gagal:**  
   Ketika ada job background (seperti pengiriman email notifikasi tugas/nilai) yang gagal akibat gangguan sementara (misal SMTP mail down, network timeout, atau database deadlock) dan batas retry habis, Laravel Queue Worker akan mengeksekusi `DatabaseUuidFailedJobProvider::log()` untuk menyimpan log kegagalan ke tabel `failed_jobs`. Karena tabel tersebut tidak ada di basis data, worker akan melempar error fatal:
   ```text
   SQLSTATE[HY000]: General error: 1 no such table: failed_jobs (atau Table 'failed_jobs' doesn't exist)
   ```
2. **Hilangnya Jejak Data Job Selamanya (*Permanent Data Loss*):**  
   Tanpa tabel `failed_jobs`, pengembang dan administrator tidak memiliki jejak (*audit trail*) mengenai job apa yang gagal, payload apa yang dibawanya, mahasiswa mana yang terdampak, serta stack trace penyebab error. Data job tersebut terbuang begitu saja tanpa bisa ditelusuri.
3. **Lumpuhnya Perintah Pemulihan Antrean (*Artisan Queue Commands*):**  
   Semua perintah standar Laravel untuk mendiagnosis dan memulihkan antrean menjadi tidak dapat digunakan sama sekali:
   - `php artisan queue:failed` → Langsung crash dengan error database.
   - `php artisan queue:retry all` (atau retry per ID) → Gagal karena tidak ada daftar job gagal yang dapat dibaca.
   - `php artisan queue:forget` dan `php artisan queue:flush` → Tidak berfungsi.
4. **Dampak Akademik Nyata:**  
   Mahasiswa tidak pernah menerima pemberitahuan tugas baru atau pengumuman nilai, sementara dosen dan admin tidak menyadari adanya kegagalan pengiriman karena log antrean gagal tidak tercatat di mana pun.

---

## 4. Solusi & Kode Perbaikan

Mengembalikan skema pembentukan tabel `failed_jobs` secara lengkap pada method `up()` serta penanganannya pada method `down()` di berkas migrasi [`database/migrations/0001_01_01_000002_create_jobs_table.php`](file:///c:/Proweb/kampuslms-kelompok-01/LMS-Broken/database/migrations/0001_01_01_000002_create_jobs_table.php):

### Diff Perbaikan:
```diff
--- a/database/migrations/0001_01_01_000002_create_jobs_table.php
+++ b/database/migrations/0001_01_01_000002_create_jobs_table.php
@@ -34,6 +34,15 @@ public function up(): void
             $table->integer('finished_at')->nullable();
         });
 
+        Schema::create('failed_jobs', function (Blueprint $table) {
+            $table->id();
+            $table->string('uuid')->unique();
+            $table->text('connection');
+            $table->text('queue');
+            $table->longText('payload');
+            $table->longText('exception');
+            $table->timestamp('failed_at')->useCurrent();
+        });
     }
 
     /**
@@ -43,5 +52,6 @@ public function down(): void
     {
         Schema::dropIfExists('jobs');
         Schema::dropIfExists('job_batches');
+        Schema::dropIfExists('failed_jobs');
     }
 };
```

---

## 5. Hasil Verifikasi & Pengujian

1. **Uji Validasi Sintaks:**  
   Perintah `php -l database/migrations/0001_01_01_000002_create_jobs_table.php` dijalankan dan menghasilkan:
   ```text
   No syntax errors detected in database/migrations/0001_01_01_000002_create_jobs_table.php
   ```
2. **Uji Perintah Artisan `queue:failed`:**  
   Menjalankan perintah pemeriksaan antrean gagal:
   ```powershell
   php artisan queue:failed
   ```
   **Output:**
   ```text
   INFO  No failed jobs found.
   ```
   Perintah berjalan normal dengan status sukses (Exit Code 0), membuktikan bahwa Laravel berhasil terhubung dan memverifikasi tabel `failed_jobs` tanpa melempar pengecualian (*exception*) database lagi.
