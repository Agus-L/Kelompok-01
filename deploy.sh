#!/bin/bash
# Skrip deploy.sh salah karena tidak memiliki perintah set -e di awal sehingga jika ada langkah yang gagal skrip tetap dipaksa lanjut dan membuat website rusak jadi ditambahkan set -e agar proses deploy langsung otomatis berhenti saat terjadi error.
set -e 
# CATATAN: Dijalankan di server production saat deploy rilis baru

echo "Starting deployment..."

# Masuk ke mode maintenance
php artisan down || true

# Update kode dari repositori
git pull origin main

# Install dependensi PHP tanpa dev dependencies
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Jalankan migrasi database tiap deploy
# CATATAN PERBAIKAN: Perintah `php artisan db:seed --force` dihapus dari skrip deploy otomatis.
# Seeder hanya boleh dijalankan satu kali saat setup awal server. Jika dijalankan setiap kali deploy,
# seeder akan menyebabkan duplikasi data (error unique constraint) atau menimpa/mereset data pengguna nyata di produksi.
php artisan migrate --force

# Cache konfigurasi, routes, dan views untuk performa
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Sebelumnya belum ada perintah queue:restart sehingga pekerja latar belakang (worker) tetap menjalankan kode lama di memori server jadi ditambahkan php artisan queue:restart agar antrean otomatis memuat kodingan terbaru pasca-deploy.
php artisan queue:restart

# Keluar dari mode maintenance
php artisan up

echo "Deployment finished successfully!"
