<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CourseController extends Controller
{
    private array $courses = [
        [
            'id' => 1,
            'code' => 'SI2514024',
            'name' => 'Pemrograman Web',
            'sks' => 3,
            'lecturer' => 'Dr. Aris Sugiharto',
            'status' => 'active',
            'description' => 'Mata kuliah dasar pengembangan aplikasi web modern menggunakan Laravel 12.'
        ],
        [
            'id' => 2,
            'code' => 'SI2514025',
            'name' => 'Basis Data Lanjut',
            'sks' => 3,
            'lecturer' => 'Budi Santoso, M.T.',
            'status' => 'active',
            'description' => 'Pembahasan indexing, transaksi, dan optimisasi query database relational.'
        ],
        [
            'id' => 3,
            'code' => 'SI2514026',
            'name' => 'Keamanan Informasi',
            'sks' => 2,
            'lecturer' => 'Siti Aminah, Ph.D.',
            'status' => 'draft',
            'description' => 'Konsep dasar enkripsi, OWASP Top 10, dan pencegahan XSS/CSRF.'
        ],
    ];

    public function index()
    {
        // [FIX] Logika penyaringan data dipindahkan dari View ke sini (Controller).
        // View seharusnya hanya menampilkan data, bukan mengolahnya.
        // Sebelumnya array_filter() ada di index.blade.php — itu keliru.
        //
        // [FIX] Bug 1: Variabel yang benar adalah $this->courses, bukan $courses.
        // $courses tanpa $this-> tidak terdefinisi dan akan menyebabkan error.
        //
        // [FIX] Bug 2: Sebelumnya yang dikirim ke View adalah $this->courses (semua data),
        // bukan $activeCourses hasil filter. Sekarang sudah dibenarkan.
        $activeCourses = array_filter($this->courses, function($c) {
            return $c['status'] === 'active';
        });
        return view('courses.index', ['activeCourses' => $activeCourses]);
    }

    public function create()
    {
        return view('courses.create');
    }

    public function show($id)
    {
        $course = collect($this->courses)->firstWhere('id', (int) $id);
        if (!$course) {
            abort(404);
        }
        return view('courses.show', compact('course'));
    }

    public function destroy($id)
    {
        return redirect()->route('courses.index')->with('success', 'Mata kuliah berhasil dihapus');
    }
}