<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sks');
            $table->foreignId('lecturer_id')->constrained('users')->restrictOnDelete();
            // $table->enum('status', ['draft', 'active', 'archived'])->default('draft'); kolom status tidak diberi ->index(), dampaknya setiap kali pengguna membuka atau menyaring daftar mata kuliah, database terpaksa mengecek seluruh baris data satu per satu dari awal sampai akhir (full table scan) sehingga loading website menjadi lambat jika datanya sudah banyak, sehingga seharusnya ditambahkan ->index() agar database memiliki jalan pintas pencarian yang cepat dan efisien.
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
