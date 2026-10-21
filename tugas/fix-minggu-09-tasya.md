# Catatan Belajar FIX Minggu 9 — KampusLMS Broken

## Identitas

Nama: Anastasya Salsabila Khoirunnisa  
NIM: 10241009  
Bagian Tugas: FIX — "Tidak ada penghapusan berkas fisik"  
Branch: `W09`  
Repository: `kampuslms-broken` (`Kelompok-01`)

---

## 1. Masalahnya Apa Sih?

Gampangnya begini:
Di dalam aplikasi web, data file itu disimpan di dua tempat yang berbeda:
1. **Catatannya di database:** Isinya info seperti judul materi, nama file, dan lokasi simpannya.
2. **File aslinya di folder komputer server (storage):** File PDF, Word, atau PPT yang beneran di-upload.

Ibaratnya, database itu seperti **daftar buku di perpustakaan**, sedangkan storage itu adalah **buku fisik di rak**.

### Kesalahan di Kode Lama:
Ketika dosen menekan tombol **Hapus Materi**, perintah bawaan Laravel (`$material->delete()`) ternyata **cuma menghapus catatannya di database saja**. Laravel tidak otomatis membuang file fisiknya yang ada di dalam folder storage.

Akibatnya:
Catatan materi di website memang hilang, tapi file aslinya masih tertinggal dan menumpuk terus di dalam folder server selamanya tanpa ada yang tahu. File yang ketinggalan tanpa catatan ini biasa disebut **file yatim (orphaned file)**.

---

## 2. Di Mana Letak Rusaknya?

* **Nama File:** `app/Http/Controllers/MaterialController.php`
* **Bagian Fungsi:** `destroy()` (fungsi untuk menghapus materi)
* **Baris:** Sekitar baris 68 sampai 80

---

## 3. Kenapa Masalah Ini Bahaya Kalau Dibiarkan?

1. **Bikin Memori Server Cepat Penuh:**  
   Kalau ada banyak dosen yang sering hapus dan ganti materi ukuran besar (misal 10 MB sampai 20 MB), file-file sampah yang sudah dihapus tadi bakal terus menumpuk di harddisk server sampai memorinya habis.
2. **Masalah Keamanan & Privasi:**  
   Misalnya dosen salah upload dokumen rahasia (seperti kunci jawaban atau data nilai mahasiswa), lalu dosen buru-buru menghapusnya dari website. Dosen mengira filenya sudah hilang, padahal file aslinya masih tersimpan utuh di dalam server dan bisa disalahgunakan jika ada yang tahu lokasinya.

---

## 4. Cara Memperbaikinya

Solusinya sederhana: sebelum kita menghapus catatan materi dari database, kita suruh sistem untuk menghapus file aslinya terlebih dahulu dari folder penyimpanan.

### Kode Sebelum Diperbaiki:
```php
public function destroy(Material $material)
{
    Gate::authorize('delete', $material);

    $course = $material->course;

    // Masalah: Cuma hapus di database, file aslinya di folder dibiarkan tertinggal
    $material->delete();

    return redirect()->route('courses.show', $course)->with('success', 'Materi berhasil dihapus.');
}
```

### Kode Setelah Diperbaiki:
```php
public function destroy(Material $material)
{
    Gate::authorize('delete', $material);

    $course = $material->course;

    // Perbaikan: Cek dulu apakah file aslinya ada di storage. Kalau ada, hapus file aslinya!
    if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
        Storage::disk('local')->delete($material->file_path);
    }

    // Baru setelah file aslinya bersih, hapus catatannya dari database
    $material->delete();

    return redirect()->route('courses.show', $course)->with('success', 'Materi berhasil dihapus.');
}
```

---

## 5. Cara Membuktikan Kalau Ini Sudah Benar

Kita bisa mengetesnya dengan cara sederhana:

1. **Upload Materi Baru:**  
   Masuk sebagai dosen, lalu upload satu file materi (misalnya materi PDF).
2. **Cek Foldernya:**  
   Buka folder `storage/app/private/materials/` di laptop/server. Pastikan ada file baru yang masuk ke sana.
3. **Klik Hapus:**  
   Di website, klik tombol hapus pada materi yang barusan di-upload.
4. **Cek Kembali Foldernya:**  
   Buka lagi folder penyimpanan tadi. Sekarang file fisiknya sudah benar-benar hilang terhapus bersih, dan di tabel database juga sudah tidak ada.

---

## 6. Poin Penting Buat Diingat Saat Ditanya Dosen

* **Kenapa harus hapus file dulu baru hapus data di database?**  
  Karena kalau kita hapus data di databasenya duluan, sistem bisa lupa di mana alamat lokasi filenya disimpan. Jadi file fisik harus dihapus lebih dulu selagi sistem masih tahu alamat filenya.
* **Inti perbaikannya:**  
  Menambahkan `Storage::disk('local')->delete($material->file_path)` agar saat materi dihapus, file fisiknya ikut terhapus bersih dan tidak menyampah di server.
