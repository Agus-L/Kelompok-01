# Catatan FIX Minggu 12 — Perbaikan Repo Cacat

## Identitas Mahasiswa
- **Nama:** Anastasya Salsabila Khoirunnisa  
- **NIM:** 10241009  
- **Kelompok:** Kelompok 01  
- **Tugas Khusus:** Memperbaiki masalah `db:seed` dijalankan tiap deploy  

---

## 1. Identifikasi Masalah

- **Masalah:** Perintah `php artisan db:seed --force` dipanggil secara otomatis pada setiap siklus deployment.
- **Lokasi Berkas:** `deploy.sh` (baris 19).
- **Kategori Risiko:** Risiko Tinggi (Integritas Data dan Ketersediaan Layanan Produksi).

---

## 2. Perbandingan Kode (Sebelum vs Sesudah)

### Sebelum Perbaikan (`deploy.sh`)
```bash
# Jalankan migrasi dan seeder database tiap deploy
php artisan migrate --force
php artisan db:seed --force
```

### Sesudah Perbaikan (`deploy.sh`)
```bash
# Jalankan migrasi database tiap deploy
# CATATAN PERBAIKAN: Perintah `php artisan db:seed --force` dihapus dari skrip deploy otomatis.
# Seeder hanya boleh dijalankan satu kali saat setup awal server. Jika dijalankan setiap kali deploy,
# seeder akan menyebabkan duplikasi data (error unique constraint) atau menimpa/mereset data pengguna nyata di produksi.
php artisan migrate --force
```

---

## 3. Analisis Teknis & Risiko Bahaya di Lingkungan Produksi

Menjalankan `db:seed` pada setiap eksekusi deployment di server produksi merupakan kesalahan fatal karena alasan berikut:

1. **Kegagalan Deployment Akibat Pelanggaran Unique Constraint:**  
   Seeder umumnya berisi data awal seperti akun bawaan (admin, dosen dummy, program studi). Ketika deploy dijalankan kedua kali atau seterusnya, seeder akan mencoba memasukkan kembali data yang sama. Hal ini memicu error basis data (`Integrity constraint violation: Duplicate entry`), yang mengakibatkan skrip deployment berhenti di tengah jalan saat aplikasi masih berada dalam mode *maintenance* (`php artisan down`).

2. **Risiko Tertimpa atau Terhapusnya Data Nyata Pengguna:**  
   Beberapa seeder menggunakan logika seperti `User::truncate()` atau memperbarui nilai default akun. Jika seeder dijalankan terus-menerus saat deploy rilis fitur baru, data riil mahasiswa, nilai tugas, dan aktivitas pembelajaran yang sudah tersimpan di produksi dapat terhapus atau tertimpa kembali ke nilai awal dummy.

3. **Memperpanjang Waktu Henti Layanan (Downtime):**  
   Saat deployment berlangsung, aplikasi dimatikan sementara dengan `php artisan down`. Menjalankan proses seeder yang berat pada setiap rilis akan memperpanjang waktu tunggu pengunjung dan memboroskan sumber daya komputasi server.

---

## 4. Solusi & Verifikasi

- **Solusi Tepat:** Menghapus baris `php artisan db:seed --force` dari skrip otomatis `deploy.sh`.
- **Aturan Operasional Seeder:** Perintah seeder hanya boleh dieksekusi secara manual dan satu kali saja saat inisialisasi awal server baru (*first-time setup*), bukan bagian dari skrip rilis rutin.
- **Verifikasi:** 
  1. Pada berkas `deploy.sh`, tahap pembaruan basis data kini hanya menyisakan `php artisan migrate --force`.
  2. Saat skrip `deploy.sh` dijalankan, proses rilis fitur baru berjalan lancar tanpa merusak integritas data tabel produksi.

---

## 5. Ringkasan untuk Deskripsi Pull Request (PR)

Bagian ini dapat disalin ke dalam deskripsi Pull Request ke repositori kelompok:

```markdown
### Perbaikan Masalah: db:seed dijalankan tiap deploy

- **Berkas yang Diubah:** `deploy.sh`
- **Tindakan:** Menghapus perintah `php artisan db:seed --force` dan hanya mempertahankan `php artisan migrate --force`.
- **Risiko Masalah:** Menjalankan seeder pada setiap rilis fitur baru di produksi dapat memicu error duplikasi data (`duplicate entry`), memperpanjang waktu downtime pemeliharaan, serta berisiko fatal menimpa atau mereset data nyata pengguna yang telah ada di basis data.
- **Hasil:** Skrip deployment kini hanya memperbarui skema migrasi tabel secara aman tanpa memanipulasi atau merusak data riil produksi.
```
